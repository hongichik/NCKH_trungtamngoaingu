<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Certificate;
use App\Models\Page;
use App\Models\Post;
use App\Models\Student;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function home(): View
    {
        $heroPosts = Post::published()->where('is_featured', true)->latest()->take(5)->get();
        if ($heroPosts->isEmpty()) {
            $heroPosts = Post::published()->latest()->take(5)->get();
        }

        $latestPosts = Post::published()
            ->with('categories')
            ->whereNotIn('id', $heroPosts->pluck('id'))
            ->latest()
            ->take(8)
            ->get();

        $newsCategories = Category::withCount(['posts as posts_count' => fn ($q) => $q->published()])
            ->having('posts_count', '>=', 5)
            ->orderByDesc('posts_count')
            ->take(3)
            ->get()
            ->each(fn (Category $category) => $category->setRelation(
                'recentPosts',
                $category->posts()->published()->latest()->take(6)->get()
            ));

        return view('public.home', [
            'heroPosts' => $heroPosts,
            'latestPosts' => $latestPosts,
            'newsCategories' => $newsCategories,
            'stats' => [
                'students' => Student::count(),
                'certificates' => Certificate::count(),
            ],
        ]);
    }

    public function blogIndex(): View
    {
        return view('public.blog-index', [
            'posts' => Post::published()->with('categories')->latest()->paginate(12),
            'categories' => Category::withCount(['posts as posts_count' => fn ($q) => $q->published()])
                ->having('posts_count', '>', 0)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function category(Category $category): View
    {
        return view('public.category', [
            'category' => $category,
            'posts' => $category->posts()->published()->latest()->paginate(12),
        ]);
    }

    /**
     * Resolve a bare slug to either a post or a static page,
     * matching the original site's flat URL structure (e.g. /thong-tin-tuyen-sinh).
     */
    public function show(string $slug): View
    {
        $post = Post::published()->where('slug', $slug)->with(['categories', 'images'])->first();

        if ($post) {
            $post->increment('views');

            $categoryIds = $post->categories->pluck('id');

            $relatedPosts = Post::published()
                ->where('id', '!=', $post->id)
                ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereHas(
                    'categories',
                    fn ($q2) => $q2->whereIn('categories.id', $categoryIds)
                ))
                ->latest()
                ->take(5)
                ->get();

            if ($relatedPosts->count() < 5) {
                $relatedPosts = $relatedPosts->concat(
                    Post::published()
                        ->where('id', '!=', $post->id)
                        ->whereNotIn('id', $relatedPosts->pluck('id'))
                        ->latest()
                        ->take(5 - $relatedPosts->count())
                        ->get()
                );
            }

            return view('public.post', [
                'post' => $post,
                'relatedPosts' => $relatedPosts,
            ]);
        }

        $page = Page::where('status', 'published')->where('slug', $slug)->firstOrFail();

        return view('public.page', ['page' => $page]);
    }
}
