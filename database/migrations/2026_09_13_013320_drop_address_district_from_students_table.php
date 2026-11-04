<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vietnam's July 2025 administrative reform abolished the district level
     * entirely (63 provinces -> 34, and a 2-tier Province -> Ward structure).
     * The candidate address form no longer collects this field.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('address_district');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('address_district')->nullable()->after('address_province');
        });
    }
};
