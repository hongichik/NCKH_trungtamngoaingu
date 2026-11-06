<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentStat extends Model
{
    protected $table = 'student_stats';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_active_on' => 'date'];
    }
}
