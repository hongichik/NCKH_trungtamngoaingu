<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    public const TYPES = [
        'image' => 'Ảnh poster (ảnh đã có sẵn chữ)',
        'text' => 'Chữ trên nền màu / ảnh',
    ];

    public const BACKGROUNDS = [
        'brand' => 'Xanh thương hiệu',
        'accent' => 'Cam → xanh',
        'dark' => 'Tối',
    ];

    protected $fillable = [
        'type', 'title', 'badge', 'description', 'bullet_points', 'image', 'image_mobile',
        'background', 'button_text', 'button_url', 'open_new_tab',
        'starts_at', 'ends_at', 'is_active', 'order',
    ];

    protected $casts = [
        'open_new_tab' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /** Active banners whose schedule (if any) includes now, in display order. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('order')->orderBy('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::url($this->image) : null;
    }

    public function getImageMobileUrlAttribute(): ?string
    {
        return $this->image_mobile ? Storage::url($this->image_mobile) : null;
    }

    /** @return list<string> */
    public function getBulletListAttribute(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->bullet_points))));
    }

    /** Human-readable schedule status for the admin list. */
    public function getScheduleStatusAttribute(): ?string
    {
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'Chưa đến lịch (từ '.$this->starts_at->format('d/m/Y H:i').')';
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'Đã hết hạn ('.$this->ends_at->format('d/m/Y H:i').')';
        }

        return null;
    }

    public function getButtonHrefAttribute(): ?string
    {
        $url = trim((string) $this->button_url);
        if ($url === '') {
            return null;
        }

        return preg_match('~^(https?://|/|#|tel:|mailto:)~i', $url) ? $url : url($url);
    }
}
