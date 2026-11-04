<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class MenuItem extends Model
{
    public const CACHE_KEY = 'menu_items.tree';

    public const LINK_TYPES = [
        'route' => 'Trang hệ thống',
        'page' => 'Trang tĩnh',
        'category' => 'Danh mục tin',
        'url' => 'Đường dẫn tự nhập',
    ];

    protected $fillable = [
        'location', 'parent_id', 'title', 'link_type', 'link_value',
        'open_new_tab', 'is_active', 'order',
    ];

    protected $casts = [
        'open_new_tab' => 'boolean',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order')->orderBy('id');
    }

    /**
     * Active menu trees for the public site, keyed by location. Page/category
     * slugs are resolved here (once, then cached) so rendering needs no queries.
     *
     * @return array<string, Collection<int, MenuItem>>
     */
    public static function tree(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $items = self::where('is_active', true)->orderBy('order')->orderBy('id')->get();

            $slugs = [
                'page' => Page::where('status', 'published')
                    ->whereIn('id', $items->where('link_type', 'page')->pluck('link_value'))
                    ->pluck('slug', 'id'),
                'category' => Category::whereIn('id', $items->where('link_type', 'category')->pluck('link_value'))
                    ->pluck('slug', 'id'),
            ];

            // Drop items whose target page/category no longer exists (or is unpublished).
            $items = $items->filter(fn (self $item) => ! isset($slugs[$item->link_type]) || $slugs[$item->link_type]->has((int) $item->link_value));
            $items->each(fn (self $item) => $item->setAttribute('resolved_slug', $slugs[$item->link_type][(int) $item->link_value] ?? null));

            $tree = [];
            foreach (array_keys(config('menu.locations')) as $location) {
                $inLocation = $items->where('location', $location);
                $roots = $inLocation->whereNull('parent_id')->values();
                $roots->each(fn (self $root) => $root->setRelation('children', $inLocation->where('parent_id', $root->id)->values()));
                $tree[$location] = $roots;
            }

            return $tree;
        });
    }

    /**
     * Keys like "blog.index" contain dots, so they can't go through config()'s dot notation.
     */
    protected function routeDefinition(): ?array
    {
        return config('menu.routes', [])[$this->link_value] ?? null;
    }

    public function getUrlAttribute(): string
    {
        switch ($this->link_type) {
            case 'route':
                $def = $this->routeDefinition();
                return $def && Route::has($def['route']) ? route($def['route'], $def['params'] ?? []) : '#';
            case 'page':
                $slug = $this->resolved_slug ?? Page::find($this->link_value)?->slug;
                return $slug ? route('public.show', $slug) : '#';
            case 'category':
                $slug = $this->resolved_slug ?? Category::find($this->link_value)?->slug;
                return $slug ? route('category.show', $slug) : '#';
            default:
                $value = trim((string) $this->link_value);
                if ($value === '') {
                    return '#';
                }
                // Allow "/lien-he", "#section", "tel:", "mailto:" and full URLs.
                return preg_match('~^(https?://|/|#|tel:|mailto:)~i', $value) ? $value : url($value);
        }
    }

    /**
     * Whether this item (or one of its children) points at the current page.
     */
    public function isActive(): bool
    {
        if ($this->relationLoaded('children') && $this->children->contains(fn (self $child) => $child->isActive())) {
            return true;
        }

        $request = request();

        if ($this->link_type === 'route') {
            $def = $this->routeDefinition();
            if (! $def || ! $request->routeIs($def['active'] ?? [$def['route']])) {
                return false;
            }
            foreach ($def['query'] ?? [] as $key => $value) {
                if ($request->query($key) !== $value) {
                    return false;
                }
            }
            return true;
        }

        $url = $this->url;
        if ($url === '#' || str_starts_with($url, '#')) {
            return false;
        }

        $parts = parse_url($url);
        if (isset($parts['host']) && $parts['host'] !== $request->getHost()) {
            return false;
        }

        return trim($parts['path'] ?? '/', '/') === trim($request->path(), '/');
    }

    /**
     * Human-readable description of the link target, for the admin list.
     */
    public function getTargetLabelAttribute(): string
    {
        return match ($this->link_type) {
            'route' => ($this->routeDefinition()['label'] ?? '(không xác định)'),
            'page' => 'Trang: '.(Page::find($this->link_value)?->title ?? '(đã xoá)'),
            'category' => 'Danh mục: '.(Category::find($this->link_value)?->name ?? '(đã xoá)'),
            default => (string) $this->link_value,
        };
    }
}
