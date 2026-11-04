<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSession extends Model
{
    protected $fillable = [
        'exam_type', 'name', 'exam_date', 'capacity', 'registration_start', 'registration_end', 'status',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'registration_start' => 'date',
        'registration_end' => 'date',
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(ExamRegistration::class);
    }

    /**
     * Registrations that actually hold a seat (cancelled ones free it up).
     */
    public function activeRegistrations(): HasMany
    {
        return $this->registrations()->where('status', '!=', 'cancelled');
    }

    public function isFull(): bool
    {
        if ($this->capacity === null) {
            return false;
        }

        $count = $this->relationLoaded('activeRegistrations')
            ? $this->activeRegistrations->count()
            : $this->activeRegistrations()->count();

        return $count >= $this->capacity;
    }

    public function scopeType($query, string $type)
    {
        return $query->where('exam_type', $type);
    }

    public function scopeOpenForRegistration($query)
    {
        return $query->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('registration_end')->orWhere('registration_end', '>=', now()->toDateString()));
    }
}
