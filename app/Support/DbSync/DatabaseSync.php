<?php

namespace App\Support\DbSync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Xuất / nhập toàn bộ dữ liệu các bảng dưới dạng JSON nén gzip.
 *
 * Định dạng payload (trước khi nén):
 * {
 *   "meta":   {"format": 1, "app": "...", "created_at": "...", "migrations": [...]},
 *   "tables": {"posts": [{...}, {...}], "categories": [...]}
 * }
 */
class DatabaseSync
{
    public const FORMAT_VERSION = 1;

    /**
     * Danh sách bảng có thể đồng bộ trên CSDL hiện tại.
     *
     * @return list<string>
     */
    public function syncableTables(): array
    {
        $excluded = config('dbsync.excluded_tables', []);

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
    public function resolveTables(array $only = [], array $except = []): array
    {
        $tables = $this->syncableTables();

        if ($only) {
            $unknown = array_diff($only, $tables);
            if ($unknown) {
                throw new InvalidArgumentException('Bảng không tồn tại hoặc không được phép đồng bộ: '.implode(', ', $unknown));
            }
            $tables = array_values(array_intersect($tables, $only));
        }

        return array_values(array_diff($tables, $except));
    }

    /**
     * Xuất dữ liệu thành chuỗi gzip.
     *
     * @param  list<string>|null  $tables
     */
    public function export(?array $tables = null): string
    {
        $tables ??= $this->syncableTables();
        $data = [];

        foreach ($tables as $table) {
            $data[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        $payload = [
            'meta' => [
                'format' => self::FORMAT_VERSION,
                'app' => config('app.name'),
                'source' => config('app.url'),
                'created_at' => now()->toIso8601String(),
                'migrations' => $this->ranMigrations(),
            ],
            'tables' => $data,
        ];

        return gzencode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 6);
    }

    /**
     * Giải nén và kiểm tra payload.
     */
    public function decode(string $raw): array
    {
        $json = @gzdecode($raw);
        if ($json === false) {
            throw new InvalidArgumentException('Payload không phải dữ liệu gzip hợp lệ.');
        }

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || ! isset($payload['meta'], $payload['tables']) || ! is_array($payload['tables'])) {
            throw new InvalidArgumentException('Payload thiếu "meta" hoặc "tables".');
        }

        if (($payload['meta']['format'] ?? null) !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException('Phiên bản định dạng payload không khớp.');
        }

        return $payload;
    }

    /**
     * Các migration có trong payload nhưng CSDL hiện tại chưa chạy.
     */
    public function missingMigrations(array $payload): array
    {
        return array_values(array_diff($payload['meta']['migrations'] ?? [], $this->ranMigrations()));
    }

    /**
     * Ghi đè dữ liệu các bảng có trong payload. Chạy trong 1 transaction:
     * lỗi ở bất kỳ bảng nào thì toàn bộ được hoàn tác.
     *
     * @return array{tables: array<string,int>, skipped: list<string>, backup: string|null}
     */
    public function import(array $payload, bool $backup = true): array
    {
        $allowed = $this->syncableTables();
        $incoming = $payload['tables'];

        $skipped = array_values(array_diff(array_keys($incoming), $allowed));
        $tables = array_values(array_intersect(array_keys($incoming), $allowed));

        $backupPath = $backup ? $this->backup($tables) : null;
        $chunk = (int) config('dbsync.chunk_size', 500);
        $result = [];

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($tables, $incoming, $chunk, &$result) {
                foreach ($tables as $table) {
                    $columns = array_flip(Schema::getColumnListing($table));
                    // Chỉ giữ các cột tồn tại ở CSDL đích.
                    $rows = array_map(
                        fn ($row) => array_intersect_key((array) $row, $columns),
                        $incoming[$table] ?? []
                    );

                    // Dùng DELETE (không dùng TRUNCATE) để giữ được transaction trên MySQL.
                    DB::table($table)->delete();

                    foreach (array_chunk($rows, $chunk) as $part) {
                        DB::table($table)->insert($part);
                    }

                    $result[$table] = count($rows);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->resetSequences($tables);

        return ['tables' => $result, 'skipped' => $skipped, 'backup' => $backupPath];
    }

    /**
     * Lưu dữ liệu hiện tại của các bảng trước khi ghi đè.
     *
     * @param  list<string>  $tables
     */
    public function backup(array $tables): string
    {
        $dir = storage_path('app/db-sync-backups');
        File::ensureDirectoryExists($dir);

        $path = $dir.'/backup-'.now()->format('Ymd-His').'-'.substr(bin2hex(random_bytes(3)), 0, 6).'.json.gz';
        File::put($path, $this->export($tables));

        $this->pruneBackups($dir);

        return $path;
    }

    private function pruneBackups(string $dir): void
    {
        $keep = max(1, (int) config('dbsync.backup_keep', 10));
        $files = collect(File::glob($dir.'/backup-*.json.gz'))->sort()->values();

        $files->slice(0, max(0, $files->count() - $keep))->each(fn ($f) => File::delete($f));
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
