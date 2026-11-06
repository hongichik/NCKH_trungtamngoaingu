<?php

namespace App\Support\DbSync;

use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Xuất / nhập dữ liệu các bảng theo kiểu stream để dùng ít bộ nhớ
 * bất kể dữ liệu lớn cỡ nào.
 *
 * Định dạng: NDJSON (mỗi dòng 1 JSON) nén gzip:
 *   {"meta": {"format": 2, "migrations": [...], ...}}
 *   {"table": "students"}
 *   {"row": {...}}            <- các dòng thuộc bảng khai báo gần nhất
 *   {"row": {...}}
 *   {"table": "posts"}
 *   ...
 * Một bảng có header nhưng không có row nghĩa là bảng rỗng (sẽ bị xoá sạch ở phía nhận).
 * Nhiều file .gz nối tiếp nhau được đọc như một luồng liên tục.
 */
class DatabaseSync
{
    public const FORMAT_VERSION = 2;

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * Danh sách bảng có thể đồng bộ trên CSDL hiện tại.
     *
     * @return list<string>
     */
    public function syncableTables(bool $includeProtected = false): array
    {
        $excluded = array_merge(
            config('dbsync.excluded_tables', []),
            $includeProtected ? [] : config('dbsync.protected_tables', []),
        );

        return collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn ($t) => in_array($t, $excluded, true) || str_starts_with($t, 'sqlite_'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Lọc danh sách bảng theo --tables / --exclude.
     *
     * @param  list<string>  $only
     * @param  list<string>  $except
     * @return list<string>
     */
    public function resolveTables(array $only = [], array $except = [], bool $includeProtected = false): array
    {
        $tables = $this->syncableTables($includeProtected);

        if ($only) {
            $unknown = array_diff($only, $tables);
            if ($unknown) {
                throw new InvalidArgumentException('Bảng không tồn tại hoặc không được phép đồng bộ: '.implode(', ', $unknown));
            }
            $tables = array_values(array_intersect($tables, $only));
        }

        return array_values(array_diff($tables, $except));
    }

    // ------------------------------------------------------------------
    // Xuất
    // ------------------------------------------------------------------

    /**
     * Sinh lần lượt từng dòng NDJSON (không kèm "\n").
     *
     * @param  list<string>  $tables
     * @return Generator<string>
     */
    public function lines(array $tables): Generator
    {
        yield json_encode(['meta' => $this->meta()], self::JSON_FLAGS);

        foreach ($tables as $table) {
            yield json_encode(['table' => $table], self::JSON_FLAGS);

            foreach ($this->rows($table) as $row) {
                yield json_encode(['row' => $row], self::JSON_FLAGS);
            }
        }
    }

    /**
     * Ghi toàn bộ dữ liệu vào 1 file gzip.
     *
     * @param  list<string>  $tables
     */
    public function exportToFile(array $tables, string $path): string
    {
        return $this->writeParts($tables, dirname($path), basename($path), null)[0];
    }

    /**
     * Ghi dữ liệu ra nhiều file gzip, mỗi file chứa khoảng $partBytes byte
     * (chưa nén). Dùng để upload từng phần nhỏ lên máy chủ.
     *
     * @param  list<string>  $tables
     * @return list<string> đường dẫn các file theo thứ tự
     */
    public function exportToParts(array $tables, string $dir, int $partBytes): array
    {
        return $this->writeParts($tables, $dir, null, max(1024, $partBytes));
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function writeParts(array $tables, string $dir, ?string $singleName, ?int $partBytes): array
    {
        File::ensureDirectoryExists($dir);

        $paths = [];
        $handle = null;
        $bytes = 0;

        foreach ($this->lines($tables) as $line) {
            if ($handle === null || ($partBytes !== null && $bytes >= $partBytes)) {
                if ($handle !== null) {
                    gzclose($handle);
                }
                $path = $dir.'/'.($singleName ?? sprintf('part-%05d.ndjson.gz', count($paths) + 1));
                $handle = gzopen($path, 'wb6') ?: throw new \RuntimeException("Không ghi được file {$path}");
                $paths[] = $path;
                $bytes = 0;
            }

            gzwrite($handle, $line."\n");
            $bytes += strlen($line) + 1;
        }

        gzclose($handle);

        return $paths;
    }

    /**
     * Đọc dữ liệu 1 bảng theo từng lô để không phải nạp hết vào bộ nhớ.
     *
     * @return Generator<array<string,mixed>>
     */
    private function rows(string $table): Generator
    {
        $query = DB::table($table);

        if (Schema::hasColumn($table, 'id')) {
            $cursor = $query->lazyById(1000);
        } else {
            // Bảng pivot không có id: sắp xếp theo mọi cột để phân trang ổn định.
            foreach (Schema::getColumnListing($table) as $column) {
                $query->orderBy($column);
            }
            $cursor = $query->lazy(1000);
        }

        foreach ($cursor as $row) {
            yield (array) $row;
        }
    }

    private function meta(): array
    {
        return [
            'format' => self::FORMAT_VERSION,
            'app' => config('app.name'),
            'source' => config('app.url'),
            'created_at' => now()->toIso8601String(),
            'migrations' => $this->ranMigrations(),
        ];
    }

    // ------------------------------------------------------------------
    // Đọc / nhập
    // ------------------------------------------------------------------

    /**
     * Đọc lần lượt các dòng từ 1 hoặc nhiều file gzip.
     *
     * @param  list<string>  $paths
     * @return Generator<array>
     */
    public function readLines(array $paths): Generator
    {
        foreach ($paths as $path) {
            $handle = @gzopen($path, 'rb');
            if (! $handle) {
                throw new InvalidArgumentException("Không đọc được file {$path}");
            }

            try {
                while (($line = gzgets($handle)) !== false) {
                    $line = rtrim($line, "\r\n");
                    if ($line === '') {
                        continue;
                    }

                    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (! is_array($item)) {
                        throw new InvalidArgumentException('Dòng dữ liệu không hợp lệ.');
                    }

                    yield $item;
                }
            } finally {
                gzclose($handle);
            }
        }
    }

    /**
     * Lấy và kiểm tra phần meta ở dòng đầu tiên.
     *
     * @param  list<string>  $paths
     */
    public function readMeta(array $paths): array
    {
        try {
            foreach ($this->readLines($paths) as $item) {
                $meta = $item['meta'] ?? null;
                break;
            }
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Dữ liệu không phải NDJSON hợp lệ: '.$e->getMessage());
        }

        if (! isset($meta) || ! is_array($meta)) {
            throw new InvalidArgumentException('Thiếu phần "meta" ở đầu dữ liệu.');
        }

        if (($meta['format'] ?? null) !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException('Phiên bản định dạng dữ liệu không khớp (cần cập nhật code ở cả 2 máy).');
        }

        return $meta;
    }

    /**
     * Các migration có ở máy gửi nhưng CSDL hiện tại chưa chạy.
     */
    public function missingMigrations(array $meta): array
    {
        return array_values(array_diff($meta['migrations'] ?? [], $this->ranMigrations()));
    }

    /**
     * Danh sách bảng khai báo trong dữ liệu (quét nhanh, không giải mã từng row).
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public function tablesIn(array $paths): array
    {
        $tables = [];

        foreach ($paths as $path) {
            $handle = @gzopen($path, 'rb') ?: throw new InvalidArgumentException("Không đọc được file {$path}");
            while (($line = gzgets($handle)) !== false) {
                if (str_starts_with($line, '{"table":')) {
                    $tables[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR)['table'];
                }
            }
            gzclose($handle);
        }

        return array_values(array_unique($tables));
    }

    /**
     * Ghi đè dữ liệu các bảng có trong file. Chạy trong 1 transaction:
     * lỗi ở bất kỳ đâu thì toàn bộ được hoàn tác.
     *
     * @param  list<string>  $paths
     * @return array{tables: array<string,int>, skipped: list<string>, backup: string|null}
     */
    public function import(array $paths, bool $backup = true, bool $includeProtected = false): array
    {
        // Mặc định KHÔNG nhập bảng được bảo vệ (dữ liệu giáo viên trên máy chủ); chỉ db:sync-pull ở máy local mới bật.
        $allowed = $this->syncableTables($includeProtected);
        $declared = $this->tablesIn($paths);

        $tables = array_values(array_intersect($declared, $allowed));
        $skipped = array_values(array_diff($declared, $allowed));

        $backupPath = $backup && $tables ? $this->backup($tables) : null;
        $chunk = max(1, (int) config('dbsync.chunk_size', 500));
        $counts = [];

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($paths, $allowed, $chunk, &$counts) {
                $current = null;
                $columns = [];
                $buffer = [];

                $flush = function () use (&$buffer, &$current) {
                    if ($buffer) {
                        DB::table($current)->insert($buffer);
                        $buffer = [];
                    }
                };

                foreach ($this->readLines($paths) as $item) {
                    if (isset($item['table'])) {
                        $flush();
                        $current = in_array($item['table'], $allowed, true) ? $item['table'] : null;

                        if ($current !== null) {
                            $columns = array_flip(Schema::getColumnListing($current));
                            // DELETE (không dùng TRUNCATE) để giữ được transaction trên MySQL.
                            DB::table($current)->delete();
                            $counts[$current] = 0;
                        }

                        continue;
                    }

                    if ($current !== null && isset($item['row']) && is_array($item['row'])) {
                        // Chỉ giữ các cột tồn tại ở CSDL đích.
                        $buffer[] = array_intersect_key($item['row'], $columns);
                        $counts[$current]++;

                        if (count($buffer) >= $chunk) {
                            $flush();
                        }
                    }
                }

                $flush();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->resetSequences($tables);

        return ['tables' => $counts, 'skipped' => $skipped, 'backup' => $backupPath];
    }

    /**
     * Lưu dữ liệu hiện tại của các bảng trước khi ghi đè.
     *
     * @param  list<string>  $tables
     */
    public function backup(array $tables, string $prefix = 'backup'): string
    {
        $dir = self::backupDir();
        $path = $dir.'/'.$prefix.'-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.ndjson.gz';

        $this->exportToFile($tables, $path);
        $this->pruneBackups($dir);

        return $path;
    }

    public static function backupDir(): string
    {
        return storage_path('app/db-sync-backups');
    }

    /**
     * Danh sách bản sao lưu, mới nhất trước.
     *
     * @return list<array{name: string, size: int, time: int}>
     */
    public function backups(): array
    {
        return collect(File::glob(self::backupDir().'/*.gz'))
            ->map(fn ($f) => ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)])
            ->sortByDesc(fn ($b) => [$b['time'], $b['name']])
            ->values()
            ->all();
    }

    /**
     * Đường dẫn tuyệt đối của 1 bản sao lưu theo tên (chống path traversal).
     */
    public function backupPath(string $name): ?string
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.gz$/', $name)) {
            return null;
        }

        $path = self::backupDir().'/'.$name;

        return is_file($path) ? $path : null;
    }

