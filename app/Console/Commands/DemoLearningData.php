<?php

namespace App\Console\Commands;

use App\Models\Classroom;
use App\Models\ClassroomCustomExercise;
use App\Models\Course;
use App\Models\CustomAttempt;
use App\Models\CustomExercise;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\XpLog;
use App\Support\CustomExercise\CustomExerciseService;
use App\Support\QuestionTypes\Registry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu mẫu theo mô hình Khoá học → Bài học → Bài tập: 2 khoá (Anh, Trung) tự tạo, mỗi bài học có bài tập
 * dựng từ thư viện dạng bài, 2 lớp có thời hạn, học viên ảo đã làm vài bài. Chạy lại nhiều lần được (xoá bản mẫu cũ trước).
 */
class DemoLearningData extends Command
{
    protected $signature = 'demo:learning-data
        {--student= : Email học viên dùng thử (bắt buộc)}
        {--teacher= : Email giáo viên (mặc định: giáo viên đầu tiên)}';

    protected $description = 'Tạo dữ liệu mẫu khoá học → bài học → bài tập, lớp học, bài làm để dùng thử';

    private const PREFIX = '[Mẫu] ';

    /** Bài học mẫu: tên => dạng bài của từng bài tập. */
    private const EN_LESSONS = [
        'Bài 1 — Family' => ['Từ vựng & ngữ pháp' => ['general.match_pairs', 'general.gap_bank', 'general.mcq_single'], 'Đọc hiểu' => ['ielts.reading.tfng', 'ielts.reading.matching_headings'], 'Viết' => ['general.paragraph']],
        'Bài 2 — Daily routines' => ['Nghe' => ['general.listen_gap', 'ielts.listening.mcq'], 'Ngữ pháp' => ['general.word_order', 'general.error_correction']],
        'Bài 3 — Travel' => ['Đọc hiểu' => ['ielts.reading.sentence_completion', 'ielts.reading.short_answer'], 'Nói' => ['general.speak_answer']],
    ];

    private const ZH_LESSONS = [
        '第一课 你好' => ['Phát âm & chữ' => ['zh.tone_choice', 'zh.pinyin_to_hanzi', 'zh.stroke_order']],
        '第二课 我的家' => ['Đọc & viết' => ['hsk.fill_word', 'hsk.word_order', 'hsk.reading_mcq']],
    ];

    public function handle(CustomExerciseService $service): int
    {
        $teacher = $this->option('teacher') ? Teacher::where('email', $this->option('teacher'))->first() : Teacher::orderBy('id')->first();
        $student = Student::where('email', (string) $this->option('student'))->first();
        if (! $teacher || ! $student) {
            $this->error('Không tìm thấy giáo viên hoặc học viên (cần --student=email).');

            return self::FAILURE;
        }

        DB::transaction(function () use ($teacher, $student, $service) {
            $this->cleanup($student);

            $en = $this->course('English A2 — Everyday Life', 'en', 'A2', self::EN_LESSONS, $teacher);
            $zh = $this->course('HSK 1 — Tiếng Trung sơ cấp', 'zh', 'HSK 1', self::ZH_LESSONS, $teacher);

            $enClass = Classroom::create(['teacher_id' => $teacher->id, 'course_id' => $en->id, 'name' => self::PREFIX.'Tiếng Anh A2 — tối 2-4-6', 'description' => 'Lớp mẫu để dùng thử',
                'show_leaderboard' => true, 'starts_at' => now()->subDays(14)->setTime(18, 0), 'ends_at' => now()->addMonths(3)->setTime(21, 0)]);
            $zhClass = Classroom::create(['teacher_id' => $teacher->id, 'course_id' => $zh->id, 'name' => self::PREFIX.'HSK 1 — sáng thứ 7', 'description' => 'Lớp mẫu tiếng Trung',
                'starts_at' => now()->subDays(7)->setTime(8, 0), 'ends_at' => now()->addMonths(2)->setTime(11, 0)]);
            $enClass->students()->attach($student->id);
            $zhClass->students()->attach($student->id);

            // Học viên ảo đã làm bài tập đầu tiên của bài 1 (bảng điểm, xếp hạng có dữ liệu)
            $peers = collect([['Trần', 'Minh Khoa'], ['Lê', 'Thu Hà'], ['Phạm', 'Gia Bảo']])->map(fn ($n, $i) => Student::forceCreate([
                'last_name' => $n[0].' '.$n[1], 'email' => 'hocvien'.($i + 1).'@demo.local', 'password' => bcrypt(str()->random(24)),
            ]));
            $enClass->students()->syncWithoutDetaching($peers->pluck('id'));
            $first = $en->lessons()->first()->exercises()->with('items')->first();
            foreach ($peers as $n => $peer) {
                $cce = ClassroomCustomExercise::firstOrCreate(['classroom_id' => $enClass->id, 'custom_exercise_id' => $first->id], ['lesson_id' => $first->lesson_id]);
                $attempt = CustomAttempt::create(['classroom_custom_exercise_id' => $cce->id, 'student_id' => $peer->id, 'status' => 'in_progress', 'started_at' => now()->subHours(3 + $n)]);
                $answers = $first->items->mapWithKeys(fn ($item) => [$item->id => $n === 0 ? $item->type()->engine()->perfectAnswer($item->content, $item->type()) : []])->all();
                $service->submit($attempt, $answers);
            }
        });

        $this->info("Đã tạo dữ liệu mẫu cho giáo viên {$teacher->email} và học viên {$student->email}.");

        return self::SUCCESS;
    }

    private function course(string $title, string $lang, string $level, array $lessons, Teacher $teacher): Course
    {
        $course = Course::create(['language' => $lang, 'level' => $level, 'title' => self::PREFIX.$title, 'is_published' => true,
            'description' => 'Khoá học mẫu: mỗi bài học có bài tập dựng từ Thư viện dạng bài.']);
        $course->editors()->attach($teacher->id);
        $pos = 0;
        foreach ($lessons as $lessonTitle => $exercises) {
            $lesson = $course->lessons()->create(['title' => $lessonTitle, 'position' => ++$pos, 'summary' => 'Bài học mẫu — học qua các bài tập bên dưới.']);
            foreach ($exercises as $exTitle => $codes) {
                $ex = CustomExercise::create(['course_id' => $course->id, 'lesson_id' => $lesson->id, 'title' => $exTitle, 'skill' => 'mixed', 'status' => 'published',
                    'created_by_type' => 'teacher', 'created_by_id' => $teacher->id, 'created_by_name' => $teacher->name]);
                foreach ($codes as $i => $code) {
                    $ex->items()->create(['position' => $i + 1, 'type_code' => $code, 'content' => Registry::get($code)->sample]);
                }
            }
        }

        return $course;
    }

    private function cleanup(Student $student): void
    {
        Classroom::where('name', 'like', self::PREFIX.'%')->get()->each->delete();
        Course::where('title', 'like', self::PREFIX.'%')->get()->each->delete();   // xoá dây chuyền bài học, bài tập, bài làm
        XpLog::where('student_id', $student->id)->delete();
        Student::where('email', 'like', '%@demo.local')->get()->each(function ($s) {
            XpLog::where('student_id', $s->id)->delete();
            $s->delete();
        });
    }
}
