<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** V2 bước 4 — bài học trong khoá, video hướng dẫn (chống tải lậu), tài liệu, tiến độ xem, lịch mở bài theo lớp. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('textbook_units')->nullOnDelete(); // gắn Unit giáo trình (nếu có)
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();          // ghi chú bài học: từ vựng, ngữ pháp… (chữ thường, xuống dòng)
            $table->unsignedInteger('position')->default(0);
            $table->boolean('require_video')->default(false); // phải xem xong video mới mở bài tập
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->index(['course_id', 'position']);
        });

        Schema::create('lesson_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->string('title');
            $table->string('provider', 20)->default('local');  // local (MP4 phát theo đoạn) | hls (gói HLS mã hoá AES-128) | bunny
            $table->string('path')->nullable();                // local: file mp4; hls: thư mục chứa playlist (đĩa local, ngoài public)
            $table->string('external_id')->nullable();         // bunny: GUID video
            $table->string('captions_path')->nullable();       // phụ đề .vtt
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('lesson_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->string('title');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('video_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('watched_seconds')->default(0); // cộng dồn thời gian thật sự xem (giới hạn mỗi lần báo)
            $table->unsignedInteger('last_position')->default(0);   // để xem tiếp
            $table->unsignedTinyInteger('percent')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['lesson_video_id', 'student_id']);
        });

        // Lịch mở bài theo lớp: không có dòng = mở theo mặc định (ngay); hidden = ẩn với lớp này.
        Schema::create('classroom_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->dateTime('opens_at')->nullable();
            $table->boolean('hidden')->default(false);
            $table->timestamps();
            $table->unique(['classroom_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_lessons');
        Schema::dropIfExists('video_progress');
        Schema::dropIfExists('lesson_materials');
        Schema::dropIfExists('lesson_videos');
        Schema::dropIfExists('course_lessons');
    }
};
