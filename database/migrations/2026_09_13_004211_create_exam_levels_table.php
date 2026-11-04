<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_levels', function (Blueprint $table) {
            $table->id();
            $table->string('exam_type'); // 'hsk' | 'cntt'
            $table->string('name');
            $table->unsignedInteger('price'); // VND, no decimals
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['exam_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_levels');
    }
};
