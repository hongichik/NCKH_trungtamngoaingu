<?php

namespace App\Support\Progress;

use App\Models\Student;
use App\Models\StudentBadge;
use App\Models\StudentStat;
use App\Models\VocabReview;
use App\Models\XpLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Điểm kinh nghiệm (XP), chuỗi ngày học, mục tiêu mỗi ngày và huy hiệu.
 * Mỗi nguồn XP có trần mỗi ngày để không "cày" điểm bằng cách bấm liên tục.
 */
class Gamification
{
    /** Trần XP mỗi ngày theo nguồn. */
    public const DAILY_CAPS = ['test' => 300, 'vocab' => 120, 'drill' => 100, 'hanzi' => 60, 'mistake' => 60, 'practice' => 80];

    public const SOURCES = ['test' => 'Làm bài', 'vocab' => 'Ôn từ vựng', 'drill' => 'Luyện tiếng Trung', 'hanzi' => 'Tập viết chữ Hán', 'mistake' => 'Sửa lỗi sai', 'practice' => 'Bài luyện riêng'];

    public const GOALS = [10 => 'Nhẹ nhàng', 30 => 'Đều đặn', 50 => 'Chăm chỉ', 100 => 'Bứt phá'];

    /** code => [tên, mô tả, icon bootstrap, màu] */
    public const BADGES = [
        'first_test' => ['Khởi động', 'Hoàn thành bài làm đầu tiên', 'flag', 'primary'],
        'unit_master' => ['Chinh phục Unit', 'Đạt từ 8 điểm trong bài của một Unit', 'trophy', 'warning'],
        'excellent' => ['Xuất sắc', 'Đạt từ 9 điểm trong một bài làm', 'star-fill', 'danger'],
        'streak_3' => ['Chuỗi 3 ngày', 'Học 3 ngày liên tiếp', 'fire', 'warning'],
        'streak_7' => ['Chuỗi 7 ngày', 'Học 7 ngày liên tiếp', 'fire', 'danger'],
        'streak_30' => ['Chuỗi 30 ngày', 'Học 30 ngày liên tiếp', 'fire', 'dark'],
        'xp_100' => ['100 XP', 'Tích luỹ 100 điểm kinh nghiệm', 'lightning-charge', 'info'],
        'xp_500' => ['500 XP', 'Tích luỹ 500 điểm kinh nghiệm', 'lightning-charge-fill', 'info'],
        'xp_2000' => ['2000 XP', 'Tích luỹ 2000 điểm kinh nghiệm', 'gem', 'primary'],
        'vocab_50' => ['50 từ', 'Học 50 từ bằng thẻ từ vựng', 'card-heading', 'success'],
        'vocab_200' => ['200 từ', 'Học 200 từ bằng thẻ từ vựng', 'journal-bookmark', 'success'],
        'drill_perfect' => ['Không sai câu nào', 'Làm đúng 100% một lượt luyện tiếng Trung', 'bullseye', 'danger'],
        'goal_7' => ['Giữ mục tiêu', 'Đạt mục tiêu ngày 7 lần', 'calendar-check', 'secondary'],
    ];

    public function stats(Student $student): StudentStat
    {
        return StudentStat::firstOrCreate(['student_id' => $student->id]);
    }

    /** Chuỗi ngày hiện tại (về 0 nếu hôm qua không học). */
    public function currentStreak(StudentStat $s): int
    {
        return $s->last_active_on && $s->last_active_on->gte(today()->subDay()) ? $s->streak : 0;
    }

    public function todayXp(Student $student): int
    {
        return (int) XpLog::where('student_id', $student->id)->where('created_at', '>=', today())->sum('points');
    }

