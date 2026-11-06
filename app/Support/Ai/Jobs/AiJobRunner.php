<?php

namespace App\Support\Ai\Jobs;

use App\Models\AiJob;
use App\Support\Ai\AiException;
use App\Support\Ai\AiLimitException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Chạy các việc trong bảng ai_jobs.
 * Được gọi bởi lệnh `ai:work` (cron mỗi phút) hoặc endpoint "tick" (trang tiến độ tự kéo).
 */
class AiJobRunner
{
    public const HEARTBEAT_KEY = 'ai:worker:last_run';

    /**
     * Xử lý các việc cho tới khi hết việc hoặc hết thời gian.
     *
     * @return array{processed: int, done: int, failed: int, retry: int}
     */
    public function work(int $seconds = 50, ?int $maxJobs = null, ?callable $afterJob = null, ?string $onlyLane = null): array
    {
        $started = microtime(true);
        $budget = (int) config('ai.jobs.job_budget_seconds', 25);
        $stats = ['processed' => 0, 'done' => 0, 'failed' => 0, 'retry' => 0, 'paused' => 0];

        Cache::put(self::HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay());
        $this->releaseStale();

        while ($maxJobs === null || $stats['processed'] < $maxJobs) {
            // Không bắt đầu việc mới nếu thời gian còn lại ít hơn thời gian tối đa của 1 việc.
            if ($stats['processed'] > 0 && (microtime(true) - $started) > max(1, $seconds - $budget)) {
                break;
            }

            $job = $this->claimNext($onlyLane);
            if (! $job) {
                break;
            }

            $outcome = $this->run($job);
            $stats['processed']++;
            $stats[$outcome]++;

            Cache::put(self::HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay());
            if ($afterJob) {
                $afterJob($job, $outcome);
            }
        }

        return $stats;
    }

    /** Làn đang có tiến trình phục vụ thì khoá này còn (hết hạn sau 10 phút nếu tiến trình bị ngắt). */
    public const LANE_BUSY_TTL = 600;

    /** @var array<int, array{0: string, 1: string}> job id => [làn, mã khoá] */
    private array $laneLocks = [];

    public static function laneKey(string $lane): string
    {
        return 'ai:lane:busy:'.$lane;
    }

    /**
     * Lấy 1 việc để chạy, theo "làn" (mỗi giáo viên một làn):
     *  - Mỗi làn tại một thời điểm chỉ có 1 tiến trình phục vụ → việc của cùng giáo viên chạy lần lượt, đúng thứ tự gửi.
     *  - Nhiều tiến trình (nhiều dòng cron) → nhiều giáo viên được chạy song song.
     *  - Chọn làn lâu nhất chưa được phục vụ (xoay vòng) → người gửi 5 việc không phải chờ người gửi 100 việc.
     */
    public function claimNext(?string $onlyLane = null): ?AiJob
    {
        $lanes = AiJob::where('status', AiJob::PENDING)
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            // Làn của một người gồm cả các làn con "làn/..." (mỗi bản nhập / giáo trình một làn con)
            ->when($onlyLane, fn ($q) => $q->where(fn ($w) => $w->where('lane', $onlyLane)->orWhere('lane', 'like', $onlyLane.'/%')))
            ->selectRaw('lane, MIN(id) AS first_id')
            ->groupBy('lane')
            ->pluck('first_id', 'lane')
            ->all();

        if (! $lanes) {
            return null;
        }

        $order = array_keys($lanes);
        $lastServed = [];
        foreach ($order as $lane) {
            $lastServed[$lane] = (float) Cache::get('ai:lane:last:'.$lane, 0);
        }
        usort($order, fn ($a, $b) => [$lastServed[$a], $lanes[$a]] <=> [$lastServed[$b], $lanes[$b]]);

        foreach ($order as $lane) {
            $token = bin2hex(random_bytes(6));
            // Cache::add là nguyên tử: chỉ 1 tiến trình giữ được làn này.
            if (! Cache::add(self::laneKey($lane), $token, self::LANE_BUSY_TTL)) {
                continue;
            }

            if ($job = $this->claimInLane($lane)) {
                $this->laneLocks[$job->id] = [$lane, $token];
                Cache::put('ai:lane:last:'.$lane, microtime(true), now()->addDay());

                return $job;
            }

            $this->releaseLane($lane, $token);
        }

        return null;
    }

