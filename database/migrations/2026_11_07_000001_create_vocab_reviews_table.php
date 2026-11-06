<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Flashcard ôn từ vựng lặp lại ngắt quãng (kiểu SM-2): 1 dòng = 1 từ của 1 Unit với 1 học viên. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocab_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('textbook_units')->cascadeOnDelete();
            $table->string('word_key', 40);                   // sha1 của từ (chữ thường)
            $table->string('word', 120);
            $table->decimal('ease', 4, 2)->default(2.5);      // hệ số dễ (SM-2)
            $table->unsignedInteger('interval_days')->default(0);
            $table->unsignedSmallInteger('reps')->default(0);   // số lần nhớ liên tiếp
            $table->unsignedSmallInteger('lapses')->default(0); // số lần quên
            $table->timestamp('due_at');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'unit_id', 'word_key']);
            $table->index(['student_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vocab_reviews');
    }
};