    /** Cộng XP (đã áp trần ngày). Trả về số XP thực cộng + huy hiệu mới. @return array{points: int, badges: list<string>} */
    public function award(Student $student, string $source, int $points, array $meta = []): array
    {
        if ($points <= 0) {
            return ['points' => 0, 'badges' => []];
        }

        return DB::transaction(function () use ($student, $source, $points, $meta) {
            $stat = StudentStat::where('student_id', $student->id)->lockForUpdate()->first() ?? $this->stats($student);
            $cap = self::DAILY_CAPS[$source] ?? 100;
            $used = (int) XpLog::where('student_id', $student->id)->where('source', $source)->where('created_at', '>=', today())->sum('points');
            $points = min($points, max(0, $cap - $used));
            if ($points <= 0) {
                return ['points' => 0, 'badges' => []];
            }
            $before = $this->todayXp($student);
            XpLog::create(['student_id' => $student->id, 'source' => $source, 'points' => $points, 'meta' => $meta ?: null]);

            // Chuỗi ngày học
            $last = $stat->last_active_on;
            if (! $last || $last->lt(today())) {
                $stat->streak = $last && $last->eq(today()->subDay()) ? $stat->streak + 1 : 1;
                $stat->last_active_on = today();
            }
            $stat->best_streak = max($stat->best_streak, $stat->streak);
            $stat->xp += $points;
            $stat->save();

            $badges = [];
            if ($before < $stat->daily_goal && $before + $points >= $stat->daily_goal) {
                $days = XpLog::where('student_id', $student->id)->selectRaw('DATE(created_at) d, SUM(points) p')->groupBy('d')->get()->where('p', '>=', $stat->daily_goal)->count();
                if ($days >= 7 && $this->grant($student, 'goal_7')) {
                    $badges[] = 'goal_7';
                }
            }
            foreach ([3, 7, 30] as $n) {
                if ($stat->streak >= $n && $this->grant($student, "streak_{$n}")) {
                    $badges[] = "streak_{$n}";
                }
            }
            foreach ([100, 500, 2000] as $n) {
                if ($stat->xp >= $n && $this->grant($student, "xp_{$n}")) {
                    $badges[] = "xp_{$n}";
                }
            }
            if ($source === 'vocab') {
                $learned = VocabReview::where('student_id', $student->id)->where('reps', '>=', 1)->count();
                foreach ([50, 200] as $n) {
                    if ($learned >= $n && $this->grant($student, "vocab_{$n}")) {
                        $badges[] = "vocab_{$n}";
                    }
                }
            }

            return ['points' => $points, 'badges' => $badges];
        });
    }

    /** Trao huy hiệu (true nếu mới nhận). */
    public function grant(Student $student, string $code, string $ref = ''): bool
    {
        return StudentBadge::firstOrCreate(['student_id' => $student->id, 'code' => $code, 'ref' => $ref], ['earned_at' => now()])->wasRecentlyCreated;
    }

    /** Bài làm 4 kỹ năng vừa chấm xong → XP + huy hiệu. */
    public function testCompleted(\App\Models\UnitTest $test): void
    {
        $student = $test->student;
        if (! $student || $test->total_score === null) {
            return;
        }
        $this->award($student, 'test', 10 + (int) round($test->total_score * 5), ['unit_test_id' => $test->id]);
        $this->grant($student, 'first_test');
        if ($test->total_score >= 8) {
            $this->grant($student, 'unit_master', (string) $test->unit_id);
        }
        if ($test->total_score >= 9) {
            $this->grant($student, 'excellent');
        }
    }

    /** XP theo ngày (n ngày gần nhất). @return array<string, int> Y-m-d => XP */
    public function daily(Student $student, int $days = 14): array
    {
        $rows = XpLog::where('student_id', $student->id)->where('created_at', '>=', today()->subDays($days - 1))
            ->selectRaw('DATE(created_at) d, SUM(points) p')->groupBy('d')->pluck('p', 'd');
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = today()->subDays($i)->toDateString();
            $out[$d] = (int) ($rows[$d] ?? 0);
        }

        return $out;
    }

    /** Bảng xếp hạng XP 7 ngày của lớp. @return Collection<int, array{student: Student, xp: int}> */
    public function leaderboard(\App\Models\Classroom $classroom, int $limit = 10): Collection
    {
        $students = $classroom->students()->get()->keyBy('id');
        $xp = XpLog::whereIn('student_id', $students->keys())->where('created_at', '>=', today()->subDays(6))
            ->selectRaw('student_id, SUM(points) p')->groupBy('student_id')->pluck('p', 'student_id');

        return $students->map(fn ($s) => ['student' => $s, 'xp' => (int) ($xp[$s->id] ?? 0)])
            ->sortByDesc('xp')->values()->take($limit);
    }
}
