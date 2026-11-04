<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DbSync\DatabaseSync;
use App\Support\DbSync\FileSync;
use App\Support\EnvEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Trang Quản trị hệ thống — chỉ chủ hệ thống (users.id = 1).
 */
class SystemController extends Controller
{
    public const TABS = [
        'overview' => ['Tổng quan', 'ti-dashboard'],
        'config' => ['Cấu hình', 'ti-adjustments'],
        'sync' => ['Đồng bộ', 'ti-refresh'],
        'backups' => ['Sao lưu & khôi phục', 'ti-database-export'],
        'commands' => ['Lệnh hệ thống', 'ti-terminal-2'],
        'logs' => ['Nhật ký', 'ti-list-details'],
    ];

    /**
     * Lệnh artisan được phép chạy từ giao diện (không cho nhập lệnh tuỳ ý).
     * key => [nhãn, lệnh, tham số, mô tả, cần xác nhận]
     */
    public const COMMANDS = [
        'storage-link' => ['Tạo storage link', 'storage:link', [], 'Tạo public/storage để ảnh tải lên hiển thị được.', false],
        'optimize-clear' => ['Xoá toàn bộ cache', 'optimize:clear', [], 'Xoá cache config, route, view, ứng dụng. Dùng sau khi upload code mới.', false],
        'cache-clear' => ['Xoá cache ứng dụng', 'cache:clear', [], 'Xoá dữ liệu cache (menu, cài đặt...).', false],
        'config-clear' => ['Xoá cache config', 'config:clear', [], 'Bắt buộc sau khi sửa .env nếu máy chủ đang cache config.', false],
        'route-clear' => ['Xoá cache route', 'route:clear', [], '', false],
        'view-clear' => ['Xoá cache view', 'view:clear', [], 'Dùng khi giao diện không cập nhật sau khi sửa file blade.', false],
        'config-cache' => ['Cache config', 'config:cache', [], 'Tăng tốc. Sau đó mọi thay đổi .env cần xoá cache config.', false],
        'view-cache' => ['Cache view', 'view:cache', [], 'Biên dịch trước toàn bộ view.', false],
        'migrate-status' => ['Trạng thái migration', 'migrate:status', [], 'Xem migration nào đã / chưa chạy.', false],
        'migrate' => ['Chạy migration', 'migrate', ['--force' => true], 'Cập nhật cấu trúc CSDL theo code mới nhất.', true],
        'about' => ['Thông tin ứng dụng', 'about', [], 'Phiên bản, môi trường, driver đang dùng.', false],
    ];

    /** Biến .env sửa được ở tab Cấu hình. */
    private const APP_ENV_KEYS = ['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL'];

    public function __construct(
        private DatabaseSync $sync,
        private FileSync $files,
        private EnvEditor $env,
    ) {}

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'overview';

        $data = ['tab' => $tab, 'tabs' => self::TABS, 'env' => $this->env];

