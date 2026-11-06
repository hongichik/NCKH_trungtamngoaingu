<?php

namespace App\Console\Commands;

use App\Support\Ai\Jobs\AiJobRunner;
use App\Support\Exercise\RecordingPruner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

/**
 * Xử lý hàng đợi việc nền AI: làm liên tục việc này xong tới việc sau cho tới khi HẾT hàng đợi
 * (tối đa --seconds cho mỗi lượt chạy), rồi dừng.
 *
 * Cron cPanel gọi lệnh này mỗi phút chỉ để "canh gác": nếu tiến trình đang chạy thì bỏ qua,
 * nếu đã dừng (hết việc / bị ngắt) thì khởi động lại. Không dùng schedule:run vì hosting tắt proc_open.
 */
class AiWork extends Command
{
    /** Tiến trình báo "còn sống" sau mỗi việc; quá thời gian này không báo thì coi như đã chết. */
    public const ALIVE_TTL = 600;

    protected $signature = 'ai:work
        {--seconds=3000 : Thời gian tối đa của một lượt chạy (giây). Hết việc thì dừng sớm.}
        {--max= : Số việc tối đa}
        {--slot=1 : Số thứ tự tiến trình (chỉ cần khi muốn chạy song song nhiều tiến trình)}
        {--idle= : Hết việc thì chờ thêm bao nhiêu giây trước khi dừng (mặc định ai.jobs.idle_seconds)}';

    protected $description = 'Xử lý hàng đợi việc nền AI liên tục tới khi hết việc (cron cPanel gọi mỗi phút để canh gác)';

    public static function aliveKey(int $slot): string
    {
        return "ai:work:alive:{$slot}";
    }

    /** Dấu phiên bản code: thời điểm sửa mới nhất của các file PHP trong app/, config/, routes/ và lời nhắc AI. */
    public static function codeVersion(): int
    {
        clearstatcache();
        $latest = 0;
        foreach (['app', 'config', 'routes', 'resources/prompts'] as $dir) {
            if (! is_dir($path = base_path($dir))) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $f) {
                $latest = max($latest, $f->getMTime());
            }
        }

        return $latest;
    }

    public function handle(AiJobRunner $runner): int
    {
        $slot = max(1, (int) $this->option('slot'));
        $key = self::aliveKey($slot);
        $token = getmypid().'-'.bin2hex(random_bytes(4));

        // Cache::add chỉ thành công nếu chưa có tiến trình nào đang giữ (thao tác nguyên tử).
        if (! Cache::add($key, $token, self::ALIVE_TTL)) {
            $this->line("Tiến trình {$slot} đang chạy, bỏ qua lần này.");

            return self::SUCCESS;
        }

        try {
            if ($slot === 1) {
                try {
                    app(RecordingPruner::class)->runDaily();
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            $seconds = (int) $this->option('seconds');
            $max = $this->option('max') ? (int) $this->option('max') : null;
            $idle = (int) ($this->option('idle') ?? config('ai.jobs.idle_seconds', 55));
            $started = now()->getTimestamp();
            $idleSince = null;
            $code = self::codeVersion();
            $checked = now()->getTimestamp();
            $stats = ['processed' => 0, 'done' => 0, 'failed' => 0, 'retry' => 0, 'paused' => 0];
            while (($left = $seconds - (now()->getTimestamp() - $started)) > 0 && ($max === null || $stats['processed'] < $max)) {
                // Tiến trình chạy lâu giữ code cũ trong bộ nhớ: code vừa sửa / vừa deploy → dừng, cron bật lại bằng code mới.
                if (now()->getTimestamp() - $checked >= 5) {
                    $checked = now()->getTimestamp();
                    if (self::codeVersion() !== $code) {
                        $this->line('Code đã thay đổi — dừng để chạy lại bằng code mới.');

                        break;
                    }
                }
                $s = $runner->work(
                    $left,
                    1,   // từng việc một để kịp nhận ra code thay đổi
                    // Sau mỗi việc: báo "còn sống" để cron không khởi động tiến trình thứ hai.
                    fn () => Cache::put($key, $token, self::ALIVE_TTL),
                );
                foreach ($stats as $k => $v) {
                    $stats[$k] = $v + ($s[$k] ?? 0);
                }
                if ($s['processed'] > 0) {
                    $idleSince = null;

                    continue;
                }
                // Hết việc: chờ thêm một lúc để việc mới gửi lên được nhận ngay.
                $idleSince ??= now()->getTimestamp();
                if (now()->getTimestamp() - $idleSince >= $idle) {
                    break;
                }
                Cache::put($key, $token, self::ALIVE_TTL);
                Sleep::for(2)->seconds();
            }
        } finally {
            if (Cache::get($key) === $token) {
                Cache::forget($key);
            }
        }

        $this->line(sprintf('Đã xử lý %d việc: xong %d, thử lại %d, lỗi %d, tạm dừng vì hạn mức %d.', $stats['processed'], $stats['done'], $stats['retry'], $stats['failed'], $stats['paused']));

        return self::SUCCESS;
    }
}
