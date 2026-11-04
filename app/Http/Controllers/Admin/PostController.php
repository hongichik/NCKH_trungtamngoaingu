<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(): View
    {
        $posts = Post::with('categories')->latest()->paginate(20);

        return view('admin.posts.index', compact('posts'));
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
            $data['image'] = $request->file('image')->store('posts', 'public');
        }

        $post = Post::create($data);
        $post->categories()->sync($request->input('categories', []));

        $this->storeGalleryImages($request, $post);

        return redirect()->route('admin.posts.index')->with('status', 'Đã tạo bài viết.');
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
            $data['image'] = $request->file('image')->store('posts', 'public');
        }

        $post->update($data);
        $post->categories()->sync($request->input('categories', []));

        $this->storeGalleryImages($request, $post);

        return redirect()->route('admin.posts.index')->with('status', 'Đã cập nhật bài viết.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('admin.posts.index')->with('status', 'Đã xoá bài viết.');
    }

    /**
     * Upload an image dropped/pasted/inserted into the content editor and
     * return its public URL, so the editor stores a link instead of
     * embedding the image as base64.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission('posts', 'create') || $request->user()->hasPermission('posts', 'edit'),
            403
        );

        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $path = $request->file('image')->store('posts', 'public');

        return response()->json(['url' => Storage::url($path)]);
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
            $post->images()->create(['image' => $file->store('posts', 'public')]);
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
            'image' => ['nullable', 'image', 'max:4096'],
            'gallery_images.*' => ['nullable', 'image', 'max:4096'],
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