        match ($tab) {
            'overview' => $data += ['info' => $this->systemInfo(), 'owner' => User::find(User::OWNER_ID)],
            'config' => $data += ['envWritable' => $this->env->isWritable()],
            'sync' => $data += [
                'envWritable' => $this->env->isWritable(),
                'tables' => collect($this->sync->syncableTables())->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]),
                'allTables' => collect(\Illuminate\Support\Facades\Schema::getTables())->pluck('name')->sort()->values(),
                'fileDirs' => $this->files->dirs(),
            ],
            'backups' => $data += ['backups' => $this->sync->backups()],
            'commands' => $data += ['commands' => self::COMMANDS],
            'logs' => $data += ['log' => $this->sync->recentLog(200)],
        };

        return view('admin.system.index', $data);
    }

    // ------------------------------------------------------------------
    // Cấu hình
    // ------------------------------------------------------------------

    public function updateConfig(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'APP_NAME' => ['required', 'string', 'max:120'],
            'APP_ENV' => ['required', Rule::in(['production', 'local', 'staging'])],
            'APP_DEBUG' => ['nullable', 'boolean'],
            'APP_URL' => ['required', 'url', 'max:255'],
        ]);
        $data['APP_DEBUG'] = $request->boolean('APP_DEBUG');

        return $this->writeEnv(array_intersect_key($data, array_flip(self::APP_ENV_KEYS)), 'config', 'Đã lưu cấu hình ứng dụng.');
    }

    public function updateSync(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'remote_url' => ['nullable', 'url', 'max:255'],
            'file_dirs' => ['nullable', 'string', 'max:1000'],
            'exclude' => ['nullable', 'array'],
            'exclude.*' => ['string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'backup_keep' => ['required', 'integer', 'min:1', 'max:200'],
            'token' => ['nullable', 'string', 'min:32', 'max:200', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ], [
            'token.min' => 'Token phải dài tối thiểu 32 ký tự.',
            'token.regex' => 'Token chỉ gồm chữ, số, dấu gạch.',
        ]);

        $dirs = array_values(array_unique(array_filter(array_map(
            fn ($d) => trim(str_replace('\\', '/', $d), " /\t"),
            preg_split('/[,\n]/', (string) ($data['file_dirs'] ?? ''))
        ))));
        $invalid = array_filter($dirs, fn ($d) => ! FileSync::isAllowedDir($d));
        if ($invalid) {
            return back()->withInput()->withErrors(['file_dirs' => 'Thư mục không hợp lệ (chỉ cho phép trong storage/app/ hoặc public/, không chứa thư mục sao lưu nội bộ): '.implode(', ', $invalid)]);
        }

        $values = [
            'DB_SYNC_ENABLED' => $request->boolean('enabled'),
            'DB_SYNC_FILES_ENABLED' => $request->boolean('files_enabled'),
            'DB_SYNC_FILE_DIRS' => implode(',', $dirs),
            'DB_SYNC_REMOTE_URL' => rtrim((string) ($data['remote_url'] ?? ''), '/'),
            'DB_SYNC_EXCLUDE' => implode(',', $data['exclude'] ?? []),
            'DB_SYNC_BACKUP_KEEP' => (int) $data['backup_keep'],
        ];
        if (filled($data['token'] ?? null)) {
            $values['DB_SYNC_TOKEN'] = $data['token'];
        }

        return $this->writeEnv($values, 'sync', 'Đã lưu cấu hình đồng bộ.');
    }

    public function regenerateToken(): RedirectResponse
    {
        $token = bin2hex(random_bytes(32));
        $response = $this->writeEnv(['DB_SYNC_TOKEN' => $token], 'sync', 'Đã tạo token mới. Hãy cập nhật DB_SYNC_TOKEN ở máy còn lại cho giống.');

        return $response->with('new_token', $token);
    }

    private function writeEnv(array $values, string $tab, string $message): RedirectResponse
    {
        if (! $this->env->isWritable()) {
            return back()->withInput()->withErrors('Không ghi được file .env (thiếu quyền ghi). Hãy sửa qua File Manager của hosting.');
        }

        try {
            $this->env->set($values);
            if (app()->configurationIsCached()) {
                Artisan::call('config:clear');
            }
        } catch (Throwable $e) {
            return back()->withInput()->withErrors('Không lưu được .env: '.$e->getMessage());
        }

        $this->sync->log('config', ['user' => auth()->user()->email, 'keys' => array_keys($values)]);

        return redirect()->route('admin.system.index', ['tab' => $tab])->with('status', $message);
    }

    // ------------------------------------------------------------------
    // Lệnh hệ thống
    // ------------------------------------------------------------------

    public function runCommand(Request $request): RedirectResponse
    {
        $key = (string) $request->input('command');
        if (! isset(self::COMMANDS[$key])) {
            abort(404);
        }

        [$label, $command, $params] = self::COMMANDS[$key];
        @set_time_limit(0);

        if ($command === 'storage:link' && file_exists(public_path('storage')) && ! is_link(public_path('storage'))) {
            return $this->commandResult($label, false, 'Đã có thư mục/file thật tên "public/storage". Hãy xoá hoặc đổi tên nó rồi chạy lại.');
        }

        try {
            $code = Artisan::call($command, $params);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            $code = 1;
            $output = $e->getMessage();
        }

        $this->sync->log('command', ['user' => auth()->user()->email, 'command' => $command, 'exit' => $code]);

        return $this->commandResult($label, $code === 0, $output ?: '(không có output)');
    }

    private function commandResult(string $label, bool $ok, string $output): RedirectResponse
    {
        return redirect()->route('admin.system.index', ['tab' => 'commands'])
            ->with('command_result', ['label' => $label, 'ok' => $ok, 'output' => mb_substr(strip_tags($output), 0, 20000)]);
    }

    // ------------------------------------------------------------------
    // Sao lưu & khôi phục
    // ------------------------------------------------------------------

    public function backupCreate(): RedirectResponse
    {
        @set_time_limit(0);
        $path = $this->sync->backup($this->sync->syncableTables(), 'manual');
        $this->sync->log('backup', ['user' => auth()->user()->email, 'backup' => basename($path)]);

        return redirect()->route('admin.system.index', ['tab' => 'backups'])->with('status', 'Đã tạo bản sao lưu '.basename($path));
    }

    public function backupDownload(string $name): BinaryFileResponse
    {
        $path = $this->sync->backupPath($name) ?? abort(404);

        return response()->download($path);
    }

    public function backupUpload(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.(200 * 1024)]], [
            'file.required' => 'Hãy chọn file sao lưu (.ndjson.gz). Nếu đã chọn mà vẫn báo lỗi, file có thể vượt upload_max_filesize của máy chủ.',
        ]);

        $file = $request->file('file');
        $name = 'upload-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.ndjson.gz';
        $path = DatabaseSync::backupDir().'/'.$name;
        File::ensureDirectoryExists(DatabaseSync::backupDir());
        $file->move(DatabaseSync::backupDir(), $name);

        try {
            $meta = $this->sync->readMeta([$path]);
        } catch (Throwable $e) {
            File::delete($path);

            return back()->withErrors('File không phải bản sao lưu hợp lệ: '.$e->getMessage());
        }

        $this->sync->log('backup-upload', ['user' => auth()->user()->email, 'backup' => $name, 'source' => $meta['source'] ?? null]);

        return redirect()->route('admin.system.index', ['tab' => 'backups'])->with('status', "Đã tải lên {$name}. Bấm \"Khôi phục\" để áp dụng.");
    }

    public function backupRestore(string $name): RedirectResponse
    {
        $path = $this->sync->backupPath($name) ?? abort(404);
        @set_time_limit(0);

        try {
            $result = $this->sync->restore($path);
        } catch (Throwable $e) {
            return redirect()->route('admin.system.index', ['tab' => 'backups'])->withErrors('Khôi phục thất bại, dữ liệu không thay đổi: '.$e->getMessage());
        }

        $this->sync->log('restore', [
            'user' => auth()->user()->email,
            'from' => $name,
            'tables' => $result['tables'],
            'backup' => $result['backup'] ? basename($result['backup']) : null,
        ]);

        $rows = array_sum($result['tables']);

        return redirect()->route('admin.system.index', ['tab' => 'backups'])->with('status', sprintf(
            'Đã khôi phục %d bảng (%s dòng) từ %s. Dữ liệu trước khi khôi phục được lưu ở %s.',
            count($result['tables']), number_format($rows), $name, $result['backup'] ? basename($result['backup']) : '-'
        ));
    }

    public function backupDelete(string $name): RedirectResponse
    {
        $path = $this->sync->backupPath($name) ?? abort(404);
        File::delete($path);

        return redirect()->route('admin.system.index', ['tab' => 'backups'])->with('status', "Đã xoá {$name}.");
    }

    // ------------------------------------------------------------------

    private function systemInfo(): array
    {
        $link = public_path('storage');

        $check = fn (bool $ok, string $yes, string $no) => ['ok' => $ok, 'text' => $ok ? $yes : $no];

        return [
            'Ứng dụng' => [
                'Tên' => config('app.name'),
                'Môi trường (APP_ENV)' => app()->environment(),
                'Chế độ debug' => $check(! config('app.debug') || ! app()->isProduction(), config('app.debug') ? 'Bật' : 'Tắt', 'Bật trên production — nên tắt'),
                'URL' => config('app.url'),
                'Laravel' => app()->version(),
                'PHP' => PHP_VERSION,
                'Múi giờ' => config('app.timezone'),
            ],
            'Cơ sở dữ liệu' => [
                'Driver' => DB::getDriverName(),
                'Tên CSDL' => DB::getDatabaseName(),
                'Số tài khoản' => User::count(),
            ],
            'Máy chủ' => [
                'memory_limit' => ini_get('memory_limit'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'max_execution_time' => ini_get('max_execution_time').'s',
                'Dung lượng trống' => ($free = @disk_free_space(base_path())) ? number_format($free / 1073741824, 2).' GB' : 'không rõ',
            ],
            'Kiểm tra' => [
                'Storage link (public/storage)' => $check(is_link($link) && is_dir($link), 'Đã có', 'Chưa có — chạy "Tạo storage link"'),
                'Ghi được storage/' => $check(is_writable(storage_path()), 'Có', 'Không'),
                'Ghi được bootstrap/cache' => $check(is_writable(base_path('bootstrap/cache')), 'Có', 'Không'),
                'Ghi được .env' => $check($this->env->isWritable(), 'Có', 'Không — chỉ sửa được qua File Manager'),
                'Cache config' => app()->configurationIsCached() ? 'Đang bật' : 'Không',
                'Cache route' => app()->routesAreCached() ? 'Đang bật' : 'Không',
                'Đồng bộ CSDL' => $check((bool) config('dbsync.enabled') && filled(config('dbsync.token')), 'Bật', 'Tắt'),
                'Đồng bộ thư mục' => $check((bool) config('dbsync.files_enabled') && filled(config('dbsync.token')), 'Bật', 'Tắt'),
            ],
        ];
    }
}
