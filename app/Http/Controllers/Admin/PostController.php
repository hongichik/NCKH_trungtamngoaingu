<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public const SORTS = [
        'newest' => 'Mới nhất',
        'oldest' => 'Cũ nhất',
        'updated' => 'Mới sửa gần đây',
        'views_desc' => 'Xem nhiều nhất',
        'views_asc' => 'Xem ít nhất',
        'title' => 'Tiêu đề A → Z',
    ];

    public function index(Request $request): View
    {
        // Invalid values are dropped rather than redirecting (which would loop on a bad URL).
        $filters = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:published,draft'],
            'category' => ['nullable', 'string', 'max:20'],
            'featured' => ['nullable', 'in:1,0'],
            'image' => ['nullable', 'in:with,without'],
            'author' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))],
            'per_page' => ['nullable', 'in:20,50,100'],
        ])->valid();

        // Everything except the status tab, so the tab counts reflect the other filters.
        $base = Post::query()
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('slug', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->when($filters['category'] ?? null, fn ($q, $category) => $category === 'none'
                ? $q->doesntHave('categories')
                : $q->whereHas('categories', fn ($c) => $c->whereKey((int) $category)))
            ->when(isset($filters['featured']), fn ($q) => $q->where('is_featured', (bool) $filters['featured']))
            ->when($filters['image'] ?? null, fn ($q, $image) => $image === 'with' ? $q->whereNotNull('image') : $q->whereNull('image'))
            ->when($filters['author'] ?? null, fn ($q, $author) => $q->where('user_id', $author))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));

        $statusCounts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $query = (clone $base)
            ->with(['categories', 'user'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest()->oldest('id'),
            'updated' => $query->latest('updated_at'),
            'views_desc' => $query->orderByDesc('views'),
            'views_asc' => $query->orderBy('views'),
            'title' => $query->orderBy('title'),
            default => $query->latest()->latest('id'),
        };

        $posts = $query->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();

        // Return to this exact filtered page after saving or deleting a post.
        $request->session()->put('admin.posts.index_url', $request->fullUrl());

        return view('admin.posts.index', [
            'posts' => $posts,
            'filters' => $filters,
            'statusCounts' => $statusCounts,
            'categories' => Category::withCount('posts')->orderBy('name')->get(),
            'authors' => User::whereIn('id', Post::whereNotNull('user_id')->distinct()->pluck('user_id'))->orderBy('name')->get(['id', 'name']),
            'sorts' => self::SORTS,
            'activeFilterCount' => collect($filters)->except(['sort', 'per_page', 'status'])->filter(fn ($v) => $v !== null && $v !== '')->count(),
        ]);
    }

    /** The list page the admin came from, keeping their filters. */
    protected function backToIndex(): RedirectResponse
    {
        return redirect(session('admin.posts.index_url', route('admin.posts.index')));
    }

    public function create(): View
    {
        $categories = Category::orderBy('name')->get();

        return view('admin.posts.form', ['post' => new Post, 'categories' => $categories]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $data['slug'] = $this->resolveSlug($data['title'], $data['slug'] ?? null);
        $data['user_id'] = $request->user()->id;

        if ($request->hasFile('image')) {
            $data['image'] = ImageOptimizer::store($request->file('image'), 'posts');
        }

        $post = Post::create($data);
        $post->categories()->sync($request->input('categories', []));

        $this->storeGalleryImages($request, $post);

        return $this->backToIndex()->with('status', 'Đã tạo bài viết.');
    }

    public function edit(Post $post): View
    {
        $categories = Category::orderBy('name')->get();
        $post->load('images');

        return view('admin.posts.form', compact('post', 'categories'));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $this->validateData($request, $post->id);

        $data['slug'] = $this->resolveSlug($data['title'], $data['slug'] ?? null, $post->id);

        if ($request->boolean('remove_image') && $post->image) {
            Storage::disk('public')->delete($post->image);
            $data['image'] = null;
        }

        if ($request->hasFile('image')) {
            if ($post->image) {
                Storage::disk('public')->delete($post->image);
            }
            $data['image'] = ImageOptimizer::store($request->file('image'), 'posts');
        }

        $post->update($data);
        $post->categories()->sync($request->input('categories', []));

        $this->storeGalleryImages($request, $post);

        return $this->backToIndex()->with('status', 'Đã cập nhật bài viết.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return $this->backToIndex()->with('status', 'Đã xoá bài viết.');
    }

    public function destroyImage(Post $post, PostImage $image): RedirectResponse
    {
        abort_unless($image->post_id === $post->id, 404);

        Storage::disk('public')->delete($image->image);
        $image->delete();

        return back()->with('status', 'Đã xoá ảnh.');
    }

    protected function storeGalleryImages(Request $request, Post $post): void
    {
        foreach ($request->file('gallery_images', []) as $file) {
            $post->images()->create(['image' => ImageOptimizer::store($file, 'posts')]);
        }
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('posts', 'slug')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:400'],
            'content' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:10240'],
            'gallery_images.*' => ['nullable', 'image', 'max:10240'],
            'is_featured' => ['sometimes', 'boolean'],
            'status' => ['required', 'in:published,draft'],
        ]);
    }

    protected function resolveSlug(string $title, ?string $desiredSlug, ?int $ignoreId = null): string
    {
        $slug = Str::slug($desiredSlug ?: $title);

        if ($slug === '') {
            $slug = Str::slug($title);
        }

        $original = $slug;
        $i = 1;

        while (Post::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}