    /**
     * Khôi phục dữ liệu từ 1 bản sao lưu (trước đó vẫn tự sao lưu dữ liệu hiện tại).
     *
     * @return array{tables: array<string,int>, skipped: list<string>, backup: string|null}
     */
    public function restore(string $path): array
    {
        $meta = $this->readMeta([$path]);

        if ($missing = $this->missingMigrations($meta)) {
            throw new InvalidArgumentException('CSDL hiện tại thiếu migration so với bản sao lưu: '.implode(', ', $missing));
        }

        return $this->import([$path]);
    }

    private function pruneBackups(string $dir): void
    {
        $keep = max(1, (int) config('dbsync.backup_keep', 10));
        // Chỉ tự xoá bản sao lưu tự động; bản tải lên / tạo tay được giữ đến khi xoá tay.
        $files = collect(File::glob($dir.'/backup-*.gz'))->sort()->values();

        $files->slice(0, max(0, $files->count() - $keep))->each(fn ($f) => File::delete($f));
    }

    // ------------------------------------------------------------------
    // Nhật ký
    // ------------------------------------------------------------------

    public static function logPath(): string
    {
        return storage_path('app/db-sync-log.jsonl');
    }

    public function log(string $action, array $data = []): void
    {
        File::ensureDirectoryExists(dirname(self::logPath()));
        File::append(self::logPath(), json_encode(
            ['time' => now()->toIso8601String(), 'action' => $action] + $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        )."\n");
    }

    /**
     * @return list<array>
     */
    public function recentLog(int $limit = 100): array
    {
        if (! is_file(self::logPath())) {
            return [];
        }

        $lines = array_slice(file(self::logPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -$limit);

        return array_values(array_filter(array_map(fn ($l) => json_decode($l, true), array_reverse($lines))));
    }

    /**
     * @return list<string>
     */
    private function ranMigrations(): array
    {
        if (! Schema::hasTable('migrations')) {
            return [];
        }

        return DB::table('migrations')->orderBy('id')->pluck('migration')->all();
    }

    /**
     * PostgreSQL cần đặt lại sequence sau khi insert id thủ công.
     * MySQL / SQLite tự xử lý AUTO_INCREMENT.
     *
     * @param  list<string>  $tables
     */
    private function resetSequences(array $tables): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'id')) {
                DB::statement("SELECT setval(pg_get_serial_sequence('\"{$table}\"', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1))");
            }
        }
    }
}
