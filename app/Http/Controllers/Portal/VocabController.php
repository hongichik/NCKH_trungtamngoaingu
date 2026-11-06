<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\Progress\Gamification;
use App\Support\Vocab\ChineseDrill;
use App\Support\Vocab\Deck;
use App\Support\Vocab\Flashcards;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Ôn từ vựng bằng flashcard + tập viết chữ Hán (Unit được giao trong lớp + bài học đang mở của khoá). */
class VocabController extends Controller
{
    public function __construct(private Flashcards $cards) {}

    private function student(): Student
    {
        return Auth::guard('student')->user();
    }

    /** Bộ thẻ của học viên: "u12" (Unit giáo trình), "l34" (bài học), số = Unit. */
    private function unit(string|int $id): Deck
    {
        $deck = $this->cards->deck($this->student(), $id);
        abort_unless($deck, 404);

        return $deck;
    }

    public function index(): View
    {
        $summary = $this->cards->summary($this->student());

        return view('portal.vocab.index', ['summary' => $summary, 'due' => $summary->sum('due'), 'fresh' => $summary->sum(fn ($s) => $s['total'] - $s['learned'])]);
    }

    public function study(Request $request): View
    {
        $id = $request->input('deck', $request->input('unit'));
        $unit = filled($id) ? $this->unit((string) $id) : null;

        return view('portal.vocab.study', ['unit' => $unit, 'cards' => $this->cards->session($this->student(), $unit?->id), 'grades' => Flashcards::GRADES]);
    }

    public function review(Request $request): JsonResponse
    {
        $data = $request->validate([
            'deck' => ['required_without:unit_id', 'nullable', 'string', 'max:20'],
            'unit_id' => ['required_without:deck', 'nullable', 'integer'],
            'key' => ['required', 'string', 'size:40'],
            'grade' => ['required', 'in:'.implode(',', array_keys(Flashcards::GRADES))],
        ]);
        $r = $this->cards->grade($this->student(), $this->unit((string) ($data['deck'] ?? $data['unit_id'])), $data['key'], $data['grade']);
        $xp = app(Gamification::class)->award($this->student(), 'vocab', in_array($data['grade'], ['good', 'easy'], true) ? 2 : 1);

        return response()->json(['ok' => true, 'interval_days' => $r->interval_days, 'due_at' => $r->due_at->toIso8601String(), 'xp' => $xp['points'], 'badges' => $this->badgeNames($xp['badges'])]);
    }

    private function badgeNames(array $codes): array
    {
        return array_map(fn ($c) => Gamification::BADGES[$c][0], $codes);
    }

    /** Luyện tiếng Trung: thanh điệu, nghe chọn pinyin, pinyin → chữ, bộ thủ, nghe viết (máy tự tạo, chấm trên trình duyệt). */
    public function drill(string $unitId): View
    {
        $unit = $this->unit($unitId);
        abort_unless($unit->isChinese(), 404);

        return view('portal.vocab.drill', ['unit' => $unit, 'questions' => app(ChineseDrill::class)->build(array_values($this->cards->words($unit)))]);
    }

    /** Nộp kết quả luyện tiếng Trung / tập viết → XP. */
    public function progress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:drill,hanzi'],
            'unit_id' => ['required', 'regex:/^[ul]?\d+$/'],
            'correct' => ['required', 'integer', 'min:0', 'max:50'],
            'total' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        $unit = $this->unit((string) $data['unit_id']);
        abort_unless($unit->isChinese(), 404);
        $g = app(Gamification::class);
        $correct = min($data['correct'], $data['total']);
        $xp = $g->award($this->student(), $data['kind'], $data['kind'] === 'drill' ? $correct * 2 : $correct * 3, ['deck' => $unit->id]);
        if ($data['kind'] === 'drill' && $data['total'] >= 5 && $correct === $data['total'] && $g->grant($this->student(), 'drill_perfect')) {
            $xp['badges'][] = 'drill_perfect';
        }

        return response()->json(['ok' => true, 'xp' => $xp['points'], 'badges' => $this->badgeNames($xp['badges'])]);
    }

    /** Tập viết chữ Hán theo thứ tự nét (Hanzi Writer, chấm trên trình duyệt). */
    public function writing(string $unitId): View
    {
        $unit = $this->unit($unitId);
        abort_unless($unit->isChinese(), 404);
        $words = array_values($this->cards->words($unit));
        $chars = [];
        foreach ($words as $w) {
            foreach (preg_split('//u', $w['word'], -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                if (preg_match('/^\p{Han}$/u', $ch) && ! isset($chars[$ch])) {
                    $chars[$ch] = ['char' => $ch, 'word' => $w['word'], 'pinyin' => $w['pinyin'], 'meaning' => $w['meaning']];
                }
            }
        }

        return view('portal.vocab.writing', ['unit' => $unit, 'chars' => array_values($chars)]);
    }
}
