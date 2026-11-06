<?php

namespace Tests\Feature;

use App\Models\AiImport;
use App\Models\AiJob;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CustomExercise;
use App\Models\Teacher;
use App\Support\Ai\Jobs\AiJobRunner;
use App\Support\AiImport\AiImportService;
use App\Support\AiImport\AudioLinker;
use App\Support\AiImport\DocxReader;
use App\Support\AiImport\TemplateDocx;
use App\Support\QuestionTypes\Registry;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** V2 bước 6: AI đọc file Word + file nghe → bản thiết kế → kiểm tra, ghép file nghe, hỏi khi thiếu → lưu thành bài tập. */
class AiImportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api.openai.com/v1/responses';

    private Course $course;

    private Teacher $teacher;

    private CourseLesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // không bao giờ đụng file thật trong storage/app/private
        Registry::flush();
        config(['ai.openai.key' => 'sk-test-123456789012345678', 'ai.tts_enabled' => false, 'ai.limits' => ['per_actor_daily' => 0, 'monthly_calls' => 0, 'monthly_budget_usd' => 0]]);
        $this->course = Course::create(['language' => 'en', 'title' => 'IELTS', 'is_published' => true]);
        $this->teacher = Teacher::create(['name' => 'Cô Lan', 'email' => 'lan@gv.vn', 'password' => 'secret123']);
        $this->course->editors()->attach($this->teacher->id);
        $this->lesson = $this->course->lessons()->create(['title' => 'Unit 3 — Listening', 'position' => 1]);
        $c = Classroom::create(['course_id' => $this->course->id, 'name' => 'Lớp 1']);
        $c->teachers()->attach($this->teacher->id, ['can_customize' => true]);
    }

    private function ai(array $data): PromiseInterface
    {
        return Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]], 'usage' => ['input_tokens' => 1000, 'output_tokens' => 500]]);
    }

    /** File Word thật (zip) có đoạn văn + bảng. */
    private function docx(array $paragraphs, array $table = []): string
    {
        $esc = fn ($s) => htmlspecialchars($s, ENT_XML1);
        $body = implode('', array_map(fn ($p) => '<w:p><w:r><w:t xml:space="preserve">'.$esc($p).'</w:t></w:r></w:p>', $paragraphs));
        if ($table) {
            $body .= '<w:tbl>'.implode('', array_map(fn ($row) => '<w:tr>'.implode('', array_map(fn ($c) => '<w:tc><w:p><w:r><w:t>'.$esc($c).'</w:t></w:r></w:p></w:tc>', $row)).'</w:tr>', $table)).'</w:tbl>';
        }
        $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();

        return $path;
    }

    private function mcqContent(string $q, string $answer = 'B'): array
    {
        return ['questions' => [['id' => '1', 'prompt' => $q, 'options' => ['Monday', 'Friday', 'Sunday'], 'answer' => $answer]]];
    }

    public function test_docx_reader_audio_linker_and_template(): void
    {
        $path = $this->docx(['PART 1', 'Listen to Track05.mp3'], [['Name', 'Answer'], ['Tom', '[[1]]']]);
        $text = app(DocxReader::class)->text($path);
        $this->assertStringContainsString("PART 1\nListen to Track05.mp3", $text);
        $this->assertStringContainsString('Name | Answer', $text);
        $this->assertStringContainsString('Tom | [[1]]', $text);
        @unlink($path);

        $audio = [['id' => 'a', 'name' => 'Track05.MP3'], ['id' => 'b', 'name' => 'CD1 - track 7.mp3'], ['id' => 'c', 'name' => 'Bài nghe số 3.m4a']];
        $l = app(AudioLinker::class);
        $this->assertSame('a', $l->match('track05.mp3', $audio));
        $this->assertSame('a', $l->match('TRACK 05', $audio));
        $this->assertSame('b', $l->match('track07', $audio));
        $this->assertSame('c', $l->match('bai nghe so 3', $audio));
        $this->assertSame('c', $l->match('file 3', $audio));
        $this->assertNull($l->match('track09.mp3', $audio));
        $this->assertNull($l->match(null, $audio));

        $tpl = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($tpl, TemplateDocx::build());
        $this->assertStringContainsString('PHẦN 1 — Nghe điền từ', app(DocxReader::class)->text($tpl));
        @unlink($tpl);
    }

    public function test_four_part_listening_doc_with_three_of_four_audio_files(): void
    {
        $doc = $this->docx([
            'LISTENING TEST — Unit 3',
            'PART 1. Listen to track01.mp3. Each question is worth 0.25 points.', 'When is the trip? A. Monday B. Friday C. Sunday', 'Answer part 1: B',
            'PART 2. Listen to Track02.mp3.', 'When does the club meet? A. Monday B. Friday C. Sunday', 'Answer part 2: A',
            'PART 3. Listen to track03.mp3.', 'Script: The shop closes on Sunday.', 'When does the shop close? A. Monday B. Friday C. Sunday', 'Answer part 3: C',
            'PART 4. Listen to track04.mp3.', 'When is the concert? A. Monday B. Friday C. Sunday', 'Answer part 4: B',
        ]);
        $plan = ['title' => 'Listening Unit 3', 'sections' => collect(range(1, 4))->map(fn ($n) => [
            'title' => "Part {$n}", 'type_code' => 'ielts.listening.mcq', 'start_quote' => "PART {$n}.", 'end_quote' => "Answer part {$n}:",
            'audio_file' => "track0{$n}.mp3", 'needs_audio' => true, 'transcript' => $n === 3 ? 'The shop closes on Sunday.' : null,
            'points_per_question' => $n === 1 ? 0.25 : null, 'scale' => null, 'confidence' => $n === 4 ? 0.4 : 0.95, 'origin' => 'textbook', 'brief' => '',
        ])->all()];
        Http::fake([self::URL => Http::sequence()
            ->pushResponse($this->ai($plan))
            ->pushResponse($this->ai(['content_json' => json_encode($this->mcqContent('When is the trip?')), 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))
            ->pushResponse($this->ai(['content_json' => json_encode($this->mcqContent('When does the club meet?', 'A')), 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))
            ->pushResponse($this->ai(['content_json' => json_encode($this->mcqContent('When does the shop close?', 'X')), 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))      // sai lược đồ
            ->pushResponse($this->ai(['content_json' => json_encode($this->mcqContent('When is the concert?')), 'flags' => ['Đáp án do AI giải: câu 1'], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))
            ->pushResponse($this->ai(['content_json' => json_encode($this->mcqContent('When does the shop close?', 'C')), 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null])), // AI sửa lại
            // Chép lời file nghe (speech-to-text) trước khi chia phần
            'https://api.openai.com/v1/audio/transcriptions' => Http::sequence()
                ->push(['text' => 'Part one. The school trip is on Friday.'])
                ->push(['text' => 'Part two. The chess club meets every Monday.'])
                ->push(['text' => 'Part four. The concert is on Friday evening.'])
                ->push(['text' => 'The shop closes on Sunday.']),
        ]);

        $t = $this->actingAs($this->teacher, 'teacher');
        $t->get(route('teacher.ai-import.create', [$this->course, 'lesson' => $this->lesson->id]))->assertOk()->assertSee('Gửi cho AI dựng bài')->assertSee('Unit 3 — Listening');
        $t->postJson(route('teacher.ai-import.store', $this->course), ['source_name' => 'de.docx'])->assertStatus(422);   // bắt buộc thuộc bài học
        $urls = $t->postJson(route('teacher.ai-import.store', $this->course), ['source_name' => 'de.docx', 'lesson_id' => $this->lesson->id])->assertOk()->json();
        $t->post($urls['document'], ['file' => new UploadedFile($doc, 'de-nghe.docx', null, null, true)], ['Accept' => 'application/json'])->assertJson(['ok' => true]);
        foreach (['Track01.mp3', 'TRACK02.MP3', 'CD1-track04.mp3'] as $name) {
            $q = http_build_query(['upload' => 'up'.md5($name), 'part' => 0, 'total' => 1, 'name' => $name]);
            $t->call('POST', $urls['audio'].'?'.$q, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], 'ID3fake-audio-'.$name)
                ->assertJson(['ok' => true, 'complete' => true]);
        }
        $t->postJson($urls['begin'])->assertOk();
        $import = AiImport::sole();
        $this->assertSame('transcribing', $import->status, 'Bước 0: chép lời các file nghe trước khi chia phần');
        $this->assertSame(3, AiJob::where('type', 'ai_import_transcribe')->count());

        app(AiJobRunner::class)->work(30);
        $import->refresh();

        // Lời đã chép được gửi cho AI ở lượt chia phần và lượt dựng phần nghe tương ứng
        $this->assertSame(['done', 'done', 'done'], collect($import->audio)->pluck('transcript_status')->all());
        Http::assertSent(fn ($r) => str_contains($r->url(), '/responses') && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'LỜI CÁC FILE NGHE')
            && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'chess club meets every Monday'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/responses') && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'LỜI FILE NGHE CỦA PHẦN NÀY')
            && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'The school trip is on Friday'));

        $this->assertSame('review', $import->status);
        $this->assertCount(4, $import->sections);
        // 4 phần dựng SONG SONG trong 1 việc; vòng sửa phần 3 chạy luôn trong việc đó, không xếp việc mới
        $this->assertSame(1, AiJob::where('type', 'ai_import_build')->count());
        $this->assertSame('teacher:'.$this->teacher->id.'/i'.$import->id, AiJob::where('type', 'ai_import_build')->value('lane'));
        $audio = collect($import->audio)->keyBy('id');
        $this->assertSame('Track01.mp3', $audio[$import->sections[0]['audio_id']]['name']);
        $this->assertSame('TRACK02.MP3', $audio[$import->sections[1]['audio_id']]['name']);
        $this->assertNull($import->sections[2]['audio_id']);
        $this->assertSame('CD1-track04.mp3', $audio[$import->sections[3]['audio_id']]['name']);
        $this->assertSame([2], AiImportService::missingAudio($import));
        $this->assertSame('ok', $import->sections[2]['status'], 'Phần sai lược đồ được AI sửa ở lượt 2');
        $this->assertStringContainsString('The shop closes on Sunday', $import->sections[2]['source']);
        $this->assertContains('Đáp án do AI giải: câu 1', $import->sections[3]['flags']);
        $this->assertTrue(collect($import->sections[3]['flags'])->contains(fn ($f) => str_contains($f, 'chưa chắc dạng bài')));

        // Màn hình duyệt: hỏi đúng phần thiếu file
        $t->get(route('teacher.ai-import.show', [$this->course, $import]))->assertOk()
            ->assertSee('Phần 3')->assertSee('cần file nghe nhưng chưa có')->assertSee('Tạo giọng đọc AI từ lời thoại')->assertSee('AI chưa chắc');

        // Giáo viên tải file cho phần 3 rồi lưu
        $t->post(route('teacher.ai-import.section.audio', [$this->course, $import, 2]), ['audio' => UploadedFile::fake()->createWithContent('track03.mp3', 'ID3fake-audio-track03')])->assertRedirect();
        $import->refresh();
        $this->assertSame([], AiImportService::missingAudio($import));
        // File tải bổ sung ở màn duyệt cũng được chép lời
        $this->assertSame('pending', collect($import->audio)->last()['transcript_status']);
        app(AiJobRunner::class)->work(30);
        $this->assertSame('The shop closes on Sunday.', collect($import->fresh()->audio)->last()['transcript']);
        $t->get(route('teacher.ai-import.show', [$this->course, $import]))->assertSee('Lời đã chép từ file nghe');

        // Đổi thứ tự + xoá bớt phần (câu hỏi lớn)
        $titles = fn () => collect($import->fresh()->sections)->pluck('title')->all();
        $t->post(route('teacher.ai-import.section.move', [$this->course, $import, 0]), ['dir' => 'up'])->assertRedirect();
        $this->assertSame(['Part 1', 'Part 2', 'Part 3', 'Part 4'], $titles(), 'Phần đầu không lên được nữa');
        $t->post(route('teacher.ai-import.section.move', [$this->course, $import, 0]), ['dir' => 'down'])->assertRedirect();
        $this->assertSame(['Part 2', 'Part 1', 'Part 3', 'Part 4'], $titles());
        $t->post(route('teacher.ai-import.section.move', [$this->course, $import, 1]), ['dir' => 'up']);
        $this->assertSame(['Part 1', 'Part 2', 'Part 3', 'Part 4'], $titles());
        $t->delete(route('teacher.ai-import.section.destroy', [$this->course, $import, 1]))->assertRedirect();
        $this->assertSame(['Part 1', 'Part 3', 'Part 4'], $titles());
        // Gọi ngầm từ màn duyệt (JSON): không chuyển trang
        $t->postJson(route('teacher.ai-import.section.move', [$this->course, $import, 2]), ['dir' => 'up'])->assertOk()->assertJson(['ok' => true, 'titles' => ['Part 1', 'Part 4', 'Part 3']]);
        $t->postJson(route('teacher.ai-import.section.move', [$this->course, $import, 2]), ['dir' => 'down'])->assertStatus(422);
        $t->postJson(route('teacher.ai-import.section.move', [$this->course, $import, 1]), ['dir' => 'down'])->assertOk();
        $this->assertSame(['Part 1', 'Part 3', 'Part 4'], $titles());
        // Đang có phần dựng lại thì khoá đổi thứ tự / xoá (chỉ số phần sẽ lệch)
        $secs = $import->fresh()->sections;
        $secs[1]['status'] = 'pending';
        $import->update(['sections' => $secs]);
        $t->delete(route('teacher.ai-import.section.destroy', [$this->course, $import, 0]))->assertSessionHasErrors('save');
        $t->deleteJson(route('teacher.ai-import.section.destroy', [$this->course, $import, 0]))->assertStatus(422)->assertJson(['ok' => false]);
        $secs[1]['status'] = 'ok';
        $import->update(['sections' => $secs]);

        $import->refresh();
        $t->post(route('teacher.ai-import.save', [$this->course, $import]), ['title' => 'Listening Unit 3'])->assertRedirect();

        $ex = CustomExercise::sole();
        $this->assertSame($this->lesson->id, $ex->lesson_id, 'Bài tập nằm trong bài học');
        $this->assertSame('ai', $ex->source);
        $this->assertSame('draft', $ex->status);
        $this->assertSame('Cô Lan', $ex->created_by_name);
        $this->assertCount(3, $ex->items);
        $this->assertSame(3, $ex->items->whereNotNull('audio_path')->count());
        $this->assertEquals(['points' => 0.25], $ex->items[0]->scoring);
        $this->assertNull($ex->items[1]->scoring);
        Storage::disk('local')->assertExists($ex->items[0]->audio_path);
        $this->assertSame('saved', $import->fresh()->status);
        $t->get(route('teacher.custom.edit', [$this->course, $ex]))->assertOk()->assertSee('Đáp án do AI giải');
        @unlink($doc);
    }

    public function test_invalid_twice_is_flagged_for_teacher_and_editable(): void
    {
        $import = AiImport::create(['course_id' => $this->course->id, 'title' => 'X', 'status' => 'uploading', 'text' => "PART 1. Choose.\nQ1? A. a B. b\nEnd of part 1.",
            'created_by_type' => 'teacher', 'created_by_id' => $this->teacher->id, 'created_by_name' => 'Cô Lan', 'lesson_id' => $this->lesson->id]);
        Http::fake([self::URL => Http::sequence()
            ->pushResponse($this->ai(['title' => 'X', 'sections' => [['title' => 'P1', 'type_code' => 'general.mcq_single', 'start_quote' => 'Không có trong tài liệu', 'end_quote' => 'zzz',
                'audio_file' => null, 'needs_audio' => false, 'transcript' => null, 'points_per_question' => null, 'scale' => null, 'confidence' => 0.9, 'origin' => 'textbook', 'brief' => '']]]))
            ->pushResponse($this->ai(['content_json' => '{oops', 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))
            ->pushResponse($this->ai(['content_json' => '{"questions": []}', 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))]);

        app(AiImportService::class)->start($import);
        app(AiJobRunner::class)->work(30);
        $import->refresh();
        $this->assertSame('review', $import->status);
        $this->assertSame('invalid', $import->sections[0]['status']);
        $this->assertTrue(collect($import->sections[0]['flags'])->contains(fn ($f) => str_contains($f, 'Không xác định được vị trí')));

        $t = $this->actingAs($this->teacher, 'teacher');
        $t->post(route('teacher.ai-import.save', [$this->course, $import]))->assertSessionHasErrors('save');
        $t->put(route('teacher.ai-import.section.update', [$this->course, $import, 0]), ['title' => 'P1', 'type_code' => 'general.mcq_single',
            'content' => json_encode($this->mcqContent('Q1?'))])->assertRedirect();
        $this->assertSame('ok', $import->fresh()->sections[0]['status']);
        $t->post(route('teacher.ai-import.save', [$this->course, $import]))->assertRedirect();
        $this->assertSame(1, CustomExercise::count());

        $this->assertSame($this->lesson->id, CustomExercise::sole()->lesson_id);
        // Giáo viên khác không xem được yêu cầu của người khác
        $other = Teacher::create(['name' => 'B', 'email' => 'b@gv.vn', 'password' => 'secret123']);
        $this->course->editors()->attach($other->id);
        $this->actingAs($other, 'teacher')->get(route('teacher.ai-import.show', [$this->course, $import]))->assertNotFound();
    }

    /** Bài nối tranh trên trang scan: AI nhìn ảnh trang, ra đáp án, cắt vùng tranh; câu kết có số dấu chấm khác bản OCR vẫn cắt đúng. */
    public function test_picture_matching_on_scanned_page_uses_page_image_and_crops_picture(): void
    {
        $import = AiImport::create(['course_id' => $this->course->id, 'title' => 'Unit 4', 'status' => 'uploading',
            'created_by_type' => 'teacher', 'created_by_id' => $this->teacher->id, 'created_by_name' => 'Cô Lan', 'lesson_id' => $this->lesson->id]);
        $img = imagecreatetruecolor(800, 1100);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagejpeg($img);
        Storage::disk('local')->put("ai-imports/{$import->id}/pages/p1.jpg", ob_get_clean());
        $ocr = "1a The equipment in the pictures can all be found in the homes of many cooks. Match the\nwords to the items.\n\nkitchen scales | measuring jug | flask | kettle\n\n1 .................................\n2 .................................\n3 .................................\n4 .................................\n\n1b Use the words from exercise 1a to complete these sentences.\n1 A ........ is probably the most used piece of kitchen equipment.";
        $import->update(['pages' => [['no' => 1, 'text' => $ocr, 'ocr' => true, 'done' => true, 'image' => "ai-imports/{$import->id}/pages/p1.jpg"]]]);

        $content = ['items' => collect(range(1, 4))->map(fn ($n) => ['id' => (string) $n, 'text' => "Picture {$n}"])->all(),
            'options' => ['kitchen scales', 'measuring jug', 'flask', 'kettle'], 'answers' => ['1' => 'D', '2' => 'A', '3' => 'C', '4' => 'B']];
        Http::fake([self::URL => Http::sequence()
            ->pushResponse($this->ai(['title' => 'Unit 4', 'sections' => [
                ['title' => '1a', 'type_code' => 'general.match_picture', 'start_quote' => '1a The equipment in the pictures', 'end_quote' => '4 ......................',
                    'audio_file' => null, 'needs_audio' => false, 'transcript' => null, 'points_per_question' => null, 'scale' => null, 'confidence' => 0.9, 'origin' => 'textbook', 'brief' => ''],
                ['title' => '1b', 'type_code' => 'general.gap_typed', 'start_quote' => '1b Use the words from exercise 1a', 'end_quote' => 'piece of kitchen equipment.',
                    'audio_file' => null, 'needs_audio' => false, 'transcript' => null, 'points_per_question' => null, 'scale' => null, 'confidence' => 0.9, 'origin' => 'textbook', 'brief' => ''],
            ]]))
            ->pushResponse($this->ai(['content_json' => json_encode($content), 'flags' => [], 'image_page' => 1, 'image_box' => [0.1, 0.2, 0.9, 0.45], 'image_refs' => null]))
            ->pushResponse($this->ai(['content_json' => json_encode(['text' => 'A [[1]] is used a lot.', 'answers' => ['1' => ['kettle']]]), 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))]);

        app(AiImportService::class)->start($import);
        app(AiJobRunner::class)->work(30);
        $import->refresh();

        $this->assertSame('review', $import->status);
        $s = $import->sections[0];
        $this->assertSame('ok', $s['status']);
        $this->assertStringNotContainsString('1b Use the words', $s['source'], 'Không lấy lẫn sang bài 1b');
        $this->assertSame([], array_values(array_filter($s['flags'], fn ($f) => str_contains($f, 'đoạn kết thúc'))));
        // Ảnh trang được gửi kèm cho AI ở lượt dựng
        Http::assertSent(fn ($req) => str_contains(json_encode($req->data()), 'input_image') && str_contains(json_encode($req->data(), JSON_UNESCAPED_UNICODE), 'ẢNH TRANG GỐC'));
        // Vùng tranh được cắt thành ảnh của phần
        $this->assertNotNull($s['image_path']);
        [$w, $h] = getimagesize(Storage::disk('local')->path($s['image_path']));
        $this->assertEqualsWithDelta(664, $w, 4);
        $this->assertEqualsWithDelta(308, $h, 4);

        // Lưu → ảnh đi theo phần, học viên thấy ảnh
        $this->actingAs($this->teacher, 'teacher')->post(route('teacher.ai-import.save', [$this->course, $import]))->assertRedirect();
        $item = CustomExercise::sole()->items()->first();
        $this->assertNotNull($item->image_path);
        $this->actingAs($this->teacher, 'teacher')->get(route('teacher.custom.edit', [$this->course, $item->custom_exercise_id]))->assertOk()
            ->assertSee(route('teacher.custom.items.image-file', [$this->course, $item->custom_exercise_id, $item]), false);
    }

    public function test_flags_are_replaced_not_accumulated_across_rounds(): void
    {
        $import = AiImport::create(['course_id' => $this->course->id, 'title' => 'X', 'status' => 'uploading', 'text' => "PART 1. Choose.\nQ1? A. a B. b\nEnd of part 1.",
            'created_by_type' => 'teacher', 'created_by_id' => $this->teacher->id, 'created_by_name' => 'Cô Lan', 'lesson_id' => $this->lesson->id]);
        Http::fake([self::URL => Http::sequence()
            ->pushResponse($this->ai(['title' => 'X', 'sections' => [['title' => 'P1', 'type_code' => 'general.mcq_single', 'start_quote' => 'PART 1.', 'end_quote' => 'End of part 1.',
                'audio_file' => null, 'needs_audio' => false, 'transcript' => null, 'points_per_question' => null, 'scale' => null, 'confidence' => 0.9, 'origin' => 'textbook', 'brief' => '']]]))
            ->pushResponse($this->ai(['content_json' => '{"questions": []}', 'flags' => ['Thiếu hình'], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))
            ->pushResponse($this->ai(['content_json' => '{"questions": []}', 'flags' => ['Thiếu hình (lượt 2)'], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))]);
        app(AiImportService::class)->start($import);
        app(AiJobRunner::class)->work(30);
        $this->assertSame(['Thiếu hình (lượt 2)'], $import->fresh()->sections[0]['flags']);
    }

    /** File Word thật có ảnh nhúng (word/media + quan hệ rId). $blocks: chuỗi = đoạn văn, ['img' => n] = ảnh thứ n. */
    private function docxWithImages(array $blocks, int $imageCount): string
    {
        $esc = fn ($s) => htmlspecialchars($s, ENT_XML1);
        $body = '';
        foreach ($blocks as $b) {
            $body .= is_array($b)
                ? '<w:p><w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:blipFill><a:blip r:embed="rId'.(100 + $b['img']).'"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'
                : '<w:p><w:r><w:t xml:space="preserve">'.$esc($b).'</w:t></w:r></w:p>';
        }
        $rels = '';
        $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach (range(1, $imageCount) as $n) {
            $img = imagecreatetruecolor(200 + $n * 10, 150);
            imagefill($img, 0, 0, imagecolorallocate($img, 40 * $n, 120, 200));
            ob_start();
            imagepng($img);
            $zip->addFromString("word/media/image{$n}.png", ob_get_clean());
            $rels .= '<Relationship Id="rId'.(100 + $n).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image'.$n.'.png"/>';
        }
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();

        return $path;
    }

    public function test_word_images_are_extracted_with_markers(): void
    {
        $path = $this->docxWithImages(['Exercise 1. Match the pictures.', ['img' => 1], ['img' => 2], ['img' => 1], 'kettle | flask'], 2);
        $doc = app(DocxReader::class)->read($path);
        $this->assertSame("Exercise 1. Match the pictures.\n [Hình 1]\n [Hình 2]\n [Hình 1]\nkettle | flask", $doc['text']);
        $this->assertCount(2, $doc['images'], 'Ảnh dùng lại không bị lấy 2 lần');
        $this->assertSame([1, 2], array_column($doc['images'], 'n'));
        $this->assertSame('png', $doc['images'][0]['ext']);
        $this->assertStringStartsWith("\xFF\xD8", DocxReader::normalizeImage($doc['images'][0]['bytes']));
        @unlink($path);
    }

    /** Word có bài nối tranh: AI nhận đúng 4 ảnh của phần, trả image_refs → ghép thành 1 ảnh đánh số làm ảnh của phần; học viên thấy ảnh. */
    public function test_picture_exercise_from_word_uses_embedded_images(): void
    {
        $doc = $this->docxWithImages([
            'Exercise 1. Match the words to the pictures.', ['img' => 1], ['img' => 2], ['img' => 3], ['img' => 4], 'kettle | flask | scales | jug', 'End of exercise 1.',
            'Exercise 2. Choose the correct answer.', 'She ___ to school. A. go B. goes C. going', 'End of exercise 2.',
        ], 4);
        $content = ['items' => collect(range(1, 4))->map(fn ($n) => ['id' => (string) $n, 'text' => "Picture {$n}"])->all(),
            'options' => ['kettle', 'flask', 'scales', 'jug'], 'answers' => ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D']];
        $sec = fn ($t, $code, $start, $end) => ['title' => $t, 'type_code' => $code, 'start_quote' => $start, 'end_quote' => $end, 'audio_file' => null, 'needs_audio' => false,
            'transcript' => null, 'points_per_question' => null, 'scale' => null, 'confidence' => 0.9, 'origin' => 'textbook', 'brief' => ''];
        Http::fake([self::URL => Http::sequence()
            ->pushResponse($this->ai(['title' => 'Word test', 'sections' => [
                $sec('Exercise 1', 'general.match_picture', 'Exercise 1. Match the words', 'End of exercise 1.'),
                $sec('Exercise 2', 'general.mcq_single', 'Exercise 2. Choose', 'End of exercise 2.'),
            ]]))
            ->pushResponse($this->ai(['content_json' => json_encode($content), 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => [1, 2, 3, 4]]))
            ->pushResponse($this->ai(['content_json' => json_encode($this->mcqContent('She ___ to school.')), 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))]);

        $t = $this->actingAs($this->teacher, 'teacher');
        $urls = $t->postJson(route('teacher.ai-import.store', $this->course), ['source_name' => 'de.docx', 'lesson_id' => $this->lesson->id])->json();
        $t->post($urls['document'], ['file' => new UploadedFile($doc, 'bai-tap.docx', null, null, true)], ['Accept' => 'application/json'])->assertJson(['ok' => true, 'images' => 4]);
        $t->postJson($urls['begin'])->assertOk();
        app(AiJobRunner::class)->work(30);
        $import = AiImport::sole();
        $this->assertSame('review', $import->status);

        // Lượt dựng phần 1 gửi kèm đúng 4 ảnh Word; phần 2 không có ảnh
        $build = collect(Http::recorded())->map(fn ($r) => json_encode($r[0]->data(), JSON_UNESCAPED_UNICODE))->filter(fn ($b) => str_contains($b, 'Dựng NỘI DUNG'))->values();
        $this->assertSame(4, substr_count($build[0], 'input_image'));
        $this->assertStringContainsString('[Hình 1], [Hình 2], [Hình 3], [Hình 4]', $build[0]);
        $this->assertSame(0, substr_count($build[1], 'input_image'));

        $s = $import->sections[0];
        $this->assertSame('ok', $s['status']);
        $this->assertNotNull($s['image_path']);
        [$w, $h] = getimagesize(Storage::disk('local')->path($s['image_path']));
        $this->assertSame(4 * 336 + 16, $w, 'Ghép 4 tranh thành 1 hàng có đánh số');
        $this->assertNull($import->sections[1]['image_path'] ?? null);
        $this->assertFalse(collect($s['flags'])->contains(fn ($f) => str_contains($f, 'cắt tự động')));

        $t->post(route('teacher.ai-import.save', [$this->course, $import]))->assertRedirect();
        $item = CustomExercise::sole()->items()->orderBy('position')->first();
        $this->assertNotNull($item->image_path);
        @unlink($doc);
    }

    /** Đáp án ở cuối tài liệu + bài đọc nằm ở bài khác: lượt dựng mỗi phần nhận cả tài liệu và quy tắc phải dùng đáp án có sẵn. */
    public function test_build_pass_gets_answer_key_and_shared_passage_from_whole_document(): void
    {
        $text = "Exercise 1. Read the text.\nTom has two friends. They play chess every Friday.\n\nExercise 4. Answer the questions about the text in Exercise 1.\ni How many friends does Tom have? a one b two\nii When do they play? a Friday b Sunday\n\nAnswer key – Unit 1\nExercise 4\ni b   ii a";
        $this->assertStringStartsWith('Answer key – Unit 1', AiImportService::answerKeyBlock($text));
        $import = AiImport::create(['course_id' => $this->course->id, 'lesson_id' => $this->lesson->id, 'title' => 'U1', 'status' => 'uploading', 'text' => $text,
            'created_by_type' => 'teacher', 'created_by_id' => $this->teacher->id, 'created_by_name' => 'Cô Lan']);
        Http::fake([self::URL => Http::sequence()
            ->pushResponse($this->ai(['title' => 'U1', 'sections' => [['title' => 'Exercise 4', 'type_code' => 'general.mcq_single', 'start_quote' => 'Exercise 4. Answer the questions', 'end_quote' => 'a Friday b Sunday',
                'audio_file' => null, 'needs_audio' => false, 'transcript' => null, 'points_per_question' => null, 'scale' => null, 'confidence' => 0.9, 'origin' => 'textbook', 'brief' => '']]]))
            ->pushResponse($this->ai(['content_json' => json_encode(['stimulus' => ['passage' => 'Tom has two friends. They play chess every Friday.'], 'questions' => [
                ['id' => 'i', 'prompt' => 'How many friends does Tom have?', 'options' => ['one', 'two'], 'answer' => 'B'],
                ['id' => 'ii', 'prompt' => 'When do they play?', 'options' => ['Friday', 'Sunday'], 'answer' => 'A']]]), 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null]))]);

        app(AiImportService::class)->start($import);
        app(AiJobRunner::class)->work(30);
        $import->refresh();

        $plan = json_encode(Http::recorded()[0][0]->data(), JSON_UNESCAPED_UNICODE);
        $build = json_encode(Http::recorded()[1][0]->data(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('KHÔNG phải bài tập', $plan);
        $this->assertStringNotContainsString('Answer key', $import->sections[0]['source'], 'Đoạn của phần không chứa đáp án…');
        $this->assertStringContainsString('Answer key – Unit 1', $build, '…nhưng lượt dựng nhận cả tài liệu để tra đáp án');
        $this->assertStringContainsString('Tom has two friends', $build, 'và bài đọc nằm ở bài khác');
        $this->assertStringContainsString('PHẢI dùng đúng đáp án đó', $build);
        $this->assertSame('Tom has two friends. They play chess every Friday.', $import->sections[0]['content']['stimulus']['passage']);
    }

    /** Nút "Dựng lại" có 2 chế độ: theo giáo trình (chép nguyên văn) / lấy giáo trình làm mẫu (AI tự viết lại). */
    public function test_rebuild_section_strict_or_using_textbook_as_model(): void
    {
        $import = AiImport::create(['course_id' => $this->course->id, 'lesson_id' => $this->lesson->id, 'title' => 'X', 'status' => 'review',
            'text' => 'Exercise 1. Ch00se. Sh3 g0es t0 sch00l. A. go B. goes', 'created_by_type' => 'teacher', 'created_by_id' => $this->teacher->id, 'created_by_name' => 'Cô Lan',
            'sections' => [['title' => 'Exercise 1', 'type_code' => 'general.mcq_single', 'start_quote' => 'Exercise 1.', 'end_quote' => 'B. goes', 'audio_file' => null, 'needs_audio' => false,
                'transcript' => null, 'points_per_question' => null, 'scale' => null, 'confidence' => 0.9, 'origin' => 'textbook', 'brief' => '', 'source' => '', 'flags' => [], 'plan_flags' => [],
                'status' => 'ok', 'content' => $this->mcqContent('old')]]]);
        Http::fake([self::URL => $this->ai(['content_json' => json_encode($this->mcqContent('She goes to school.')), 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null])]);
        $t = $this->actingAs($this->teacher, 'teacher');
        $t->get(route('teacher.ai-import.show', [$this->course, $import]))->assertOk()->assertSee('Dựng theo giáo trình')->assertSee('Dựng lại, lấy giáo trình làm mẫu');

        // Lấy giáo trình làm mẫu
        $t->post(route('teacher.ai-import.section.rebuild', [$this->course, $import, 0]), ['style' => 'model'])->assertSessionHas('status', fn ($m) => str_contains($m, 'làm mẫu'));
        app(AiJobRunner::class)->work(30);
        $import->refresh();
        $this->assertSame('model', $import->sections[0]['build_style']);
        $this->assertSame('review', $import->status);
        $this->assertTrue(collect($import->sections[0]['flags'])->contains(fn ($f) => str_contains($f, 'tự dựng lại theo mẫu')));
        $last = json_encode(collect(Http::recorded())->last()[0]->data(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('DỰNG LẠI, LẤY GIÁO TRÌNH LÀM MẪU', $last);
        $t->get(route('teacher.ai-import.show', [$this->course, $import]))->assertSee('tự dựng theo mẫu');

        // Theo giáo trình (mặc định) — gọi bằng AJAX từ màn duyệt: cả bài vẫn "Chờ duyệt", chỉ phần này "đang dựng"
        $t->postJson(route('teacher.ai-import.section.rebuild', [$this->course, $import, 0]), ['style' => 'strict'])->assertOk()->assertJson(['ok' => true, 'section' => 0]);
        $this->assertSame('review', $import->fresh()->status);
        $this->assertSame('pending', $import->fresh()->sections[0]['status']);
        $t->get(route('teacher.ai-import.show', [$this->course, $import]))->assertOk()->assertSee('AI đang dựng lại riêng phần này')->assertSee('Lưu thành bài tập tuỳ chỉnh');
        $t->postJson(route('teacher.ai-import.tick', [$this->course, $import]))->assertOk()->assertJson(['status' => 'review', 'sections' => ['ok']]);

        // Dựng lại toàn bộ: mọi phần "đang dựng", cả bài vẫn "Chờ duyệt"
        $t->postJson(route('teacher.ai-import.rebuild-all', [$this->course, $import]), ['style' => 'strict'])->assertOk()->assertJson(['ok' => true, 'sections' => [0]]);
        $this->assertSame('review', $import->fresh()->status);
        $this->assertSame('pending', $import->fresh()->sections[0]['status']);
        $t->postJson(route('teacher.ai-import.tick', [$this->course, $import]))->assertOk()->assertJson(['status' => 'review', 'sections' => ['ok']]);
        $t->get(route('teacher.ai-import.show', [$this->course, $import]))->assertSee('Dựng lại toàn bộ');
        $import->refresh();
        $this->assertSame('strict', $import->sections[0]['build_style']);
        $this->assertFalse(collect($import->sections[0]['flags'])->contains(fn ($f) => str_contains($f, 'tự dựng lại theo mẫu')));
        $this->assertStringNotContainsString('LẤY GIÁO TRÌNH LÀM MẪU', json_encode(collect(Http::recorded())->last()[0]->data(), JSON_UNESCAPED_UNICODE));
    }

    /**
     * "AI tự tạo bài tập": nhập số CÂU HỎI LỚN (số phần) từng kỹ năng → AI tạo bài MỚI → lưu Nháp thành MỘT bài tập.
     * Server chốt đúng số phần: bỏ kỹ năng không yêu cầu, thừa thì bớt, thiếu thì nhân thêm phần; số câu nhỏ trong phần không đếm.
     */
    public function test_ai_compose_new_exercises_with_chosen_question_counts(): void
    {
        $this->lesson->update(['summary' => 'Talking about friends', 'content' => "Từ vựng: make friends, hang out\nNgữ pháp: present simple"]);
        $sec = fn ($t, $code, $brief) => ['title' => $t, 'type_code' => $code, 'start_quote' => '', 'end_quote' => '', 'audio_file' => null, 'needs_audio' => false,
            'transcript' => null, 'points_per_question' => null, 'scale' => null, 'confidence' => 0.9, 'origin' => 'textbook', 'brief' => $brief];
        $q = fn ($n) => collect(range(1, $n))->map(fn ($i) => ['id' => (string) $i, 'prompt' => "Q{$i}?", 'options' => ['Tom', 'Ann'], 'answer' => 'A'])->all();
        $build = fn ($content) => $this->ai(['content_json' => json_encode($content), 'flags' => [], 'image_page' => null, 'image_box' => null, 'image_refs' => null]);
        Http::fake([self::URL => Http::sequence()
            ->pushResponse($this->ai(['title' => 'Friends', 'sections' => [
                $sec('Đọc 1', 'ielts.reading.mcq_single', 'Bài đọc về bạn bè'),
                $sec('Đọc 2', 'ielts.reading.mcq_single', 'Bài đọc về trường'),
                $sec('Đọc 3', 'ielts.reading.mcq_single', 'Thừa'),                       // yêu cầu 2 phần Đọc → bớt
                $sec('Nói', 'general.speak_answer', 'Nói về bạn thân'),                  // yêu cầu 2 phần Nói → nhân thêm 1
                $sec('Nghe', 'ielts.listening.mcq', 'Nghe thêm'),                        // không yêu cầu → bỏ
            ]]))
            ->pushResponse($build(['stimulus' => ['passage' => 'Tom hangs out with friends.'], 'questions' => $q(4)]))
            ->pushResponse($build(['stimulus' => ['passage' => 'Ann likes her school.'], 'questions' => $q(2)]))
            ->pushResponse($build(['prompt' => 'Talk about your best friend.']))
            ->pushResponse($build(['prompt' => 'Describe a friend you met at school.']))]);

        $t = $this->actingAs($this->teacher, 'teacher');
        $t->get(route('teacher.lessons.edit', [$this->course, $this->lesson]))->assertOk()->assertSee('AI tự tạo bài tập')->assertSee('câu hỏi lớn')->assertSee('Từ vựng &amp; ngữ pháp', false);
        $t->post(route('teacher.lessons.ai-compose', [$this->course, $this->lesson]), ['counts' => ['reading' => 0, 'listening' => 0]])->assertSessionHasErrors('counts');
        $t->post(route('teacher.lessons.ai-compose', [$this->course, $this->lesson]), ['counts' => ['reading' => 2, 'listening' => 0, 'speaking' => 2, 'writing' => 0, 'vocab' => 0]])
            ->assertSessionHas('status', fn ($m) => str_contains($m, '4 câu hỏi lớn'));
        $import = AiImport::sole();
        $this->assertSame('compose', $import->mode);
        $this->assertSame(['reading' => 2, 'speaking' => 2], $import->plan['compose']);

        app(AiJobRunner::class)->work(30);
        $import->refresh();
        $plan = json_encode(Http::recorded()[0][0]->data(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('SỐ CÂU HỎI LỚN YÊU CẦU (phải đúng): Đọc: 2 câu hỏi lớn; Nói: 2 câu hỏi lớn', $plan);
        $this->assertStringContainsString('Từ vựng: make friends', $plan, 'Bài không gắn Unit → dựa vào ghi chú của bài học');
        Http::assertSentCount(5);   // 1 lượt lập kế hoạch + 4 phần, không gửi sửa vì số câu nhỏ
        $this->assertSame(['Đọc 1', 'Đọc 2', 'Nói', 'Nói (2)'], array_column($import->sections, 'title'));
        $this->assertTrue(collect($import->sections)->every(fn ($s) => $s['origin'] === 'new' && $s['status'] === 'ok'));
        $this->assertSame('saved', $import->status);

        $ex = $this->lesson->exercises()->with('items')->sole();   // 1 bài tập dù 2 kỹ năng
        $this->assertSame('draft', $ex->status);
        $this->assertSame('AI tạo — '.$this->lesson->title, $ex->title);
        $this->assertSame('mixed', $ex->skill);
        $this->assertCount(4, $ex->items, '4 câu hỏi lớn = 4 phần (phần đọc có 4 và 2 câu nhỏ)');
        $this->assertSame('Cô Lan', Teacher::find($ex->created_by_id)?->name);
    }

    /** Bản AI dựng chưa lưu (đang dựng / chờ duyệt / lỗi) phải hiện ở trang bài học, có nút duyệt & xoá; giáo viên chỉ thấy bản của mình. */
    public function test_pending_ai_imports_show_on_lesson_page(): void
    {
        $base = ['course_id' => $this->course->id, 'lesson_id' => $this->lesson->id, 'mode' => 'extract', 'source_name' => 'de-nghe.docx'];
        $review = AiImport::create($base + ['status' => 'review', 'title' => 'Đề nghe Unit 3', 'created_by_type' => 'teacher', 'created_by_id' => $this->teacher->id, 'created_by_name' => 'Cô Lan',
            'sections' => [['status' => 'ok'], ['status' => 'ok'], ['status' => 'invalid']]]);
        AiImport::create($base + ['status' => 'saved', 'title' => 'Bản đã lưu', 'created_by_type' => 'teacher', 'created_by_id' => $this->teacher->id, 'created_by_name' => 'Cô Lan']);
        AiImport::create($base + ['status' => 'failed', 'title' => 'Bản của admin', 'error' => 'AI lỗi', 'created_by_type' => 'user', 'created_by_id' => 99, 'created_by_name' => 'Admin']);

        $t = $this->actingAs($this->teacher, 'teacher');
        $t->get(route('teacher.lessons.edit', [$this->course, $this->lesson]))->assertOk()
            ->assertSee('Chờ duyệt')->assertSee('Đề nghe Unit 3')->assertSee('2/3 phần hợp lệ')->assertSee('Duyệt &amp; lưu', false)
            ->assertSee(route('teacher.ai-import.show', [$this->course, $review]))
            ->assertDontSee('Bản đã lưu')->assertDontSee('Bản của admin');

        $owner = \App\Models\User::forceCreate(['id' => 1, 'name' => 'Owner', 'email' => 'admin@gmail.com', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $this->actingAs($owner)->get(route('admin.courses.lessons.edit', [$this->course, $this->lesson]))->assertOk()
            ->assertSee('Đề nghe Unit 3')->assertSee('Bản của admin')->assertSee('AI lỗi')->assertDontSee('Bản đã lưu');

        $this->actingAs($this->teacher, 'teacher')->delete(route('teacher.ai-import.destroy', [$this->course, $review]), ['from' => 'lesson'])
            ->assertRedirect(route('teacher.lessons.edit', [$this->course, $this->lesson]));
        $this->assertModelMissing($review);
    }

    /** Danh sách bài tập của bài học: đổi trạng thái nhanh (Nháp / Xuất bản / Ẩn), huỷ bài. */
    public function test_quick_status_and_cancel_from_lesson_list(): void
    {
        $ex = CustomExercise::create(['course_id' => $this->course->id, 'lesson_id' => $this->lesson->id, 'title' => 'Bài A', 'status' => 'draft', 'created_by_type' => 'user', 'created_by_id' => null, 'created_by_name' => 'AI']);
        $t = $this->actingAs($this->teacher, 'teacher');
        $t->get(route('teacher.lessons.edit', [$this->course, $this->lesson]))->assertSee('name="status"', false)->assertSee('Xuất bản');
        $t->patch(route('teacher.custom.status', [$this->course, $ex]), ['status' => 'published'])->assertSessionHasErrors('status');   // chưa có phần nào
        $ex->items()->create(['position' => 1, 'type_code' => 'general.mcq_single', 'content' => Registry::get('general.mcq_single')->sample]);
        $t->patch(route('teacher.custom.status', [$this->course, $ex]), ['status' => 'published'])->assertSessionHas('status');
        $this->assertSame('published', $ex->fresh()->status);
        $t->patch(route('teacher.custom.status', [$this->course, $ex]), ['status' => 'hidden']);
        $this->assertSame('hidden', $ex->fresh()->status);
        $t->patch(route('teacher.custom.status', [$this->course, $ex]), ['status' => 'xyz'])->assertSessionHasErrors('status');

        // Giáo viên không được sửa khoá → không đổi được
        $other = Teacher::create(['name' => 'B', 'email' => 'b@gv.vn', 'password' => 'secret123']);
        $this->actingAs($other, 'teacher')->patch(route('teacher.custom.status', [$this->course, $ex]), ['status' => 'published'])->assertForbidden();

        // Huỷ (xoá) bài
        $this->actingAs($this->teacher, 'teacher')->delete(route('teacher.custom.destroy', [$this->course, $ex]))->assertRedirect(route('teacher.lessons.edit', [$this->course, $this->lesson]));
        $this->assertNull($ex->fresh());
    }
}
