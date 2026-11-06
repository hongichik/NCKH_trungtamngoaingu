<?php

namespace App\Console\Commands;

use App\Models\AiImport;
use App\Models\Classroom;
use App\Models\ClassroomCustomExercise;
use App\Models\Course;
use App\Models\CustomAttempt;
use App\Models\CustomExercise;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\XpLog;
use App\Support\Ai\Jobs\AiJobRunner;
use App\Support\AiImport\AiImportService;
use App\Support\CustomExercise\CustomExerciseService;
use App\Support\CustomExercise\ItemAiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Dữ liệu demo "chuẩn chỉ" cho bộ video hướng dẫn (tools/tutorial-videos):
 *  - 2 khoá học thật: Tiếng Anh giao tiếp A2, Tiếng Trung HSK 1 — mỗi bài học có tóm tắt + nội dung (từ vựng, ngữ pháp, hội thoại);
 *  - bài tập của từng bài học do AI của hệ thống tự tạo theo đúng nội dung bài (đọc, nghe, từ vựng & ngữ pháp, viết, nói), bài nghe có file giọng đọc AI;
 *  - tài khoản quản trị viên, giáo viên, học viên, thí sinh (email @tutorial.local), 2 lớp có học viên và bài làm mẫu.
 * Các id đã tạo lưu ở storage/app/tutorial-demo.json; "tutorial:demo cleanup" xoá sạch.
 */
class TutorialDemo extends Command
{
    protected $signature = 'tutorial:demo {action=setup : setup | cleanup | info} {--no-ai : không gọi AI tạo bài tập (chỉ tạo khoá, bài học, tài khoản)}';

    protected $description = 'Tạo / xoá dữ liệu demo (khoá học Anh, Trung + tài khoản) để quay video hướng dẫn';

    public const PASSWORD = 'Demo@12345';

    public const DOMAIN = '@tutorial.local';

    private const STATE = 'tutorial-demo.json';

