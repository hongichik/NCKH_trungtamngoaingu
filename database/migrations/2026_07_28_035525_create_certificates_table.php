<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('numbering')->nullable();
            $table->date('exam_date')->nullable();
            $table->string('exam_place')->nullable();
            $table->string('exam_room')->nullable();
            $table->string('practice_score')->nullable();
            $table->string('theoretical_score')->nullable();
            $table->text('scores')->nullable();
            $table->string('classification')->nullable();
            $table->string('certificate_type')->nullable();
            $table->string('serial_number')->nullable()->index();
            $table->string('certificate_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->string('issue_place')->nullable();
            $table->string('status')->default('published');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
