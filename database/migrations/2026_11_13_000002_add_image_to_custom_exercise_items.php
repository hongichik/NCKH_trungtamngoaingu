<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ảnh của một phần bài tập tuỳ chỉnh (tranh để nối, bản đồ, sơ đồ…) — học viên thấy ở phần ngữ liệu. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_exercise_items', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('audio_path');
        });
    }

    public function down(): void
    {
        Schema::table('custom_exercise_items', fn (Blueprint $t) => $t->dropColumn('image_path'));
    }
};
