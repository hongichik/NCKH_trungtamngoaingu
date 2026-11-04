<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        $locations = config('menu.locations');
        $location = array_key_exists($request->query('location'), $locations) ? $request->query('location') : array_key_first($locations);

        $items = MenuItem::where('location', $location)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('order')->orderBy('id')
            ->get();

        return view('admin.menus.index', compact('locations', 'location', 'items'));
    }

    public function create(Request $request): View
    {
        $item = new MenuItem([
            'location' => array_key_exists($request->query('location'), config('menu.locations')) ? $request->query('location') : 'header',
            'parent_id' => $request->query('parent_id'),
            'link_type' => 'route',
            'is_active' => true,
        ]);

        return view('admin.menus.form', $this->formData($item));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['order'] = (int) MenuItem::where('location', $data['location'])
            ->where('parent_id', $data['parent_id'])
            ->max('order') + 1;

        MenuItem::create($data);

        return redirect()->route('admin.menus.index', ['location' => $data['location']])->with('status', 'Đã thêm mục menu.');
    }

    public function edit(MenuItem $menu): View
    {
        return view('admin.menus.form', $this->formData($menu));
    }

    public function update(Request $request, MenuItem $menu): RedirectResponse
    {
        $data = $this->validateData($request, $menu);

        DB::transaction(function () use ($menu, $data) {
            $moved = $menu->location !== $data['location'] || $menu->parent_id != $data['parent_id'];
            if ($moved) {
                $data['order'] = (int) MenuItem::where('location', $data['location'])
                    ->where('parent_id', $data['parent_id'])
                    ->max('order') + 1;
            }

            $menu->update($data);

            // Children follow their parent to the new location.
            $menu->children()->update(['location' => $data['location']]);
        });

        return redirect()->route('admin.menus.index', ['location' => $data['location']])->with('status', 'Đã cập nhật mục menu.');
    }

    public function destroy(MenuItem $menu): RedirectResponse
    {
        $location = $menu->location;
        $menu->delete();

        return redirect()->route('admin.menus.index', ['location' => $location])->with('status', 'Đã xoá mục menu.');
    }

    public function toggle(MenuItem $menu): RedirectResponse
    {
        $menu->update(['is_active' => ! $menu->is_active]);

        return back()->with('status', $menu->is_active ? 'Đã hiện mục menu.' : 'Đã ẩn mục menu.');
    }

    /**
     * Save drag-and-drop order. Payload: items = [{id, parent_id}, ...] in display order.
     */
    public function reorder(Request $request): JsonResponse
    {
        $locations = config('menu.locations');
        $data = $request->validate([
            'location' => ['required', Rule::in(array_keys($locations))],
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.parent_id' => ['nullable', 'integer'],
        ]);

        $ids = collect($data['items'])->pluck('id');
        $owned = MenuItem::where('location', $data['location'])->whereIn('id', $ids)->pluck('id');
        if ($owned->count() !== $ids->unique()->count()) {
            return response()->json(['message' => 'Dữ liệu menu không hợp lệ, vui lòng tải lại trang.'], 422);
        }

        $nested = $locations[$data['location']]['nested'];

        DB::transaction(function () use ($data, $ids, $nested) {
            $counters = [];
            foreach ($data['items'] as $row) {
                $parentId = $nested ? ($row['parent_id'] ?? null) : null;
                // Only one level of nesting, and only under an item of this same location.
                if ($parentId !== null && ! $ids->contains($parentId)) {
                    $parentId = null;
                }
                $key = $parentId ?? 0;
                $counters[$key] = ($counters[$key] ?? 0) + 1;

                MenuItem::whereKey($row['id'])->update(['parent_id' => $parentId, 'order' => $counters[$key]]);
            }
        });

        // Query-builder updates skip model events, so flush the cache by hand.
        Cache::forget(MenuItem::CACHE_KEY);

        return response()->json(['message' => 'Đã lưu thứ tự menu.']);
    }

    protected function formData(MenuItem $item): array
    {
        return [
            'item' => $item,
            'locations' => config('menu.locations'),
            'routes' => config('menu.routes'),
            'pages' => Page::orderBy('title')->get(['id', 'title', 'status']),
            'categories' => Category::orderBy('order')->orderBy('name')->get(['id', 'name']),
            // Possible parents: top-level items in nested locations (excluding itself).
            'parents' => MenuItem::whereNull('parent_id')
                ->whereIn('location', collect(config('menu.locations'))->filter(fn ($l) => $l['nested'])->keys())
                ->when($item->exists, fn ($q) => $q->where('id', '!=', $item->id))
                ->orderBy('order')->get(['id', 'title', 'location']),
        ];
    }

    protected function validateData(Request $request, ?MenuItem $item = null): array
    {
        $locations = config('menu.locations');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'location' => ['required', Rule::in(array_keys($locations))],
            'parent_id' => ['nullable', 'integer', Rule::exists('menu_items', 'id')->whereNull('parent_id')],
            'link_type' => ['required', Rule::in(array_keys(MenuItem::LINK_TYPES))],
            'route_value' => ['required_if:link_type,route', 'nullable', Rule::in(array_keys(config('menu.routes')))],
            'page_value' => ['required_if:link_type,page', 'nullable', 'integer', 'exists:pages,id'],
            'category_value' => ['required_if:link_type,category', 'nullable', 'integer', 'exists:categories,id'],
            'url_value' => ['required_if:link_type,url', 'nullable', 'string', 'max:500', 'regex:~^(https?://|/|#|tel:|mailto:|[a-z0-9-])~i'],
            'open_new_tab' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'required_if' => 'Vui lòng chọn hoặc nhập đích đến của liên kết.',
            'url_value.regex' => 'Đường dẫn phải bắt đầu bằng http(s)://, /, #, tel: hoặc mailto:.',
        ], [
            'title' => 'tên hiển thị',
            'location' => 'vị trí',
        ]);

        $parentId = $data['parent_id'] ?? null;
        if (! $locations[$data['location']]['nested']) {
            $parentId = null;
        }
        if ($parentId) {
            if ($item && $parentId == $item->id) {
                $parentId = null;
            } elseif ($item && $item->children()->exists()) {
                // Max 2 levels: an item that already has children can't become a child itself.
                throw ValidationException::withMessages(['parent_id' => 'Mục này đang có menu con nên không thể đặt làm menu con.']);
            } elseif (MenuItem::find($parentId)?->location !== $data['location']) {
                $parentId = null;
            }
        }

        return [
            'title' => $data['title'],
            'location' => $data['location'],
            'parent_id' => $parentId,
            'link_type' => $data['link_type'],
            'link_value' => $data[$data['link_type'].'_value'] ?? null,
            'open_new_tab' => $request->boolean('open_new_tab'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
