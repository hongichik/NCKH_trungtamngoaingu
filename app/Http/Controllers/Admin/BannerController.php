<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BannerController extends Controller
{
    public function index(): View
    {
        return view('admin.banners.index', [
            'banners' => Banner::orderBy('order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.banners.form', ['banner' => new Banner(['type' => 'image', 'background' => 'brand', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        if ($data['type'] === 'image' && ! $request->hasFile('image')) {
            return back()->withInput()->withErrors(['image' => 'Banner dạng ảnh poster cần có ảnh.']);
        }

        $data['order'] = (int) Banner::max('order') + 1;
        $data += $this->storeImages($request, new Banner);

        Banner::create($data);

        return redirect()->route('admin.banners.index')->with('status', 'Đã thêm banner.');
    }

    public function edit(Banner $banner): View
    {
        return view('admin.banners.form', compact('banner'));
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $data = $this->validateData($request);

        $keepsImage = $banner->image && ! $request->boolean('remove_image');
        if ($data['type'] === 'image' && ! $request->hasFile('image') && ! $keepsImage) {
            return back()->withInput()->withErrors(['image' => 'Banner dạng ảnh poster cần có ảnh.']);
        }

        $data = array_merge($data, $this->storeImages($request, $banner));

        $banner->update($data);

        return redirect()->route('admin.banners.index')->with('status', 'Đã cập nhật banner.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        Storage::disk('public')->delete(array_filter([$banner->image, $banner->image_mobile]));
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('status', 'Đã xoá banner.');
    }

    public function toggle(Banner $banner): RedirectResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('status', $banner->is_active ? 'Đã hiện banner.' : 'Đã ẩn banner.');
    }

    /** Save drag-and-drop order: ids = [3, 1, 2]. */
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                Banner::whereKey($id)->update(['order' => $i + 1]);
            }
        });

        return response()->json(['message' => 'Đã lưu thứ tự banner.']);
    }

    /** @return array<string, string|null> */
    protected function storeImages(Request $request, Banner $banner): array
    {
        $data = [];

        foreach (['image' => [2400, 1200], 'image_mobile' => [1200, 1600]] as $field => [$w, $h]) {
            $remove = $request->boolean('remove_'.$field);
            if (($remove || $request->hasFile($field)) && $banner->$field) {
                Storage::disk('public')->delete($banner->$field);
                $data[$field] = null;
            }
            if ($request->hasFile($field)) {
                $data[$field] = ImageOptimizer::store($request->file($field), 'banners', $w, $h, 88);
            }
        }

        return $data;
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Banner::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'bullet_points' => ['nullable', 'string', 'max:1000'],
            'background' => ['required', Rule::in(array_keys(Banner::BACKGROUNDS))],
            'button_text' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image' => ['nullable', 'image', 'max:10240'],
            'image_mobile' => ['nullable', 'image', 'max:10240'],
        ], [
            'ends_at.after_or_equal' => 'Ngày kết thúc phải sau ngày bắt đầu.',
        ], [
            'title' => 'tiêu đề',
            'image' => 'ảnh banner',
            'image_mobile' => 'ảnh cho điện thoại',
        ]);

        unset($data['image'], $data['image_mobile']);
        $data['open_new_tab'] = $request->boolean('open_new_tab');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
