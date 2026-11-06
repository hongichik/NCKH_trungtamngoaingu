<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Khoá học cho học viên — xuất bản từ một giáo trình (mỗi Unit có hồ sơ = 1 bài học).
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('textbook_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('language', 5);
            $table->string('title');
            $table->string('level', 30)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        // Bộ bài tập (Đọc / Nghe) của một Unit.
        Schema::create('exercise_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('textbook_units')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('skill', 20);                  // reading | listening
            $table->string('title');
            // generating → draft → published | failed
            $table->string('status', 20)->default('generating');
            $table->string('source', 20);                 // textbook (nguyên văn giáo trình) | ai (AI tự viết) | audio (lời chép audio)
            $table->json('data')->nullable();             // passage, cloze_passage | speakers, script
            $table->json('matrix')->nullable();
            $table->json('warnings')->nullable();
            $table->string('audio_mode', 20)->nullable(); // textbook | tts | browser (bài Nghe)
            $table->foreignId('audio_track_id')->nullable()->constrained('audio_tracks')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Ngân hàng câu hỏi: mỗi câu thuộc một bộ bài tập (gắn Unit, kỹ năng, mức độ qua bộ).
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_set_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('code', 10);                   // R1, L3...
            $table->string('type', 20);                   // mcq | true_false | cloze | ordering | gap_fill
            $table->string('level', 20)->nullable();      // recognition | comprehension | application
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->string('answer', 500);
            $table->text('evidence')->nullable();
            $table->text('explanation')->nullable();
            $table->json('issues')->nullable();           // lỗi kiểm định còn lại → "cần xem"
            $table->timestamps();
        });

        // Lượt làm bài của học viên (tự luyện).
        Schema::create('exercise_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->json('result');
            $table->unsignedInteger('correct');
            $table->unsignedInteger('total');
            $table->decimal('score', 4, 1);               // thang 10
            $table->timestamps();

            $table->index(['student_id', 'exercise_set_id']);
        });

        // Bộ nhớ đệm giọng đọc AI theo từng câu (cùng nội dung + giọng + ngôn ngữ → dùng lại mãi).
        Schema::create('tts_cache', function (Blueprint $table) {
            $table->id();
            $table->char('hash', 64)->unique();
            $table->string('language', 5);
            $table->string('voice', 40);
            $table->text('text');
            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tts_cache');
        Schema::dropIfExists('exercise_attempts');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('exercise_sets');
        Schema::dropIfExists('courses');
    }
};
