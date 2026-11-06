<?php

use App\Support\Db\Fk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V2 bước 2 — khoá học & lớp học do quản trị viên quản lý, lớp có thời hạn, phân quyền giáo viên theo lớp / khoá.
 * Dữ liệu V1 (quyết định 5): giáo viên đang sở hữu khoá → được quyền sửa khoá; đang sở hữu lớp → được gán vào lớp với đủ quyền.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Các bước có kiểm tra tồn tại để chạy lại được nếu lần trước dừng giữa chừng (máy chủ không có khoá ngoại).
        if (! Schema::hasColumn('courses', 'created_by')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by')->nullable()->after('teacher_id');   // users.id (quản trị viên tạo)
            });
        }
        if (! Schema::hasColumn('courses', 'cover_image')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('cover_image')->nullable()->after('description');
            });
        }

        if (! Schema::hasTable('course_editors')) {
            Schema::create('course_editors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['course_id', 'teacher_id']);
            });
        }

        // Lớp không còn bị xoá theo giáo viên: teacher_id = "giáo viên chính" (tuỳ chọn), quyền thật nằm ở classroom_teacher.
        Fk::dropIfExists('classrooms', 'teacher_id');
        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedBigInteger('teacher_id')->nullable()->change();
            $table->foreign('teacher_id')->references('id')->on('teachers')->nullOnDelete();
            $table->dateTime('starts_at')->nullable()->after('description');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->boolean('allow_after_end')->default(false)->after('ends_at');           // chỉ chủ hệ thống bật
            $table->unsignedSmallInteger('grace_days')->nullable()->after('allow_after_end'); // null = không giới hạn khi đã cho phép
            $table->unsignedBigInteger('created_by')->nullable()->after('grace_days');
        });

        Schema::create('classroom_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_customize')->default(false);      // tuỳ chỉnh bài tập của lớp
            $table->boolean('can_add_students')->default(false);   // thêm / bớt học viên
            $table->boolean('can_reset_password')->default(true);  // cấp lại mật khẩu học viên trong lớp (quyết định 3)
            $table->timestamps();
            $table->unique(['classroom_id', 'teacher_id']);
        });

        $now = now();
        foreach (DB::table('classrooms')->whereNotNull('teacher_id')->get(['id', 'teacher_id']) as $c) {
            DB::table('classroom_teacher')->insertOrIgnore(['classroom_id' => $c->id, 'teacher_id' => $c->teacher_id,
                'can_customize' => true, 'can_add_students' => true, 'can_reset_password' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (DB::table('courses')->whereNotNull('teacher_id')->get(['id', 'teacher_id']) as $c) {
            DB::table('course_editors')->insertOrIgnore(['course_id' => $c->id, 'teacher_id' => $c->teacher_id, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_teacher');
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at', 'allow_after_end', 'grace_days', 'created_by']);
        });
        Schema::dropIfExists('course_editors');
        Schema::table('courses', fn (Blueprint $t) => $t->dropColumn(['created_by', 'cover_image']));
    }
};
