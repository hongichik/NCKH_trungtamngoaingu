<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentBadge extends Model
{
    protected $table = 'student_badges';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['earned_at' => 'datetime'];
    }
}
