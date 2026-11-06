<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** V2 bước 5 — bài tập tuỳ chỉnh (dựng từ thư viện dạng bài), thư viện bài tập của khoá, giao cho lớp, bài làm. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('course_lessons')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('custom_exercises')->nullOnDelete(); // bản gốc khi nhân bản
            $table->foreignId('source_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete(); // tạo cho lớp nào
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('skill', 20)->default('mixed');
            $table->string('status', 20)->default('draft');      // draft | published | hidden
            $table->boolean('is_standard')->default(false);     // "bài chuẩn của khoá" (quản trị viên đánh dấu)
            $table->string('source', 20)->default('manual');    // manual | ai
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->json('scoring')->nullable();                // ghi đè cách tính điểm cho cả bài: points, partial, penalty, scale
            $table->string('created_by_type', 10);              // user | teacher
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'status']);
        });

        Schema::create('custom_exercise_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('title')->nullable();                // tên phần, vd "Part 1"
            $table->string('type_code', 60);                    // mã dạng bài trong thư viện
            $table->json('content');
            $table->json('scoring')->nullable();                // ghi đè cho phần này
            $table->string('audio_path')->nullable();           // file nghe của phần (đĩa local)
            $table->json('meta')->nullable();                   // AI: đoạn tài liệu gốc, chỗ chưa chắc…
            $table->timestamps();
        });

        Schema::create('classroom_custom_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('course_lessons')->nullOnDelete();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->unsignedSmallInteger('max_attempts')->nullable();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->string('show_answers', 20)->default('after_submit'); // after_submit | after_due | never
            $table->string('assigned_by_name')->nullable();
            $table->timestamps();
            $table->unique(['classroom_id', 'custom_exercise_id'], 'cce_class_exercise_unique');
        });

        Schema::create('custom_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_custom_exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->json('files')->nullable();                  // ghi âm: item_id => path
            $table->json('result')->nullable();                 // [item_id => {parts, score}]
            $table->decimal('earned', 8, 2)->default(0);
            $table->decimal('max', 8, 2)->default(0);
            $table->decimal('score', 4, 1)->nullable();         // thang 10
            $table->string('status', 20)->default('in_progress'); // in_progress | grading | graded
            $table->dateTime('started_at')->nullable();
            $table->dateTime('deadline_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->decimal('teacher_score', 4, 1)->nullable();
            $table->text('teacher_comment')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'classroom_custom_exercise_id'], 'custom_attempts_student_cce_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_attempts');
        Schema::dropIfExists('classroom_custom_exercises');
        Schema::dropIfExists('custom_exercise_items');
        Schema::dropIfExists('custom_exercises');
    }
};
