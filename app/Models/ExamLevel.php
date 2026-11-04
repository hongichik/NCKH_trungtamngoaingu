<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamLevel extends Model
{
    protected $fillable = [
        'exam_type', 'name', 'price', 'order', 'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'is_active' => 'boolean',
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(ExamRegistration::class);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('exam_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
