<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mỗi lượt gọi AI một dòng: để tính hạn mức và chi phí.
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 20)->nullable(); // user | student | null (hệ thống)
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('feature', 30);                // analysis | generate | grading | ocr | transcribe | tts | test
            $table->string('model', 80);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('units', 12, 2)->default(0);  // giây âm thanh / số ký tự giọng đọc
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->boolean('success')->default(true);
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index('created_at');
        });

        // Hàng đợi việc nền (chạy bằng cron cPanel hoặc trang tiến độ tự kéo).
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('batch', 100)->nullable();     // nhóm việc, vd "textbook:12"
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending'); // pending | running | done | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->json('result')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index('batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_jobs');
        Schema::dropIfExists('ai_usage');
    }
};
