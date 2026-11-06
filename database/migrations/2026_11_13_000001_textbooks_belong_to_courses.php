<?php

use App\Support\Db\Fk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giáo trình được tải theo khoá học (quản trị viên, hoặc giáo viên được cấp quyền sửa khoá).
 * Một giáo trình có thể dùng chung cho nhiều khoá → bỏ ràng buộc unique của courses.textbook_id.
 * Thêm phiên "tạo chương trình học từ file Word hướng dẫn" (AI đọc → danh sách bài học).
 */
return new class extends Migration
{
    public function up(): void
    {
        Fk::dropIfExists('courses', 'textbook_id');
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['textbook_id']);
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->foreign('textbook_id')->references('id')->on('textbooks')->nullOnDelete();
        });

        Schema::table('textbooks', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('teacher_id'); // users.id khi quản trị viên tải
        });

        Schema::create('program_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('analyzing'); // analyzing → review → applied | failed
            $table->string('source_name')->nullable();
            $table->longText('text')->nullable();
            $table->json('result')->nullable();                 // {notes, lessons: [{title, summary, content, unit_id}]}
            $table->text('error')->nullable();
            $table->string('created_by_type', 10);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_imports');
        Schema::table('textbooks', fn (Blueprint $t) => $t->dropColumn('created_by'));
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['textbook_id']);
            $table->unique('textbook_id');
            $table->foreign('textbook_id')->references('id')->on('textbooks')->nullOnDelete();
        });
    }
};
