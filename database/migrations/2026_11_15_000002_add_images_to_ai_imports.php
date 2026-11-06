<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ảnh nhúng trong file Word của tài liệu AI dựng bài: [{n, path}] ứng với dấu "[Hình N]" trong chữ. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_imports', function (Blueprint $table) {
            $table->json('images')->nullable()->after('audio');
        });
    }

    public function down(): void
    {
        Schema::table('ai_imports', fn (Blueprint $t) => $t->dropColumn('images'));
    }
};
