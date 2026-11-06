<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bật / tắt + ghi đè cấu hình của một dạng bài trong thư viện (định nghĩa dạng bài nằm trong code). */
class QuestionTypeSetting extends Model
{
    protected $table = 'question_types';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'settings' => 'array'];
    }
}
