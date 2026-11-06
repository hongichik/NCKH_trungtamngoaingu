<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** GĐ7: điểm kinh nghiệm, chuỗi ngày học, huy hiệu, sổ lỗi sai, bài luyện riêng (AI), bảng xếp hạng lớp, đề thi thử. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedSmallInteger('streak')->default(0);       // số ngày học liên tiếp
            $table->unsignedSmallInteger('best_streak')->default(0);
            $table->date('last_active_on')->nullable();
            $table->unsignedSmallInteger('daily_goal')->default(30); // XP mỗi ngày
            $table->timestamps();
        });

        Schema::create('xp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);          // test | vocab | drill | hanzi | mistake | practice
            $table->smallInteger('points');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['student_id', 'created_at']);
        });

        Schema::create('student_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('ref', 40)->default('');   // vd. id Unit với huy hiệu "Chinh phục Unit"
            $table->timestamp('earned_at')->useCurrent();
            $table->unique(['student_id', 'code', 'ref']);
        });

        Schema::create('mistake_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_set_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->unsignedSmallInteger('tries')->default(0);
            $table->timestamp('mastered_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'exercise_set_id', 'code']);
        });

        Schema::create('practice_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('language', 5)->default('en');
            $table->string('status', 20)->default('generating'); // generating | ready | done | failed
            $table->json('source');                 // các câu sai làm gốc
            $table->json('questions')->nullable();
            $table->json('result')->nullable();
            $table->decimal('score', 4, 1)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::table('classrooms', fn (Blueprint $t) => $t->boolean('show_leaderboard')->default(false)->after('allow_join'));
        Schema::table('assignments', fn (Blueprint $t) => $t->string('kind', 10)->default('unit')->after('mode')); // unit | mock (đề thi thử)
    }

    public function down(): void
    {
        Schema::table('assignments', fn (Blueprint $t) => $t->dropColumn('kind'));
        Schema::table('classrooms', fn (Blueprint $t) => $t->dropColumn('show_leaderboard'));
        Schema::dropIfExists('practice_sets');
        Schema::dropIfExists('mistake_reviews');
        Schema::dropIfExists('student_badges');
        Schema::dropIfExists('xp_logs');
        Schema::dropIfExists('student_stats');
    }
};
