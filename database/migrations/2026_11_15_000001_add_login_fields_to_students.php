<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quản lý tài khoản học viên: khoá đăng nhập, lần đăng nhập cuối. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('login_locked')->default(false)->after('must_change_password');
            $table->timestamp('last_login_at')->nullable()->after('login_locked');
        });
    }

    public function down(): void
    {
        Schema::table('students', fn (Blueprint $t) => $t->dropColumn(['login_locked', 'last_login_at']));
    }
};
