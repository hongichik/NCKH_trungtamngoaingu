<?php

use App\Support\Db\Fk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mô hình chốt: Khoá học → Bài học → Bài tập.
 *  - Giáo trình thuộc đúng 1 khoá (courses.textbook_id unique lại), không dùng chung.
 *  - Bài tập luôn nằm trong bài học: xoá bài học thì xoá bài tập của nó.
 *  - AI dựng bài tập gắn với bài học: từ file giáo viên (extract) hoặc từ Unit giáo trình (generate).
 *  - Bỏ "chương trình từ file Word" (chỉ tạo bài rỗng có ghi chú).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('program_imports');

        Fk::dropIfExists('courses', 'textbook_id');
        Schema::table('courses', function (Blueprint $table) {
            $table->unique('textbook_id');
            $table->foreign('textbook_id')->references('id')->on('textbooks')->nullOnDelete();
        });

        Fk::dropIfExists('custom_exercises', 'lesson_id');
        Schema::table('custom_exercises', function (Blueprint $table) {
            $table->foreign('lesson_id')->references('id')->on('course_lessons')->cascadeOnDelete();
        });

        Schema::table('ai_imports', function (Blueprint $table) {
            $table->foreignId('lesson_id')->nullable()->after('course_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->after('lesson_id')->constrained('textbook_units')->nullOnDelete();
            $table->string('mode', 10)->default('extract')->after('status'); // extract: file giáo viên | generate: từ Unit giáo trình
        });
    }

    public function down(): void
    {
        Schema::table('ai_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lesson_id');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('mode');
        });
    }
};
