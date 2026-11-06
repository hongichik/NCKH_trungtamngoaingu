<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ôn từ vựng cho khoá học kiểu mới (Khoá học → Bài học → Bài tập):
 *  - course_lessons.vocabulary: danh sách từ của bài học [{word, pinyin, pos, meaning_vi, example}] (AI lập, giáo viên sửa);
 *  - vocab_reviews: thẻ ôn thuộc Unit giáo trình (unit_id) HOẶC bài học (lesson_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->json('vocabulary')->nullable()->after('content');
        });
        Schema::table('vocab_reviews', function (Blueprint $table) {
            $table->foreignId('lesson_id')->nullable()->after('unit_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->unique(['student_id', 'lesson_id', 'word_key']);
        });
        Schema::table('vocab_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vocab_reviews', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'lesson_id', 'word_key']);
            $table->dropConstrainedForeignId('lesson_id');
        });
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropColumn('vocabulary');
        });
    }
};
