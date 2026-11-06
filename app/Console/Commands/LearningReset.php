<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Xoá sạch dữ liệu học tập (khoá học, giáo trình, bài học, bài tập, lớp, bài làm, tiến độ, học viên demo) để làm lại từ đầu.
 * KHÔNG xoá học viên thật, giáo viên, tài khoản quản trị, nội dung website, đăng ký thi.
 * Sao lưu JSON các bảng trước khi xoá vào storage/app/private/backups/.
 */
class LearningReset extends Command
{
    protected $signature = 'learning:reset {--force : Không hỏi lại} {--no-backup : Không sao lưu}';

    protected $description = 'Xoá sạch dữ liệu khoá học / giáo trình / lớp / bài làm / học viên demo (giữ học viên thật)';

    /** Thứ tự xoá: bảng con trước. */
    public const TABLES = [
        'custom_attempts', 'classroom_custom_exercises', 'custom_exercise_items', 'custom_exercises', 'ai_imports',
        'video_progress', 'lesson_materials', 'lesson_videos', 'classroom_lessons', 'course_lessons',
        'exercise_attempts', 'unit_tests', 'assignments', 'questions', 'exercise_sets',
        'practice_sets', 'mistake_reviews', 'vocab_reviews', 'xp_logs', 'student_badges', 'student_stats', 'account_logs',
        'classroom_student', 'classroom_teacher', 'classrooms', 'course_editors', 'courses',
        'unit_profiles', 'textbook_chunks', 'audio_tracks', 'textbook_units', 'textbook_pages', 'textbooks',
    ];

    public const DIRS = ['courses', 'textbooks', 'custom-exercises', 'ai-imports', 'recordings'];

    public function handle(): int
    {
        $tables = array_values(array_filter(self::TABLES, fn ($t) => Schema::hasTable($t)));
        foreach ($tables as $t) {
            $this->line(sprintf('  %-28s %d dòng', $t, DB::table($t)->count()));
        }
        $demo = DB::table('students')->where('email', 'like', '%@demo.local')->count();
        $this->line("  học viên demo (@demo.local)   {$demo}");
        if (! $this->option('force') && ! $this->confirm('Xoá toàn bộ dữ liệu trên?')) {
            return self::FAILURE;
        }

        if (! $this->option('no-backup')) {
            $dir = 'backups/learning-reset-'.now()->format('Ymd-His');
            foreach ($tables as $t) {
                Storage::disk('local')->put("{$dir}/{$t}.json", DB::table($t)->get()->toJson(JSON_UNESCAPED_UNICODE));
            }
            Storage::disk('local')->put("{$dir}/students-demo.json", DB::table('students')->where('email', 'like', '%@demo.local')->get()->toJson(JSON_UNESCAPED_UNICODE));
            $this->info('Đã sao lưu vào storage/app/private/'.$dir);
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $t) {
                DB::table($t)->delete();
            }
            DB::table('students')->where('email', 'like', '%@demo.local')->delete();
            DB::table('ai_jobs')->whereIn('status', ['pending', 'running', 'failed'])->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        foreach (self::DIRS as $d) {
            Storage::disk('local')->deleteDirectory($d);
        }
        $this->info('Đã xoá sạch dữ liệu học tập. Học viên thật, giáo viên, quản trị viên được giữ nguyên.');

        return self::SUCCESS;
    }
}
