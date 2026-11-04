<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(): View
    {
        $galleries = Gallery::withCount('images')->latest()->paginate(20);

        return view('admin.galleries.index', compact('galleries'));
    }

    public function create(): View
    {
        return view('admin.galleries.form', ['gallery' => new Gallery]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['user_id'] = $request->user()->id;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('galleries', 'public');
        }

        $gallery = Gallery::create($data);

        foreach ($request->file('images', []) as $file) {
            $gallery->images()->create(['image' => $file->store('galleries', 'public')]);
        }

        return redirect()->route('admin.galleries.index')->with('status', 'Đã tạo thư viện ảnh.');
    }

    public function edit(Gallery $gallery): View
    {
        $gallery->load('images');

        return view('admin.galleries.form', compact('gallery'));
    }

    public function update(Request $request, Gallery $gallery): RedirectResponse
    {
        $data = $this->validateData($request);

        if ($data['name'] !== $gallery->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $gallery->id);
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('galleries', 'public');
        }

        $gallery->update($data);

        foreach ($request->file('images', []) as $file) {
            $gallery->images()->create(['image' => $file->store('galleries', 'public')]);
        }

        return redirect()->route('admin.galleries.index')->with('status', 'Đã cập nhật thư viện ảnh.');
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        $gallery->delete();

        return redirect()->route('admin.galleries.index')->with('status', 'Đã xoá thư viện ảnh.');
    }

    public function destroyImage(Gallery $gallery, \App\Models\GalleryImage $image): RedirectResponse
    {
        abort_unless($image->gallery_id === $gallery->id, 404);
        $image->delete();

        return back()->with('status', 'Đã xoá ảnh.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:400'],
            'image' => ['nullable', 'image', 'max:4096'],
            'images.*' => ['nullable', 'image', 'max:4096'],
            'is_featured' => ['sometimes', 'boolean'],
            'status' => ['required', 'in:published,draft'],
        ]);
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;

        while (Gallery::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}
