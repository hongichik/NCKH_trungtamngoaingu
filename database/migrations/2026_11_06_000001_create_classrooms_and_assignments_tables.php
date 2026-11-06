<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lớp học, học viên trong lớp, bài giao cho lớp; giáo viên sửa điểm bài làm. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 12)->unique();           // mã để học viên tự tham gia
            $table->text('description')->nullable();
            $table->boolean('allow_join')->default(true);  // cho phép tham gia bằng mã
            $table->timestamps();
        });

        Schema::create('classroom_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['classroom_id', 'student_id']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('textbook_units')->cascadeOnDelete();
            $table->string('title');
            $table->string('mode', 20)->default('practice');   // practice | test
            $table->json('sections');                          // [{skill, set_id}] — chốt bộ đề lúc giao
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->unsignedSmallInteger('max_attempts')->nullable();       // null = không giới hạn
            $table->unsignedSmallInteger('time_limit_minutes')->nullable(); // null = không giới hạn
            $table->unsignedTinyInteger('max_listens')->nullable();         // số lần nghe tối đa (bài Nghe)
            $table->string('show_answers', 20)->default('after_submit');    // after_submit | after_due
            $table->timestamps();
        });

        Schema::table('unit_tests', function (Blueprint $table) {
            $table->foreignId('assignment_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
            $table->timestamp('deadline_at')->nullable()->after('status'); // hết giờ làm (chế độ kiểm tra)
        });

        Schema::table('exercise_attempts', function (Blueprint $table) {
            // Giáo viên chấm lại: điểm sửa thay cho điểm máy chấm
            $table->decimal('teacher_score', 4, 1)->nullable()->after('score');
            $table->text('teacher_comment')->nullable()->after('teacher_score');
            $table->timestamp('reviewed_at')->nullable()->after('teacher_comment');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_attempts', fn (Blueprint $t) => $t->dropColumn(['teacher_score', 'teacher_comment', 'reviewed_at']));
        Schema::table('unit_tests', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assignment_id');
            $t->dropColumn('deadline_at');
        });
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('classroom_student');
        Schema::dropIfExists('classrooms');
    }
};
