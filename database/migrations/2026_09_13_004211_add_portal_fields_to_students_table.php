<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Portal login (students self-register on the public site).
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');

            // Fields only ever required when the student registers for an HSK exam.
            $table->string('chinese_name')->nullable()->after('last_name');
            $table->string('religion')->nullable()->after('ethnic');
            $table->string('nationality')->nullable()->after('religion');
            $table->string('mother_tongue')->nullable()->after('nationality');
            $table->unsignedTinyInteger('chinese_years')->nullable()->after('mother_tongue');

            // Permanent address, used by both exam types.
            $table->string('address_province')->nullable()->after('identity_place');
            $table->string('address_district')->nullable()->after('address_province');
            $table->string('address_ward')->nullable()->after('address_district');
            $table->string('address_detail')->nullable()->after('address_ward');

            // Document photos required to complete the candidate profile.
            $table->string('identity_front_image')->nullable()->after('avatar');
            $table->string('identity_back_image')->nullable()->after('identity_front_image');
        });

        // A student only becomes a portal account once they set a password;
        // legacy/admin-imported rows keep email nullable & non-unique-checked
        // until then, but two portal accounts must never share an email.
        Schema::table('students', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn([
                'password', 'remember_token',
                'chinese_name', 'religion', 'nationality', 'mother_tongue', 'chinese_years',
                'address_province', 'address_district', 'address_ward', 'address_detail',
                'identity_front_image', 'identity_back_image',
            ]);
        });
    }
};
