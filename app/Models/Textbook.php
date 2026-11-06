<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Textbook extends Model
{
    public const STATUS_LABELS = [
        'uploading' => 'Đang tải lên',
        'extracting' => 'Đang trích chữ',
        'await_ocr' => 'Chờ xác nhận đọc trang scan',
        'ocr' => 'Đang đọc trang scan',
        'detecting' => 'Đang nhận diện Unit',
        'review_units' => 'Chờ duyệt danh sách Unit',
        'processing' => 'Đang phân tích',
        'ready' => 'Sẵn sàng',
        'failed' => 'Lỗi',
    ];

    public const LANGUAGES = ['en' => 'Tiếng Anh', 'zh' => 'Tiếng Trung'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['outline' => 'array'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(TextbookPage::class)->orderBy('page_no');
    }

    public function units(): HasMany
    {
        return $this->hasMany(TextbookUnit::class)->orderBy('position');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(TextbookChunk::class);
    }

    public function audioTracks(): HasMany
    {
        return $this->hasMany(AudioTrack::class)->orderBy('original_name');
    }

    /** Khoá đầu tiên dùng giáo trình này (một giáo trình có thể dùng chung cho nhiều khoá). */
    public function course(): HasOne
    {
        return $this->hasOne(Course::class)->oldest('id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** Nhóm việc nền của giáo trình này trong ai_jobs. */
    public function batch(): string
    {
        return 'textbook:'.$this->id;
    }

    public function storageDir(): string
    {
        return 'textbooks/'.$this->id;
    }
}
