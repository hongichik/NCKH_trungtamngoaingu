<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
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
        // Nhà cung cấp AI dùng chung (đổi bằng AI_PROVIDER).
        $this->app->bind(\App\Support\Ai\AiProvider::class, function () {
            return match (config('ai.provider')) {
                default => new \App\Support\Ai\OpenAiProvider((string) config('ai.openai.key'), (string) config('ai.openai.base_url')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Relative dates ("2 năm trước") in Vietnamese without switching the whole app locale.
        Carbon::setLocale('vi');

        // The site name edited in Admin > Cài đặt website replaces APP_NAME everywhere.
        Setting::flushLoaded();
        if ($siteName = setting('site_name')) {
            config(['app.name' => $siteName]);
        }

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
