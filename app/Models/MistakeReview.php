<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MistakeReview extends Model
{
    protected $table = 'mistake_reviews';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['mastered_at' => 'datetime'];
    }
}
