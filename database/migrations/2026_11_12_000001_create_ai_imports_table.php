<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** V2 bước 6 — AI đọc file giáo viên chuẩn bị (Word / PDF / ảnh + file nghe) → bản thiết kế bài → dựng bài tập tuỳ chỉnh. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('custom_exercise_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            // uploading → ocr → planning → building → review → saved | failed
            $table->string('status', 20)->default('uploading');
            $table->string('source_name')->nullable();
            $table->longText('text')->nullable();          // chữ của tài liệu (đã ghép trang)
            $table->json('pages')->nullable();             // [{no, text, ocr: bool, image}]
            $table->json('audio')->nullable();             // [{id, name, path, size}]
            $table->json('plan')->nullable();              // lượt 1: các phần, dạng bài, file nghe, cách tính điểm
            $table->json('sections')->nullable();          // lượt 2: nội dung từng phần đã kiểm tra + cờ "chưa chắc"
            $table->text('error')->nullable();
            $table->string('created_by_type', 10);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_imports');
    }
};
