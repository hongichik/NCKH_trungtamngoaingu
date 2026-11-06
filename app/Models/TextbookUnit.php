<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TextbookUnit extends Model
{
    public const STATUS_LABELS = [
        'pending' => 'Chưa phân tích',
        'analyzing' => 'Đang phân tích',
        'ready' => 'Có hồ sơ',
        'failed' => 'Lỗi',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['skipped' => 'boolean'];
    }

    public function textbook(): BelongsTo
    {
        return $this->belongsTo(Textbook::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(TextbookChunk::class, 'unit_id')->orderBy('position');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UnitProfile::class, 'unit_id');
    }

    public function audioTracks(): HasMany
    {
        return $this->hasMany(AudioTrack::class, 'unit_id');
    }

    public function exerciseSets(): HasMany
    {
        return $this->hasMany(ExerciseSet::class, 'unit_id')->latest('id');
    }

    public function pageRange(): string
    {
        return $this->start_page === $this->end_page ? "tr. {$this->start_page}" : "tr. {$this->start_page}–{$this->end_page}";
    }
}
