<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thêm dữ liệu cho bài Nói / Viết (nhiệm vụ, gợi ý, bài mẫu) và chấm bài chạy nền. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Nói: task_type, ideas_vi, useful_language, sample_answer, time_seconds
            // Viết: start, accepted_variants (viết lại câu); min_words, max_words, ideas_vi, sample_answer (đoạn văn)
            $table->json('meta')->nullable()->after('explanation');
        });

        Schema::table('exercise_attempts', function (Blueprint $table) {
            $table->string('status', 20)->default('graded')->after('score'); // grading | graded | failed
            $table->text('error')->nullable()->after('status');
            $table->json('files')->nullable()->after('answers');             // ghi âm bài Nói: code => đường dẫn
        });
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $t) => $t->dropColumn('meta'));
        Schema::table('exercise_attempts', fn (Blueprint $t) => $t->dropColumn(['status', 'error', 'files']));
    }
};
