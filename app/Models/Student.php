<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Student extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The only two authorities that appear as "Nơi cấp" on a CCCD: the current
     * chip-based card (issued since 10/10/2018) and the older barcode card
     * (issued 01/01/2016 - 10/10/2018). Any CCCD in circulation today shows
     * one of these two exact values.
     */
    public const IDENTITY_PLACE_OPTIONS = [
        'Cục Cảnh sát quản lý hành chính về trật tự xã hội',
        'Cục Cảnh sát ĐKQL cư trú và DLQG về dân cư',
    ];

    protected $fillable = [
        'numbering', 'first_name', 'last_name', 'chinese_name', 'candidate_number', 'description',
        'gender', 'email', 'password', 'phone', 'dob', 'birthplace', 'ethnic',
        'religion', 'nationality', 'mother_tongue', 'chinese_years',
        'identity_number', 'identity_date', 'identity_place', 'avatar', 'status',
        'address_province', 'address_ward', 'address_detail',
        'identity_front_image', 'identity_back_image',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'dob' => 'date',
        'identity_date' => 'date',
        'password' => 'hashed',
    ];

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function examRegistrations(): HasMany
    {
        return $this->hasMany(ExamRegistration::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->last_name} {$this->first_name}");
    }

    /**
     * Fields required to complete the base candidate profile, needed
     * before registering for either exam type.
     */
    public function hasCompletedCoreProfile(): bool
    {
        return filled($this->last_name)
            && filled($this->phone)
            && filled($this->dob)
            && filled($this->gender)
            && filled($this->identity_number)
            && filled($this->identity_date)
            && filled($this->identity_place)
            && filled($this->identity_front_image)
            && filled($this->identity_back_image)
            && filled($this->address_province)
            && filled($this->address_ward);
    }

    /**
     * Extra fields required only when the candidate registers for an HSK exam.
     */
    public function hasCompletedHskProfile(): bool
    {
        return filled($this->chinese_years)
            && filled($this->nationality)
            && filled($this->mother_tongue);
    }
}
