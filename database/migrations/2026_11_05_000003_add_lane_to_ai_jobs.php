<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Làn" của việc nền: mỗi giáo viên một làn (teacher:ID). Việc trong cùng làn chạy lần lượt
 * theo thứ tự gửi; các làn khác nhau chạy song song / xoay vòng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->string('lane', 50)->default('system')->after('batch');
            $table->index(['status', 'lane', 'id']);
        });

        // Gán làn cho các việc đang có (theo teacher_id trong payload)
        DB::table('ai_jobs')->whereIn('status', ['pending', 'running'])->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $teacher = json_decode((string) $row->payload, true)['teacher_id'] ?? null;
                if ($teacher) {
                    DB::table('ai_jobs')->where('id', $row->id)->update(['lane' => 'teacher:'.$teacher]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->dropIndex(['status', 'lane', 'id']);
            $table->dropColumn('lane');
        });
    }
};
