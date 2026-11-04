<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Public-site menus managed in Admin > Nội dung > Menu website.
        View::composer('layouts.app', function ($view) {
            $view->with('siteMenus', Schema::hasTable('menu_items') ? MenuItem::tree() : []);
        });

        // Menu links to pages/categories embed their slug, so refresh the cached menu when those change.
        foreach ([Page::class, Category::class] as $model) {
            $model::saved(fn () => Cache::forget(MenuItem::CACHE_KEY));
            $model::deleted(fn () => Cache::forget(MenuItem::CACHE_KEY));
        }
    }
}
