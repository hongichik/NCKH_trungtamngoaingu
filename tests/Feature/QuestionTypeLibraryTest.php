<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use App\Support\Exercise\Scorer;
use App\Support\QuestionTypes\Engine;
use App\Support\QuestionTypes\Engines;
use App\Support\QuestionTypes\LegacyAdapter;
use App\Support\QuestionTypes\Matcher;
use App\Support\QuestionTypes\QuestionType;
use App\Support\QuestionTypes\Registry;
use App\Support\QuestionTypes\Scoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** V2 bước 1 — Thư viện dạng bài. */
class QuestionTypeLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // không bao giờ đụng file thật trong storage/app/private
        Registry::flush();
    }

    private function owner(): User
    {
        return User::forceCreate(['id' => 1, 'name' => 'Owner', 'email' => 'admin@gmail.com', 'password' => bcrypt('x'), 'is_admin' => 1]);
    }

    /** Chuyển qua JSON như câu trả lời thật gửi từ trình duyệt. */
    private function viaJson(array $a): array
    {
        return json_decode(json_encode($a), true);
    }

    // ---------------------------------------------------------------- danh mục

    public function test_library_has_at_least_60_types_covering_every_engine_and_standard(): void
    {
        $all = collect(Registry::all());

        $this->assertGreaterThanOrEqual(60, $all->count());
        $this->assertEqualsCanonicalizing(array_keys(Engines::MAP), $all->pluck('engine')->unique()->values()->all());
        foreach (array_keys(QuestionType::STANDARDS) as $std) {
            $this->assertTrue($all->contains('standard', $std), "Thiếu chuẩn đề {$std}");
        }
        foreach (array_keys(Question::TYPES) as $v1) {
            $this->assertNotNull(Registry::forLegacy($v1), "Loại V1 {$v1} chưa vào thư viện");
        }
    }

    public function test_every_type_sample_validates_and_grades_full_marks_for_the_perfect_answer(): void
    {
        foreach (Registry::all() as $code => $t) {
            $engine = $t->engine();
            $this->assertSame([], $engine->validate($t->sample, $t), "Mẫu {$code} không hợp lệ");

            $graded = Registry::grade($t, $t->sample, $this->viaJson($engine->perfectAnswer($t->sample, $t)));
            if ($t->grading === 'auto') {
                $this->assertFalse($graded['pending'], $code);
                $this->assertSame(100.0, $graded['score']['percent'], "Đáp án đúng của {$code} không đạt điểm tối đa: ".json_encode($graded['parts'], JSON_UNESCAPED_UNICODE));
            } else {
                $this->assertTrue($graded['pending'], "{$code} phải chờ AI / giáo viên chấm");
                $this->assertSame('Chờ chấm', $graded['score']['scaled']);
            }

            $empty = Registry::grade($t, $t->sample, []);
            $this->assertSame(0.0, $empty['score']['earned'], "{$code}: bỏ trống mà vẫn có điểm");
            $this->assertFalse($empty['pending'], "{$code}: bỏ trống không được chờ chấm");

            // Dữ liệu gửi xuống trình duyệt không lộ đáp án.
            $prepared = json_encode($engine->prepare($t->sample, $t, 'seed'), JSON_UNESCAPED_UNICODE);
            foreach (['"answer"', '"answers"', '"correction"', '"sample_answer"'] as $leak) {
                $this->assertStringNotContainsString($leak, $prepared, "{$code} lộ {$leak} ra giao diện");
            }
        }
    }

    public function test_type_codes_are_unique_and_well_formed(): void
    {
        foreach (Registry::all() as $code => $t) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(\.[a-z0-9_]+)+$/', $code);
            $this->assertArrayHasKey($t->skill, QuestionType::SKILLS, $code);
            $this->assertArrayHasKey($t->lang, QuestionType::LANGS, $code);
            $this->assertArrayHasKey(Registry::scoring($t)['scale'], Scoring::SCALES, $code);
        }
    }

    // ---------------------------------------------------------------- bộ chấm theo quy tắc

    public function test_matcher_rules(): void
    {
        $this->assertTrue(Matcher::check('  The MUSEUM. ', ['(the) museum'])['correct']);
        $this->assertTrue(Matcher::check('museum', ['(the) museum'])['correct']);
        $this->assertTrue(Matcher::check('fifteen', ['15'])['correct']);
        $this->assertTrue(Matcher::check('twenty-five', ['25'])['correct']);
        $this->assertTrue(Matcher::check('3000', ['3,000'])['correct']);
        $this->assertTrue(Matcher::check('one hundred and five', ['105'])['correct']);
        $this->assertTrue(Matcher::check('eight fifteen', ['8:15'])['correct']);
        $this->assertTrue(Matcher::check('don’t', ["don't"])['correct']);
        $this->assertTrue(Matcher::check('well known', ['well-known'])['correct']);
        $this->assertFalse(Matcher::check('musem', ['museum'])['correct']);
        $this->assertFalse(Matcher::check('', ['x'])['correct']);

        // Giới hạn số từ; IELTS: con số không tính vào giới hạn.
        $r = Matcher::check('the big old museum', ['the big old museum'], 'en', ['max_words' => 2]);
        $this->assertFalse($r['correct']);
        $this->assertSame('Quá 2 từ', $r['reason']);
        $this->assertTrue(Matcher::check('Studio 2', ['Studio 2'], 'en', ['max_words' => 1, 'numbers_free' => true])['correct']);

        // Tiếng Trung: bỏ khoảng trắng và dấu câu.
        $this->assertTrue(Matcher::check('我 爱 你。', ['我爱你'], 'zh')['correct']);
        $this->assertSame(1.0, Matcher::wordAccuracy('the museum opens at nine', 'The museum opens at 9.'));
        $this->assertEqualsWithDelta(0.8, Matcher::wordAccuracy('the museum open at nine', 'the museum opens at nine'), 0.001);
    }

    public function test_partial_credit_penalty_and_scales(): void
    {
        $multi = Registry::get('ielts.reading.mcq_multi');
        $one = Registry::grade($multi, $multi->sample, ['1-2' => ['A', 'B']]);    // 1 đúng / 2
        $this->assertSame(1.0, $one['score']['earned']);
        $this->assertSame(2.0, $one['score']['max']);
        $over = Registry::grade($multi, $multi->sample, ['1-2' => ['A', 'B', 'C']]); // chọn thừa mất 1 ý
        $this->assertSame(1.0, $over['score']['earned']);
        $strict = Registry::grade($multi, $multi->sample, ['1-2' => ['A', 'B']], ['partial' => false]);
        $this->assertSame(0.0, $strict['score']['earned']);

        $mcq = Registry::get('general.mcq_single');
        $pen = Registry::grade($mcq, $mcq->sample, ['1' => 'B', '2' => 'A'], ['points' => 0.25, 'penalty' => 0.1, 'scale' => 'raw']);
        $this->assertSame(0.15, $pen['score']['earned']);   // 0,25 − 0,1
        $this->assertSame('0.15 / 0.5', $pen['score']['scaled']);
        $blank = Registry::grade($mcq, $mcq->sample, ['1' => 'B'], ['penalty' => 1]);
        $this->assertSame(1.0, $blank['score']['earned'], 'Bỏ trống không bị trừ điểm');

        $this->assertSame(7.0, Scoring::ieltsBand('ielts_listening', 30));
        $this->assertSame(6.0, Scoring::ieltsBand('ielts_reading_academic', 23));
        $this->assertSame(6.0, Scoring::ieltsBand('ielts_reading_general', 30));
        $this->assertSame('Band 9', Scoring::convert('ielts_listening', 100));
        $this->assertSame(6.5, Scoring::ieltsOverall([6, 6.5, 6.5, 7]));
        $this->assertSame(6.5, Scoring::ieltsOverall([6, 6, 6.5, 6.5]));  // 6.25 → 6.5
        $this->assertSame(495, Scoring::toeic(100, true));
        $this->assertSame(5, Scoring::toeic(0, false));
        $this->assertSame('7.0 — Bậc 4 (B2)', Scoring::convert('vstep', 70));
        $this->assertSame('60 / 100 — Đạt', Scoring::convert('hsk', 60));
        $this->assertSame('8.5 / 10', Scoring::convert('ten', 85));
    }

    public function test_engine_specific_grading(): void
    {
        // Sửa lỗi: chọn đúng chỗ 0,5 + sửa đúng 0,5.
        $err = Registry::get('general.error_correction');
        $r = Registry::grade($err, $err->sample, ['1' => ['index' => 2, 'fix' => 'went'], '2' => ['index' => 0, 'fix' => 'for']]);
        $this->assertSame(0.5, $r['score']['earned']);

        // Gạch chân: chọn thừa mất ý.
        $hl = Registry::get('general.highlight');
        $perfect = $hl->engine()->perfectAnswer($hl->sample, $hl)['selected'];
        $r = Registry::grade($hl, $hl->sample, ['selected' => array_merge($perfect, [0])]);
        $this->assertSame(2.0, $r['score']['earned']);

        // Sắp xếp: điểm từng phần theo vị trí; V1 sắp xếp thì phải đúng hết.
        $ord = Registry::get('general.order_sentences');
        $this->assertSame(50.0, Registry::grade($ord, $ord->sample, ['order' => [0, 1, 3, 2]])['score']['percent']);
        $v1 = Registry::get('v1.ordering');
        $this->assertSame(0.0, Registry::grade($v1, $v1->sample, ['order' => [0, 2, 1]])['score']['percent']);

        // Chọn điểm trên ảnh: trong bán kính mới đúng.
        $hot = Registry::get('general.hotspot');
        $this->assertSame(50.0, Registry::grade($hot, $hot->sample, ['1' => ['x' => 22, 'y' => 27], '2' => ['x' => 10, 'y' => 10]])['score']['percent']);

        // Viết lại câu: học viên xoá phần mở đầu vẫn được chấm đúng.
        $rw = Registry::get('general.rewrite');
        $this->assertSame(1.0, Registry::grade($rw, $rw->sample, ['2' => 'was built in 1990.'])['score']['earned']);

        // Chép chính tả: điểm theo tỉ lệ từ đúng.
        $dict = Registry::get('general.dictation');
        $r = Registry::grade($dict, $dict->sample, ['1' => 'The museum open at nine in the morning.']);
        $this->assertEqualsWithDelta(0.88, $r['parts'][0]['earned'], 0.01);

        // Ghép từ tiếng Trung: không có dấu cách.
        $wo = Registry::get('hsk.word_order');
        $this->assertSame(1.0, Registry::grade($wo, $wo->sample, ['1' => '我把作业写完了'])['score']['earned']);

        // Viết: bài thiếu từ được đánh dấu, chưa dùng từ khoá được báo.
        $hw = Registry::get('hsk.sentence_with_word');
        $r = Registry::grade($hw, $hw->sample, ['text' => '我喜欢学习。']);
        $this->assertTrue($r['pending']);
        $this->assertStringContainsString('chưa dùng: 一边', $r['parts'][0]['reason']);
    }

    public function test_validation_catches_broken_content(): void
    {
        $tfng = Registry::get('ielts.reading.tfng');
        $this->assertNotEmpty($tfng->engine()->validate(['questions' => [['id' => '1', 'prompt' => 'x', 'answer' => 'MAYBE']]], $tfng));
        $gap = Registry::get('general.gap_typed');
        $this->assertNotEmpty($gap->engine()->validate(['text' => 'A [[1]] and [[2]]', 'answers' => ['1' => 'x']], $gap));
        $this->assertNotEmpty($gap->engine()->validate(['answers' => []], $gap));
        $kwt = Registry::get('cambridge.key_word_transformation');
        $this->assertNotEmpty($kwt->engine()->validate(['questions' => [['id' => '1', 'prompt' => 'x', 'keyword' => 'last', 'answers' => ['first time']]]], $kwt));
    }

    // ---------------------------------------------------------------- bài V1 không bị ảnh hưởng

    public function test_legacy_v1_questions_grade_the_same_through_the_library(): void
    {
        $scorer = new Scorer;
        $cases = [
            ['mcq', 'B', ['A. one', 'B. two', 'C. three'], ['B', 'b', 'B. two', 'A', '']],
            ['cloze', 'C', ['A. go', 'B. goes', 'C. went'], ['C', 'A', '']],
            ['true_false', 'True', null, ['True', 'true', 'False', '']],
            ['ordering', 'c-a-b', ['a. first', 'b. second', 'c. third'], ['c-a-b', 'C, A, B', 'a-b-c', '']],
            ['gap_fill', 'library', null, ['library', 'Library.', 'librery', '']],
        ];
        foreach ($cases as [$type, $answer, $options, $givens]) {
            $q = new Question(['code' => 'Q1', 'type' => $type, 'prompt' => 'Prompt', 'options' => $options, 'answer' => $answer]);
            foreach ($givens as $given) {
                $old = $scorer->isCorrect($type, $given, $answer, 'en');
                ['type' => $t, 'content' => $content, 'answer' => $a] = LegacyAdapter::convert($q, $given);
                $this->assertSame([], $t->engine()->validate($content, $t), "{$type}: nội dung chuyển đổi không hợp lệ");
                $new = Registry::grade($t, $content, $this->viaJson($a))['score']['percent'] === 100.0;
                $this->assertSame($old, $new, "{$type} / \"{$given}\": V1 chấm ".var_export($old, true).', thư viện chấm '.var_export($new, true));
            }
        }

        $tr = new Question(['code' => 'W1', 'type' => 'transformation', 'prompt' => 'He began to learn French in 2020.', 'answer' => 'He has learned French since 2020.', 'meta' => ['start' => 'He has', 'accepted_variants' => ['He has been learning French since 2020.']]]);
        ['type' => $t, 'content' => $c, 'answer' => $a] = LegacyAdapter::convert($tr, 'He has been learning French since 2020.');
        $this->assertSame(100.0, Registry::grade($t, $c, $a)['score']['percent']);

        foreach (['paragraph', 'speaking', 'read_aloud'] as $open) {
            $q = new Question(['code' => 'S1', 'type' => $open, 'prompt' => 'Talk about it.', 'answer' => '', 'meta' => ['min_words' => 10]]);
            ['type' => $t, 'content' => $c] = LegacyAdapter::convert($q, 'x');
            $this->assertSame([], $t->engine()->validate($c, $t), $open);
        }
    }

    // ---------------------------------------------------------------- trang thư viện

    public function test_only_the_system_owner_can_open_the_library(): void
    {
        $owner = $this->owner();
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'boss@x.x', 'password' => bcrypt('x'), 'is_admin' => 1]);

        $this->get(route('admin.system.question-types.index'))->assertRedirect();
        $this->actingAs($admin)->get(route('admin.system.question-types.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.system.question-types.show', 'ielts.reading.tfng'))->assertForbidden();
        $this->actingAs($admin)->postJson(route('admin.system.question-types.grade', 'ielts.reading.tfng'), ['answer' => []])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.system.question-types.toggle', 'ielts.reading.tfng'))->assertForbidden();

        $this->actingAs($owner)->get(route('admin.system.question-types.index'))
            ->assertOk()->assertSee('Thư viện dạng bài')->assertSee('True / False / Not Given')->assertSee('HSKK');
        $this->actingAs($owner)->get(route('admin.system.index', ['tab' => 'question-types']))->assertRedirect(route('admin.system.question-types.index'));
        $this->actingAs($owner)->get(route('admin.system.question-types.index', ['standard' => 'toeic']))
            ->assertOk()->assertSee('TOEIC Part 5')->assertDontSee('HSKK — Đọc to');
        $this->actingAs($owner)->get(route('admin.system.question-types.show', 'khong.ton.tai'))->assertNotFound();
    }

    public function test_every_type_page_renders_its_sample_without_leaking_answers(): void
    {
        $owner = $this->owner();
        foreach (Registry::all() as $code => $t) {
            $html = $this->actingAs($owner)->get(route('admin.system.question-types.show', $code))->assertOk()->getContent();
            $this->assertStringContainsString('id="qt-try"', $html, $code);
            $this->assertStringContainsString('data-engine="'.$t->engine.'"', $html, $code);
        }
        // Lời thoại bài nghe không hiện sẵn dưới dạng chữ trong phần làm bài.
        $html = $this->actingAs($owner)->get(route('admin.system.question-types.show', 'general.dictation'))->getContent();
        $try = substr($html, strpos($html, 'id="qt-try"'), 3000);
        $this->assertStringNotContainsString('The museum opens at nine', $try);
    }

    public function test_try_grade_toggle_scoring_and_custom_json(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->postJson(route('admin.system.question-types.grade', 'ielts.reading.tfng'), ['answer' => ['1' => 'FALSE', '2' => 'NOT GIVEN', '3' => 'FALSE']])
            ->assertOk()->assertJsonPath('score.earned', 2)->assertJsonPath('score.max', 3)->assertJsonPath('parts.2.correct', false);

        // Bật / tắt lưu CSDL.
        $this->post(route('admin.system.question-types.toggle', 'toeic.part1_photographs'))->assertRedirect();
        $this->assertDatabaseHas('question_types', ['code' => 'toeic.part1_photographs', 'enabled' => false]);
        Registry::flush();
        $this->assertFalse(Registry::enabled('toeic.part1_photographs'));
        $this->get(route('admin.system.question-types.index', ['enabled' => '0']))->assertSee('TOEIC Part 1')->assertDontSee('TOEIC Part 5');
        $this->post(route('admin.system.question-types.toggle', 'toeic.part1_photographs'));
        Registry::flush();
        $this->assertTrue(Registry::enabled('toeic.part1_photographs'));

        // Chỉnh cách tính điểm mặc định.
        $this->post(route('admin.system.question-types.scoring', 'general.mcq_single'), ['points' => 0.5, 'penalty' => 0, 'scale' => 'raw'])->assertRedirect();
        Registry::flush();
        $this->assertEquals(['points' => 0.5, 'partial' => false, 'penalty' => 0, 'scale' => 'raw'], Registry::scoring(Registry::get('general.mcq_single')));
        $this->postJson(route('admin.system.question-types.grade', 'general.mcq_single'), ['answer' => ['1' => 'B', '2' => 'B']])->assertJsonPath('score.scaled', '1 / 1');
        $this->post(route('admin.system.question-types.scoring', 'general.mcq_single'), ['reset' => 1]);
        Registry::flush();
        $this->assertSame('ten', Registry::scoring(Registry::get('general.mcq_single'))['scale']);
        $this->post(route('admin.system.question-types.scoring', 'general.mcq_single'), ['points' => 1, 'penalty' => 0, 'scale' => 'khong-co'])->assertSessionHasErrors('scale');

        // Dựng thử với JSON tự soạn: hợp lệ thì hiển thị, sai lược đồ thì báo lỗi.
        $json = json_encode(['instructions' => 'Custom!', 'questions' => [['id' => '9', 'prompt' => 'Zebras are blue.', 'answer' => 'FALSE']]]);
        $this->post(route('admin.system.question-types.show', 'ielts.reading.tfng'), ['content' => $json])->assertOk()->assertSee('Zebras are blue.')->assertSee('nội dung tự soạn');
        $this->postJson(route('admin.system.question-types.grade', 'ielts.reading.tfng'), ['content' => $json, 'answer' => ['9' => 'FALSE']])->assertJsonPath('score.percent', 100);
        $this->post(route('admin.system.question-types.show', 'ielts.reading.tfng'), ['content' => '{"questions": [{"id": "1", "answer": "MAYBE"}]}'])
            ->assertOk()->assertSee('không nằm trong các lựa chọn');
        $this->post(route('admin.system.question-types.show', 'ielts.reading.tfng'), ['content' => '{oops'])->assertOk()->assertSee('JSON không hợp lệ');
        $this->postJson(route('admin.system.question-types.grade', 'ielts.reading.tfng'), ['content' => '{oops', 'answer' => []])->assertStatus(422);
    }

    /** Khung ngoài của bài không được trùng class với CSS / JS bên trong (lỗi cũ: "qt-choice" làm câu hỏi dàn thành cột ngang). */
    public function test_root_class_never_collides_with_inner_css_or_js_selectors(): void
    {
        $css = file_get_contents(public_path('css/qtypes.css'));
        $js = file_get_contents(public_path('js/qtypes.js'));
        $owner = $this->owner();
        foreach (Registry::all() as $code => $t) {
            $html = $this->actingAs($owner)->get(route('admin.system.question-types.show', $code))->getContent();
            preg_match('/<div class="(qt [^"]+)" id="qt-try"/', $html, $m);
            $this->assertNotEmpty($m, $code);
            foreach (array_slice(explode(' ', $m[1]), 1) as $cls) {
                $this->assertDoesNotMatchRegularExpression('/\.'.preg_quote($cls, '/').'(?![\w-])/', $css, "{$code}: class khung ngoài .{$cls} trùng CSS");
                $this->assertDoesNotMatchRegularExpression('/\.'.preg_quote($cls, '/').'(?![\w-])/', $js, "{$code}: class khung ngoài .{$cls} trùng JS");
            }
        }
    }

    /** Lựa chọn AI / giáo viên ghi sẵn "A. …" không bị hiện thành "A. A. …". */
    public function test_option_labels_are_not_duplicated(): void
    {
        $t = Registry::get('ielts.reading.mcq_single');
        $content = ['questions' => [['id' => '1', 'prompt' => 'How long?', 'options' => ['A. Six months', 'B) Eighteen months', '(C) Three years', 'd. Nine years', 'Apple pie'], 'answer' => 'C']]];
        $this->assertSame([], $t->engine()->validate($content, $t));
        $opts = array_column(Engine::options($content['questions'][0]['options']), 'text');
        $this->assertSame(['Six months', 'Eighteen months', 'Three years', 'Nine years', 'Apple pie'], $opts);
        $html = view('question-types.render', ['type' => $t, 'content' => $content, 'uid' => 'x'])->render();
        $this->assertStringContainsString('<b>A.</b> Six months', $html);
        $this->assertStringNotContainsString('A. A.', $html);
        $this->assertSame(100.0, Registry::grade($t, $content, ['1' => 'C'])['score']['percent']);
        // Không bỏ nhầm chữ không phải nhãn
        $this->assertSame('A lot of people', Engine::stripLabel('A lot of people', 'A'));
        $this->assertSame('B.', Engine::stripLabel('B.', 'B'));
    }
}
