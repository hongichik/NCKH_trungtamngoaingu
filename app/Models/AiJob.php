<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Một việc nền (gọi AI, OCR, chép lời...). Xem App\Support\Ai\Jobs\AiJobRunner.
 */
class AiJob extends Model
{
    public const PENDING = 'pending';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const STATUS_LABELS = [
        self::PENDING => 'Chờ',
        self::RUNNING => 'Đang chạy',
        self::DONE => 'Xong',
        self::FAILED => 'Lỗi',
    ];

    /** Tiền tố của last_error khi việc đang tạm dừng vì hết hạn mức (vẫn ở trạng thái "Chờ"). */
    public const PAUSED_PREFIX = 'Tạm dừng vì hết hạn mức: ';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'available_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Đưa một việc vào hàng đợi.
     */
    public static function dispatch(string $type, array $payload = [], ?string $batch = null, int $delaySeconds = 0, ?string $lane = null): self
    {
        // Mỗi giáo viên một làn: việc của cùng người chạy lần lượt, người khác nhau chạy song song.
        $lane ??= ! empty($payload['teacher_id']) ? 'teacher:'.$payload['teacher_id'] : 'system';

        if (! array_key_exists($type, config('ai.jobs.handlers', []))) {
            throw new \InvalidArgumentException("Loại việc nền chưa đăng ký: {$type}");
        }

        return self::create([
            'type' => $type,
            'batch' => $batch,
            'lane' => $lane,
            'payload' => $payload,
            'status' => self::PENDING,
            'max_attempts' => (int) config('ai.jobs.max_attempts', 3),
            'available_at' => now()->addSeconds($delaySeconds),
        ]);
    }

    /** Tên hiển thị của làn (vd "Cô Lan"). */
    public static function laneLabel(string $lane): string
    {
        if (str_contains($lane, '/')) {
            [$owner, $sub] = explode('/', $lane, 2);

            return self::laneLabel($owner).' · '.(str_starts_with($sub, 'i') ? 'bản nhập #'.substr($sub, 1) : 'giáo trình #'.ltrim(strtok($sub, '/'), 'b'));
        }
        if (str_starts_with($lane, 'teacher:')) {
            return Teacher::find((int) substr($lane, 8))?->name ?? $lane;
        }

        return $lane === 'system' ? 'Hệ thống' : $lane;
    }

    public function isPaused(): bool
    {
        return $this->status === self::PENDING && str_starts_with((string) $this->last_error, self::PAUSED_PREFIX);
    }

    /** Chạy tiếp ngay các việc đang tạm dừng vì hết hạn mức (gọi sau khi tăng hạn mức). */
    public static function resumePaused(): int
    {
        return self::where('status', self::PENDING)->where('last_error', 'like', self::PAUSED_PREFIX.'%')
            ->update(['available_at' => now(), 'last_error' => null]);
    }

    /**
     * Cho chạy lại việc lỗi. Lớp xử lý có thể khai báo retrying() để đưa quy trình về đúng bước
     * (vd chạy lại OCR → sau khi đọc xong phải nhận diện lại Unit).
     */
    public function retry(): void
    {
        $this->update(['status' => self::PENDING, 'attempts' => 0, 'last_error' => null, 'available_at' => now(), 'finished_at' => null]);

        $class = config("ai.jobs.handlers.{$this->type}");
        if ($class && method_exists($class, 'retrying')) {
            app($class)->retrying($this);
        }
    }

    /**
     * Chạy lại mọi việc lỗi (lọc theo nhóm / làn nếu có).
     */
    public static function retryFailed(?string $batch = null, ?string $lane = null): int
    {
        $jobs = self::where('status', self::FAILED)
            ->when($batch, fn ($q) => $q->where('batch', $batch))
            ->when($lane, fn ($q) => $q->where('lane', $lane))
            ->orderBy('id')->get();
        $jobs->each->retry();

        return $jobs->count();
    }
}
