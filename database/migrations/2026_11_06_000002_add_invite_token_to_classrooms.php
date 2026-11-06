<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Vào lớp bằng LINK MỜI (thay cho nhập mã lớp). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('invite_token', 40)->nullable()->unique()->after('code');
        });
        foreach (DB::table('classrooms')->pluck('id') as $id) {
            DB::table('classrooms')->where('id', $id)->update(['invite_token' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('classrooms', fn (Blueprint $t) => $t->dropColumn('invite_token'));
    }
};