    /** Số câu hỏi lớn AI tạo cho mỗi bài học, theo kỹ năng. */
    private const COUNTS = [
        'en' => ['vocab' => 2, 'reading' => 1, 'listening' => 1, 'writing' => 1, 'speaking' => 1],
        'zh' => ['vocab' => 2, 'reading' => 1, 'listening' => 1, 'speaking' => 1],
    ];

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'cleanup' => $this->cleanup(),
            'info' => $this->info_(),
            default => $this->setup(),
        };
    }

    /** JSON các id / đường dẫn của dữ liệu demo (cho kịch bản quay video). */
    private function info_(): int
    {
        $state = json_decode((string) (Storage::disk('local')->exists(self::STATE) ? Storage::disk('local')->get(self::STATE) : '{}'), true) ?: [];
        $out = ['password' => self::PASSWORD, 'domain' => self::DOMAIN, 'courses' => [], 'classrooms' => []];
        foreach (Course::whereIn('id', $state['courses'] ?? [])->with('lessons')->get() as $c) {
            $out['courses'][$c->language] = ['id' => $c->id, 'title' => $c->title, 'lessons' => $c->lessons->map(fn ($l) => ['id' => $l->id, 'title' => $l->title,
                'exercises' => CustomExercise::where('lesson_id', $l->id)->pluck('id')->all()])->all()];
        }
        foreach (Classroom::whereIn('id', $state['classrooms'] ?? [])->get() as $k) {
            $out['classrooms'][$k->course?->language ?? 'x'] = ['id' => $k->id, 'name' => $k->name];
        }
        $this->line(json_encode($out, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function setup(): int
    {
        $this->cleanup(quiet: true);
        $pw = bcrypt(self::PASSWORD);
        $state = [];

        $admin = User::forceCreate(['name' => 'Nguyễn Thị Hương', 'email' => 'demo.admin'.self::DOMAIN, 'password' => $pw, 'is_admin' => true]);
        $teacher = Teacher::forceCreate(['name' => 'Thầy Nguyễn Văn Minh', 'email' => 'demo.giaovien'.self::DOMAIN, 'phone' => '0912000111', 'password' => $pw, 'is_active' => true]);
        $zhTeacher = Teacher::forceCreate(['name' => 'Cô Vương Lệ Hoa', 'email' => 'demo.giaovien2'.self::DOMAIN, 'phone' => '0912000112', 'password' => $pw, 'is_active' => true]);

        $en = $this->course(self::english(), 'en', $admin);
        $zh = $this->course(self::chinese(), 'zh', $admin);
        $en->editors()->syncWithoutDetaching([$teacher->id]);
        $zh->editors()->syncWithoutDetaching([$zhTeacher->id, $teacher->id]);
        $state['courses'] = [$en->id, $zh->id];

        $enClass = Classroom::create(['course_id' => $en->id, 'teacher_id' => $teacher->id, 'name' => 'Giao tiếp A2 — tối 2-4-6', 'description' => 'Lớp tiếng Anh giao tiếp buổi tối, 18:00–19:30',
            'show_leaderboard' => true, 'starts_at' => now()->subDays(14)->setTime(18, 0), 'ends_at' => now()->addMonths(3)->setTime(19, 30)]);
        $zhClass = Classroom::create(['course_id' => $zh->id, 'teacher_id' => $zhTeacher->id, 'name' => 'HSK 1 — sáng thứ 7', 'description' => 'Lớp tiếng Trung sơ cấp sáng thứ Bảy, 8:00–10:00',
            'show_leaderboard' => true, 'starts_at' => now()->subDays(7)->setTime(8, 0), 'ends_at' => now()->addMonths(2)->setTime(10, 0)]);
        $enClass->teachers()->syncWithoutDetaching([$teacher->id => ['can_customize' => true, 'can_add_students' => true, 'can_reset_password' => true]]);
        $zhClass->teachers()->syncWithoutDetaching([$zhTeacher->id => ['can_customize' => true, 'can_add_students' => true, 'can_reset_password' => true],
            $teacher->id => ['can_customize' => false, 'can_add_students' => false, 'can_reset_password' => false]]);
        $state['classrooms'] = [$enClass->id, $zhClass->id];

        $profile = fn (string $gender, string $phone, string $dob) => ['gender' => $gender, 'phone' => $phone, 'dob' => $dob, 'identity_number' => '0013040'.substr($phone, -5),
            'identity_date' => '2021-06-01', 'identity_place' => Student::IDENTITY_PLACE_OPTIONS[0], 'address_province' => 'Thành phố Hà Nội', 'address_ward' => 'Phường Cầu Giấy',
            'address_detail' => 'Số 1 Xuân Thuỷ', 'nationality' => 'Việt Nam', 'mother_tongue' => 'Tiếng Việt', 'must_change_password' => false, 'password' => $pw];
        $learner = Student::forceCreate(['last_name' => 'Trần Thị Lan', 'email' => 'demo.hocvien'.self::DOMAIN] + $profile('Nữ', '0987000222', '2004-05-12'));
        $enClass->students()->attach($learner->id);
        $zhClass->students()->attach($learner->id);
        $peers = collect([['Phạm Quốc Huy', 'Nam'], ['Đỗ Minh Anh', 'Nữ'], ['Vũ Thu Trang', 'Nữ'], ['Hoàng Đức Long', 'Nam'], ['Ngô Bảo Ngọc', 'Nữ']])
            ->map(fn ($p, $i) => Student::forceCreate(['last_name' => $p[0], 'email' => 'demo.hv'.($i + 1).self::DOMAIN] + $profile($p[1], '098700030'.$i, '200'.(3 + $i % 3).'-0'.(3 + $i).'-1'.$i)));
        $enClass->students()->attach($peers->pluck('id'));
        $zhClass->students()->attach($peers->take(3)->pluck('id'));
        // Thí sinh đăng ký thi: chưa vào lớp nào, hồ sơ mới có thông tin cơ bản (để thấy bước "Bổ sung hồ sơ").
        Student::forceCreate(['last_name' => 'Lê Hoàng Nam', 'email' => 'demo.thisinh'.self::DOMAIN, 'phone' => '0903000333', 'gender' => 'Nam', 'must_change_password' => false, 'password' => $pw]);
        $this->saveState($state);
        $this->info('Đã tạo khoá học, bài học, lớp và tài khoản demo.');

        if (! $this->option('no-ai')) {
            if (! filled(config('ai.openai.key'))) {
                $this->warn('Chưa cấu hình AI → bỏ qua bước tạo bài tập.');
            } else {
                $this->buildExercises([$en, $zh], ['en' => $teacher, 'zh' => $zhTeacher]);
                $this->sampleAttempts($enClass, $learner, $peers);
                $this->sampleAttempts($zhClass, $learner, $peers->take(3));
            }
        }

        $this->table(['Vai trò', 'Đăng nhập tại', 'Email', 'Mật khẩu'], [
            ['Quản trị viên', '/admin/login', $admin->email, self::PASSWORD],
            ['Giáo viên', '/giao-vien/dang-nhap', $teacher->email, self::PASSWORD],
            ['Học viên', '/dang-ky-chung-chi', $learner->email, self::PASSWORD],
            ['Thí sinh đăng ký thi', '/dang-ky-chung-chi', 'demo.thisinh'.self::DOMAIN, self::PASSWORD],
        ]);
        $this->line(json_encode($state + ['admin' => $admin->email], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function course(array $def, string $lang, User $admin): Course
    {
        $course = Course::create(['language' => $lang, 'level' => $def['level'], 'title' => $def['title'], 'description' => $def['description'],
            'is_published' => true, 'created_by' => $admin->id]);
        foreach ($def['lessons'] as $i => $l) {
            $course->lessons()->create(['title' => $l['title'], 'summary' => $l['summary'], 'content' => $l['content'], 'position' => $i + 1, 'is_published' => true]);
        }

        return $course;
    }

    /** AI của hệ thống tự tạo bài tập cho từng bài học (đúng chủ đề, từ vựng, ngữ pháp của bài), rồi xuất bản. */
    private function buildExercises(array $courses, array $teachers): void
    {
        $service = app(AiImportService::class);
        $imports = [];
        foreach ($courses as $course) {
            foreach ($course->lessons as $lesson) {
                $imports[] = $service->composeForLesson($lesson, self::COUNTS[$course->language], $teachers[$course->language])->id;
            }
        }
        $this->info('AI đang tạo bài tập cho '.count($imports).' bài học…');
        $runner = app(AiJobRunner::class);
        $deadline = time() + 900;
        do {
            $runner->work(50);
            $left = AiImport::whereIn('id', $imports)->whereNotIn('status', ['saved', 'failed'])->count();
            $this->line('  còn '.$left.' bài học đang tạo…');
            if ($left) {
                sleep(2);
            }
        } while ($left && time() < $deadline);

        foreach (AiImport::whereIn('id', $imports)->get() as $imp) {
            if ($imp->status === 'failed' || ! $imp->custom_exercise_id) {
                $this->warn("  Bài học #{$imp->lesson_id}: AI chưa tạo được ({$imp->error}).");

                continue;
            }
            $ex = CustomExercise::find($imp->custom_exercise_id);
            $ex->update(['title' => 'Luyện tập — '.$imp->lesson->title, 'status' => 'published', 'is_standard' => true,
                'created_by_name' => $teachers[$ex->course->language]->name, 'created_by_type' => 'teacher', 'created_by_id' => $teachers[$ex->course->language]->id]);
            $this->line("  ✓ {$ex->title}: {$ex->items()->count()} phần".($imp->error ? " ({$imp->error})" : ''));
        }
        $this->voiceListening($courses);
        // Từ vựng của từng bài (học viên ôn bằng thẻ)
        $vocab = app(\App\Support\Vocab\LessonVocabulary::class);
        foreach ($courses as $course) {
            foreach ($course->lessons()->with('course')->get() as $lesson) {
                try {
                    $lesson->update(['vocabulary' => $vocab->generate($lesson)]);
                    $this->line("  📚 Từ vựng: {$lesson->title} — ".count($lesson->vocabulary ?? []).' từ');
                } catch (\Throwable $e) {
                    $this->warn('  Không lập được từ vựng: '.$e->getMessage());
                }
            }
        }
    }

    /** Phần nghe có lời bài nghe → tạo sẵn file giọng đọc AI (học viên bấm nghe là có ngay). */
    private function voiceListening(array $courses): void
    {
        if (! config('ai.tts_enabled')) {
            return;
        }
        $svc = app(ItemAiService::class);
        foreach (CustomExercise::whereIn('course_id', collect($courses)->pluck('id'))->with('items.exercise.course')->get() as $ex) {
            foreach ($ex->items as $item) {
                if (! $item->audio_path && trim((string) ($item->content['stimulus']['transcript'] ?? '')) !== '') {
                    try {
                        $svc->tts($item, []);
                        $this->line("  🔊 Giọng đọc AI: {$ex->title} — ".($item->title ?: $item->type_code));
                    } catch (\Throwable $e) {
                        $this->warn('  Không tạo được giọng đọc: '.$e->getMessage());
                    }
                }
            }
        }
    }

    /** Học viên đã làm bài tập của bài 1 (bảng điểm, xếp hạng, tiến độ có dữ liệu). Học viên chính chừa lại bài 2 trở đi để tự làm khi quay. */
    private function sampleAttempts(Classroom $class, Student $learner, $peers): void
    {
        $service = app(CustomExerciseService::class);
        $first = CustomExercise::where('course_id', $class->course_id)->where('status', 'published')->orderBy('lesson_id')->with('items')->first();
        if (! $first) {
            return;
        }
        $cce = ClassroomCustomExercise::firstOrCreate(['classroom_id' => $class->id, 'custom_exercise_id' => $first->id], ['lesson_id' => $first->lesson_id]);
        foreach ($peers->push($learner)->values() as $n => $s) {
            $attempt = CustomAttempt::create(['classroom_custom_exercise_id' => $cce->id, 'student_id' => $s->id, 'status' => 'in_progress', 'started_at' => now()->subHours(30 - $n * 3)]);
            // Mỗi người đúng một phần khác nhau → điểm khác nhau.
            $answers = $first->items->mapWithKeys(function ($item, $k) use ($n) {
                $type = $item->type();
                $objective = ! in_array($type?->skill, ['writing', 'speaking'], true);

                return [$item->id => $objective && ($k + $n) % 4 !== 0 ? $type->engine()->perfectAnswer($item->content, $type) : []];
            })->all();
            try {
                $service->submit($attempt, $answers);
            } catch (\Throwable $e) {
                $this->warn('  Bài làm mẫu lỗi: '.$e->getMessage());
            }
        }
    }

    private function saveState(array $state): void
    {
        Storage::disk('local')->put(self::STATE, json_encode($state, JSON_PRETTY_PRINT));
    }

    private function cleanup(bool $quiet = false): int
    {
        $state = json_decode((string) (Storage::disk('local')->exists(self::STATE) ? Storage::disk('local')->get(self::STATE) : '{}'), true) ?: [];
        DB::transaction(function () use ($state) {
            $students = Student::where('email', 'like', '%'.self::DOMAIN)->get();
            foreach ($students as $s) {
                CustomAttempt::where('student_id', $s->id)->delete();
                XpLog::where('student_id', $s->id)->delete();
                $s->delete();
            }
            Classroom::whereIn('id', $state['classrooms'] ?? [])->get()->each->delete();
            foreach (Course::whereIn('id', $state['courses'] ?? [])->get() as $c) {
                foreach (CustomExercise::where('course_id', $c->id)->get() as $ex) {
                    Storage::disk('local')->deleteDirectory("custom-exercises/{$ex->id}");
                }
                foreach (AiImport::where('course_id', $c->id)->get() as $imp) {
                    Storage::disk('local')->deleteDirectory($imp->storageDir());
                }
                $c->delete();   // xoá dây chuyền bài học, bài tập, AI import
            }
            foreach (Teacher::where('email', 'like', '%'.self::DOMAIN)->get() as $t) {
                DB::table('course_editors')->where('teacher_id', $t->id)->delete();
                $t->delete();
            }
            User::where('email', 'like', '%'.self::DOMAIN)->delete();
            // Liên hệ mẫu (video hướng dẫn mục Liên hệ)
            \App\Models\Contact::where('email', 'like', '%'.self::DOMAIN)->get()->each->delete();
        });
        Storage::disk('local')->delete(self::STATE);
        if (! $quiet) {
            $this->info('Đã xoá toàn bộ dữ liệu demo (khoá học, lớp, tài khoản @tutorial.local).');
        }

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- nội dung khoá học

    private static function english(): array
    {
        return ['title' => 'Tiếng Anh giao tiếp A2', 'level' => 'A2',
            'description' => 'Khoá tiếng Anh giao tiếp trình độ A2 (CEFR) cho người mới bắt đầu: chào hỏi, sinh hoạt hằng ngày, ăn uống, du lịch. Mỗi bài gồm từ vựng, ngữ pháp trọng tâm, hội thoại mẫu và bài luyện 4 kỹ năng.',
            'lessons' => [
                ['title' => 'Unit 1 — Hello! Nice to meet you', 'summary' => 'Chào hỏi, giới thiệu tên, tuổi, quê quán, nghề nghiệp. Động từ to be (am / is / are).',
                    'content' => "TỪ VỰNG: hello, hi, good morning, good evening, name, first name, surname, age, country, city, job, student, teacher, doctor, engineer, nurse, Vietnam, Vietnamese, England, English, friend, classmate.\n"
                        ."NGỮ PHÁP: Động từ to be — I am (I'm), you/we/they are ('re), he/she/it is ('s). Phủ định: I'm not, he isn't, they aren't. Câu hỏi: Are you a student? — Yes, I am. / No, I'm not. Where are you from? — I'm from Hanoi. What's your name? How old are you? What do you do?\n"
                        ."HỘI THOẠI MẪU:\nAnna: Hi! I'm Anna. What's your name?\nNam: Hello, Anna. My name is Nam. Nice to meet you.\nAnna: Nice to meet you too. Where are you from, Nam?\nNam: I'm from Vietnam. I'm a student at Hanoi University. And you?\nAnna: I'm from England. I'm an English teacher."],
                ['title' => 'Unit 2 — My daily routine', 'summary' => 'Nói về thói quen hằng ngày, giờ giấc. Thì hiện tại đơn, trạng từ chỉ tần suất.',
                    'content' => "TỪ VỰNG: get up, wake up, have breakfast, brush my teeth, take a shower, go to work, go to school, catch the bus, have lunch, finish work, come home, cook dinner, watch TV, do homework, go to bed; o'clock, half past, quarter to.\n"
                        ."NGỮ PHÁP: Thì hiện tại đơn — I/you/we/they work; he/she works, goes, watches, has. Phủ định: I don't, she doesn't. Câu hỏi: Do you…? Does he…? What time do you get up? Trạng từ tần suất: always, usually, often, sometimes, rarely, never (đứng trước động từ thường, sau to be).\n"
                        ."ĐOẠN VĂN MẪU: Linh is an office worker. She usually gets up at six o'clock. She has breakfast with her family and catches the bus at seven. She starts work at eight and finishes at five. In the evening she sometimes goes to the gym. She never goes to bed after eleven."],
                ['title' => 'Unit 3 — Food and drinks', 'summary' => 'Gọi món ở quán cà phê, nhà hàng. Danh từ đếm được / không đếm được, some / any, How much / How many.',
                    'content' => "TỪ VỰNG: menu, waiter, bill, coffee, tea, orange juice, water, milk, bread, rice, noodles, chicken, beef, fish, egg, salad, soup, cake, ice cream, sandwich; hungry, thirsty, delicious, spicy.\n"
                        ."NGỮ PHÁP: Danh từ đếm được (an apple, two eggs) và không đếm được (water, rice, bread). some (câu khẳng định, lời mời), any (phủ định, câu hỏi). How many + danh từ đếm được số nhiều; How much + danh từ không đếm được / hỏi giá. Lời đề nghị lịch sự: I'd like…, Can I have…, Would you like…?\n"
                        ."HỘI THOẠI MẪU:\nWaiter: Good afternoon. Are you ready to order?\nMai: Yes. I'd like a chicken sandwich and a cup of tea, please.\nWaiter: Would you like any cake?\nMai: No, thanks. How much is it?\nWaiter: It's six dollars fifty.\nMai: Here you are."],
                ['title' => 'Unit 4 — Travel and directions', 'summary' => 'Hỏi đường, chỉ đường, nói về chuyến đi. Giới từ chỉ nơi chốn, câu mệnh lệnh, thì quá khứ đơn cơ bản.',
                    'content' => "TỪ VỰNG: station, airport, hotel, museum, bank, post office, hospital, supermarket, bridge, street, corner, traffic lights, map, ticket, passport, luggage; turn left, turn right, go straight on, cross the road.\n"
                        ."NGỮ PHÁP: Giới từ nơi chốn — next to, opposite, between, behind, in front of, on the corner of. Câu mệnh lệnh chỉ đường: Go straight on. Turn left at the traffic lights. Hỏi đường: Excuse me, how do I get to…? Is there a bank near here? Quá khứ đơn: went, visited, saw, took, stayed; Did you…?\n"
                        ."HỘI THOẠI MẪU:\nTourist: Excuse me, how do I get to the museum?\nLocal: Go straight on and turn left at the traffic lights. The museum is opposite the bank.\nTourist: Is it far?\nLocal: No, it's about five minutes on foot.\nTourist: Thank you very much!"],
            ]];
    }

    private static function chinese(): array
    {
        return ['title' => 'Tiếng Trung HSK 1', 'level' => 'HSK 1',
            'description' => 'Khoá tiếng Trung sơ cấp theo chuẩn HSK 1: phát âm pinyin và thanh điệu, chữ Hán cơ bản, chào hỏi, giới thiệu bản thân, gia đình, ngày tháng. Hoàn thành khoá có thể thi HSK 1.',
            'lessons' => [
                ['title' => '第一课 你好 — Chào hỏi', 'summary' => 'Pinyin, 4 thanh điệu; chào hỏi, cảm ơn, xin lỗi, tạm biệt.',
                    'content' => "TỪ VỰNG: 你 (nǐ) bạn, 好 (hǎo) tốt, 你好 (nǐ hǎo) xin chào, 您 (nín) ngài, 们 (men) các, 谢谢 (xièxie) cảm ơn, 不客气 (bú kèqi) không có gì, 对不起 (duìbuqǐ) xin lỗi, 没关系 (méi guānxi) không sao, 再见 (zàijiàn) tạm biệt, 老师 (lǎoshī) thầy cô.\n"
                        ."PHÁT ÂM: 4 thanh điệu — mā (thanh 1, ngang), má (thanh 2, đi lên), mǎ (thanh 3, xuống rồi lên), mà (thanh 4, đi xuống); thanh nhẹ: ma. Biến điệu: hai thanh 3 liền nhau → thanh đầu đọc thành thanh 2 (nǐ hǎo → ní hǎo); 不 trước thanh 4 đọc bú.\n"
                        ."HỘI THOẠI MẪU:\nA: 你好！(Nǐ hǎo!)\nB: 你好！(Nǐ hǎo!)\nA: 谢谢你。(Xièxie nǐ.)\nB: 不客气。(Bú kèqi.)\nA: 对不起！(Duìbuqǐ!)\nB: 没关系。(Méi guānxi.)\nA: 老师再见！(Lǎoshī zàijiàn!)"],
                ['title' => '第二课 你叫什么名字 — Giới thiệu bản thân', 'summary' => 'Hỏi và nói tên, quốc tịch, nghề nghiệp. Câu với 是, câu hỏi với 吗 và 什么.',
                    'content' => "TỪ VỰNG: 我 (wǒ) tôi, 叫 (jiào) gọi là, 什么 (shénme) cái gì, 名字 (míngzi) tên, 是 (shì) là, 不 (bù) không, 学生 (xuésheng) học sinh, 人 (rén) người, 中国 (Zhōngguó) Trung Quốc, 越南 (Yuènán) Việt Nam, 哪 (nǎ) nào, 国 (guó) nước, 吗 (ma) trợ từ nghi vấn.\n"
                        ."NGỮ PHÁP: Câu chữ 是: 我是学生。Phủ định: 我不是老师。Câu hỏi 吗: 你是中国人吗？Câu hỏi với từ để hỏi (không dùng 吗): 你叫什么名字？你是哪国人？\n"
                        ."HỘI THOẠI MẪU:\nA: 你叫什么名字？(Nǐ jiào shénme míngzi?)\nB: 我叫李明。(Wǒ jiào Lǐ Míng.)\nA: 你是中国人吗？(Nǐ shì Zhōngguó rén ma?)\nB: 不是，我是越南人。(Bú shì, wǒ shì Yuènán rén.)\nA: 你是学生吗？\nB: 是，我是学生。"],
                ['title' => '第三课 我的家 — Gia đình', 'summary' => 'Giới thiệu các thành viên gia đình. Câu với 有 / 没有, hỏi số lượng với 几, lượng từ 个 / 口.',
                    'content' => "TỪ VỰNG: 家 (jiā) nhà, 爸爸 (bàba) bố, 妈妈 (māma) mẹ, 哥哥 (gēge) anh trai, 姐姐 (jiějie) chị gái, 弟弟 (dìdi) em trai, 妹妹 (mèimei) em gái, 儿子 (érzi) con trai, 女儿 (nǚ'ér) con gái, 有 (yǒu) có, 没有 (méiyǒu) không có, 几 (jǐ) mấy, 口 (kǒu) lượng từ người trong nhà, 个 (gè) cái, chiếc, 和 (hé) và.\n"
                        ."NGỮ PHÁP: 有 / 没有 (phủ định của 有 dùng 没, không dùng 不): 我有一个哥哥。我没有妹妹。Hỏi số lượng nhỏ (dưới 10) dùng 几 + lượng từ: 你家有几口人？Số + lượng từ + danh từ: 三口人, 两个姐姐 (số 2 trước lượng từ dùng 两).\n"
                        ."ĐOẠN VĂN MẪU: 我家有四口人：爸爸、妈妈、姐姐和我。我爸爸是老师，我妈妈是医生。我姐姐是大学生。我没有弟弟。"],
                ['title' => '第四课 今天几号 — Số đếm, ngày tháng', 'summary' => 'Số đếm 1–99, ngày, tháng, thứ trong tuần. Hỏi ngày với 几月几号, 星期几.',
                    'content' => "TỪ VỰNG: 一 二 三 四 五 六 七 八 九 十 (1–10), 零 (líng) số 0, 今天 (jīntiān) hôm nay, 明天 (míngtiān) ngày mai, 昨天 (zuótiān) hôm qua, 年 (nián) năm, 月 (yuè) tháng, 号 (hào) ngày, 星期 (xīngqī) tuần, thứ, 星期一…星期六 thứ Hai…thứ Bảy, 星期天 chủ nhật, 生日 (shēngrì) sinh nhật.\n"
                        ."NGỮ PHÁP: Số 11–99: 十一, 二十, 三十五. Thứ tự ngày tháng tiếng Trung: năm → tháng → ngày → thứ: 2026年11月5号星期四。Hỏi: 今天几月几号？今天星期几？你的生日是几月几号？Câu danh từ vị ngữ (không cần 是): 今天星期四。\n"
                        ."HỘI THOẠI MẪU:\nA: 今天几月几号？(Jīntiān jǐ yuè jǐ hào?)\nB: 今天十一月五号。\nA: 今天星期几？\nB: 今天星期四。\nA: 你的生日是几月几号？\nB: 我的生日是三月八号。"],
            ]];
    }
}
