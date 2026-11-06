<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Một lần làm bài của học viên cho 1 Unit = đủ 4 kỹ năng làm lần lượt (Đọc → Nghe → Nói → Viết).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('textbook_units')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->json('sections');                         // [{skill, set_id, attempt_id}]
            $table->unsignedTinyInteger('current')->default(0); // phần đang làm
            $table->string('status', 20)->default('in_progress'); // in_progress | grading | completed | abandoned
            $table->json('skill_scores')->nullable();         // {reading: 8.5, ...}
            $table->decimal('total_score', 4, 1)->nullable();
            $table->string('level', 40)->nullable();          // Xuất sắc / Giỏi / Khá / Trung bình / Chưa đạt
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'unit_id']);
        });

        Schema::table('exercise_attempts', function (Blueprint $table) {
            $table->foreignId('unit_test_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
        });

        Schema::table('courses', function (Blueprint $table) {
            // Giáo viên chọn có cho học viên xem trước bài đọc của Unit hay không
            $table->boolean('show_reading_preview')->default(false)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $t) => $t->dropColumn('show_reading_preview'));
        Schema::table('exercise_attempts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('unit_test_id');
        });
        Schema::dropIfExists('unit_tests');
    }
};