    /** Việc gửi sớm nhất của làn, đổi pending → running một cách nguyên tử. */
    private function claimInLane(string $lane): ?AiJob
    {
        for ($i = 0; $i < 5; $i++) {
            $id = AiJob::where('status', AiJob::PENDING)
                ->where('lane', $lane)
                ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
                ->orderBy('id')
                ->value('id');

            if (! $id) {
                return null;
            }

            $claimed = AiJob::where('id', $id)->where('status', AiJob::PENDING)->update([
                'status' => AiJob::RUNNING,
                'started_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

            if ($claimed === 1) {
                return AiJob::find($id);
            }
        }

        return null;
    }

    private function releaseLane(string $lane, string $token): void
    {
        if (Cache::get(self::laneKey($lane)) === $token) {
            Cache::forget(self::laneKey($lane));
        }
    }

    /**
     * @return 'done'|'failed'|'retry'|'paused'
     */
    public function run(AiJob $job): string
    {
        try {
            return $this->execute($job);
        } finally {
            // Xong việc (thành công hay lỗi) → nhả làn cho việc tiếp theo của giáo viên này
            if (isset($this->laneLocks[$job->id])) {
                [$lane, $token] = $this->laneLocks[$job->id];
                unset($this->laneLocks[$job->id]);
                $this->releaseLane($lane, $token);
            }
        }
    }

    private function execute(AiJob $job): string
    {
        // Cron (CLI): không giới hạn — một lượt chép lời / phân tích dài có thể hơn 60 giây.
        // Web (tick): xin thêm thời gian nếu hosting cho phép.
        @set_time_limit(PHP_SAPI === 'cli' ? 0 : 120);

        $handler = null;
        try {
            $class = config("ai.jobs.handlers.{$job->type}") ?? throw new AiException("Loại việc chưa đăng ký: {$job->type}");
            /** @var AiJobHandler $handler */
            $handler = app($class);
            $result = $handler->handle($job);

            $job->update(['status' => AiJob::DONE, 'result' => $result, 'last_error' => null, 'finished_at' => now()]);

            return 'done';
        } catch (Throwable $e) {
            // Hết hạn mức (lượt/ngày, lượt/tháng, ngân sách): KHÔNG phải lỗi của việc → tạm dừng,
            // không tính lần thử, tự thử lại sau 15 phút; lưu cấu hình AI (tăng hạn mức) thì chạy tiếp ngay.
            if ($e instanceof AiLimitException) {
                $job->update([
                    'status' => AiJob::PENDING,
                    'attempts' => max(0, $job->attempts - 1),
                    'last_error' => AiJob::PAUSED_PREFIX.$e->getMessage(),
                    'available_at' => now()->addMinutes(15),
                ]);

                return 'paused';
            }

            $retryable = ! ($e instanceof AiException) || $e->retryable;

            if ($retryable && $job->attempts < $job->max_attempts) {
                // Giãn cách tăng dần: 30s, 60s, 120s...
                $job->update([
                    'status' => AiJob::PENDING,
                    'last_error' => mb_substr($e->getMessage(), 0, 2000),
                    'available_at' => now()->addSeconds(30 * 2 ** ($job->attempts - 1)),
                ]);

                return 'retry';
            }

            $job->update(['status' => AiJob::FAILED, 'last_error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
            report($e);

            // Cho lớp xử lý dọn dẹp (vd đánh dấu đoạn giáo trình là lỗi để quy trình không bị kẹt).
            if ($handler && method_exists($handler, 'failed')) {
                try {
                    $handler->failed($job, $e);
                } catch (Throwable $inner) {
                    report($inner);
                }
            }

            return 'failed';
        }
    }

    /** Việc "đang chạy" quá lâu (tiến trình bị ngắt) → trả về hàng chờ. */
    public function releaseStale(): int
    {
        return AiJob::where('status', AiJob::RUNNING)
            ->where('started_at', '<', now()->subMinutes((int) config('ai.jobs.stale_minutes', 5)))
            ->update(['status' => AiJob::PENDING, 'available_at' => now(), 'last_error' => 'Bị ngắt giữa chừng, đã đưa lại vào hàng chờ.']);
    }

    public function counts(?string $batch = null): array
    {
        $q = AiJob::query()->when($batch, fn ($q) => $q->where('batch', $batch));

        return array_merge(
            array_fill_keys(array_keys(AiJob::STATUS_LABELS), 0),
            $q->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->map(fn ($c) => (int) $c)->all()
        );
    }

    /**
     * Hàng đợi theo làn (cho trang quản trị).
     *
     * @return list<array{lane: string, label: string, pending: int, running: int}>
     */
    public function lanes(): array
    {
        $rows = AiJob::whereIn('status', [AiJob::PENDING, AiJob::RUNNING])
            ->selectRaw('lane, status, COUNT(*) AS c')->groupBy('lane', 'status')->get();

        return $rows->groupBy('lane')->map(fn ($g, $lane) => [
            'lane' => $lane,
            'label' => AiJob::laneLabel($lane),
            'pending' => (int) ($g->firstWhere('status', AiJob::PENDING)->c ?? 0),
            'running' => (int) ($g->firstWhere('status', AiJob::RUNNING)->c ?? 0),
        ])->sortByDesc('pending')->values()->all();
    }

    public static function lastHeartbeat(): ?string
    {
        return Cache::get(self::HEARTBEAT_KEY);
    }
}
