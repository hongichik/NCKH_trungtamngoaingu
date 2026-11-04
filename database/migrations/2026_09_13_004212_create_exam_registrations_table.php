<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('registration_code')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_level_id')->constrained()->cascadeOnDelete();
            $table->string('exam_type'); // denormalized copy for simple filtering/reporting
            $table->unsignedInteger('price'); // snapshot of the level price at registration time
            $table->string('status')->default('pending_payment'); // pending_payment | paid | cancelled
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'exam_session_id', 'exam_level_id'], 'exam_registrations_unique_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_registrations');
    }
};
