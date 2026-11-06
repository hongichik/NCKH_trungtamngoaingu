<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thư viện dạng bài (V2 bước 1). Định nghĩa dạng bài nằm trong code (App\Support\QuestionTypes\Catalog);
 * bảng này chỉ lưu bật / tắt và cấu hình ghi đè do chủ hệ thống chỉnh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_types', function (Blueprint $table) {
            $table->string('code', 60)->primary();
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();       // ghi đè cách tính điểm: points, partial, penalty, scale
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_types');
    }
};
