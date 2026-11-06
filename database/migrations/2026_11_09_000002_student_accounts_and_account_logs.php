<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** V2 bước 3 — tạo tài khoản học viên hàng loạt, bắt đổi mật khẩu lần đầu, nhật ký thao tác tài khoản. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        Schema::create('account_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 10);                 // user | teacher | student
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('action', 30);                     // create | reset_password | add_to_class | remove_from_class | change_password
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_logs');
        Schema::table('students', fn (Blueprint $t) => $t->dropColumn('must_change_password'));
    }
};
