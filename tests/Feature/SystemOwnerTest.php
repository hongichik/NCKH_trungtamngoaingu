<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DbSync\DatabaseSync;
use App\Support\EnvEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemOwnerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $staff;

    private string $envPath;

    private string $filesDir = 'storage/app/dbsync-test-files';

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::forceCreate(['name' => 'Owner', 'email' => 'admin@gmail.com', 'password' => bcrypt('x'), 'is_admin' => 0]);
        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'boss@x.x', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $this->staff = User::forceCreate(['name' => 'Staff', 'email' => 'staff@x.x', 'password' => bcrypt('x'), 'is_admin' => 0]);

        // Không bao giờ đụng vào .env thật trong test.
        $this->envPath = storage_path('framework/testing/env-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists(dirname($this->envPath));
        File::put($this->envPath, "APP_NAME=Test\nAPP_ENV=local\nAPP_DEBUG=true\nAPP_URL=http://localhost\n# ghi chú giữ nguyên\nDB_PASSWORD=\"p@ss w0rd\"\n");
        $this->app->instance(EnvEditor::class, new EnvEditor($this->envPath));

        config(['dbsync.token' => str_repeat('t', 40), 'dbsync.file_dirs' => [$this->filesDir], 'dbsync.files_enabled' => true]);
    }

    protected function tearDown(): void
    {
        File::delete($this->envPath);
        File::deleteDirectory(base_path($this->filesDir));
        foreach (['db-sync-backups', 'db-sync-sessions', 'db-sync-tmp', 'env-backups'] as $d) {
            File::deleteDirectory(storage_path('app/'.$d));
        }
        File::delete(DatabaseSync::logPath());
        parent::tearDown();
    }

    // ---------------------------------------------------------------- tài khoản

    public function test_user_id_1_is_owner_and_always_admin(): void
    {
        $this->assertTrue($this->owner->isOwner());
        $this->assertTrue($this->owner->fresh()->is_admin);
        $this->assertTrue($this->owner->hasPermission('anything', 'delete'));
        $this->assertFalse($this->admin->isOwner());

        $this->expectException(\LogicException::class);
        $this->owner->delete();
    }

    public function test_migration_moves_admin_gmail_to_id_1_and_updates_references(): void
    {
        DB::table('users')->delete();
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Khác', 'email' => 'other@x.x', 'password' => 'x', 'is_admin' => 0],
            ['id' => 2, 'name' => 'Admin', 'email' => 'admin@gmail.com', 'password' => 'x', 'is_admin' => 1],
        ]);
        $permId = DB::table('permissions')->value('id');
        DB::table('permission_user')->insert(['permission_id' => $permId, 'user_id' => 1]);
        DB::table('pages')->insert(['title' => 'p', 'slug' => 'p', 'user_id' => 2, 'created_at' => now(), 'updated_at' => now()]);

        (require database_path('migrations/2026_11_04_000003_make_admin_gmail_the_system_owner.php'))->up();

        $this->assertSame('admin@gmail.com', DB::table('users')->where('id', 1)->value('email'));
        $this->assertSame(3, (int) DB::table('users')->where('email', 'other@x.x')->value('id'));
        $this->assertSame(1, (int) DB::table('pages')->where('slug', 'p')->value('user_id'));
        $this->assertSame(3, (int) DB::table('permission_user')->where('permission_id', $permId)->value('user_id'));
    }

    public function test_only_owner_can_open_system_panel(): void
    {
        $this->get('/admin/system')->assertRedirect('/admin/login');
        $this->actingAs($this->staff)->get('/admin/system')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/system')->assertForbidden();

        foreach (array_keys(\App\Http\Controllers\Admin\SystemController::TABS) as $tab) {
            $this->actingAs($this->owner)->get('/admin/system?tab='.$tab)->assertOk();
        }
        // (Dashboard dùng hàm riêng của MySQL nên không render được trên SQLite trong test.)
        $this->actingAs($this->owner)->get('/admin/users')->assertOk()->assertSee('Quản trị hệ thống')->assertSee('Chủ hệ thống');
        $this->actingAs($this->admin)->get('/admin/users')->assertOk()->assertDontSee('Quản trị hệ thống');
    }

    public function test_owner_can_edit_any_account_and_grant_admin(): void
    {
        // Admin thường không sửa được admin khác.
        $this->actingAs($this->admin)->get("/admin/users/{$this->owner->id}/edit")->assertForbidden();

        $this->actingAs($this->owner)->get("/admin/users/{$this->admin->id}/edit")->assertOk();
        $this->actingAs($this->owner)->put("/admin/users/{$this->staff->id}", [
            'name' => 'Staff mới', 'email' => 'staff@x.x', 'is_admin' => 1,
        ])->assertRedirect();
        $this->assertTrue($this->staff->fresh()->is_admin);

        $this->actingAs($this->owner)->put("/admin/users/{$this->admin->id}", [
            'name' => 'Admin', 'email' => 'boss@x.x', 'is_admin' => 0, 'password' => 'newpass', 'password_confirmation' => 'newpass',
        ])->assertRedirect();
        $this->assertFalse($this->admin->fresh()->is_admin);

        // Không thể tự hạ quyền hay xoá chủ hệ thống.
        $this->actingAs($this->owner)->put("/admin/users/{$this->owner->id}", ['name' => 'O', 'email' => 'admin@gmail.com', 'is_admin' => 0]);
        $this->assertTrue($this->owner->fresh()->is_admin);
        $this->actingAs($this->owner)->delete("/admin/users/{$this->owner->id}")->assertSessionHasErrors();
        $this->assertNotNull($this->owner->fresh());
    }

    public function test_non_owner_admin_cannot_grant_admin(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'N', 'email' => 'n@x.x', 'password' => 'secret', 'password_confirmation' => 'secret', 'is_admin' => 1,
        ])->assertRedirect();
        $this->assertFalse(User::where('email', 'n@x.x')->first()->is_admin);
    }

    // ---------------------------------------------------------------- cấu hình

    public function test_owner_updates_app_config_in_env_preserving_other_lines(): void
    {
        $this->actingAs($this->owner)->post('/admin/system/config', [
            'APP_NAME' => 'Trung tâm Ngoại ngữ', 'APP_ENV' => 'production', 'APP_DEBUG' => 0, 'APP_URL' => 'https://demo.test',
        ])->assertRedirect()->assertSessionHas('status');

        $env = File::get($this->envPath);
        $this->assertStringContainsString('APP_NAME="Trung tâm Ngoại ngữ"', $env);
        $this->assertStringContainsString("APP_DEBUG=false\n", $env);
        $this->assertStringContainsString("APP_ENV=production\n", $env);
        $this->assertStringContainsString("# ghi chú giữ nguyên\nDB_PASSWORD=\"p@ss w0rd\"", $env);
        $this->assertNotEmpty(File::glob(storage_path('app/env-backups/.env-*')));

        $this->actingAs($this->staff)->post('/admin/system/config', ['APP_NAME' => 'x'])->assertForbidden();
    }

    public function test_owner_updates_sync_settings_and_token(): void
    {
        $this->actingAs($this->owner)->post('/admin/system/sync', [
            'enabled' => 0, 'files_enabled' => 1, 'file_dirs' => "storage/app/public\npublic/images",
            'remote_url' => 'https://demo.cnttuhl.site/', 'exclude' => ['users'], 'backup_keep' => 5,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $editor = new EnvEditor($this->envPath);
        $this->assertSame('false', $editor->get('DB_SYNC_ENABLED'));
        $this->assertSame('true', $editor->get('DB_SYNC_FILES_ENABLED'));
        $this->assertSame('storage/app/public,public/images', $editor->get('DB_SYNC_FILE_DIRS'));
        $this->assertSame('https://demo.cnttuhl.site', $editor->get('DB_SYNC_REMOTE_URL'));
        $this->assertSame('users', $editor->get('DB_SYNC_EXCLUDE'));

        // Thư mục nguy hiểm bị từ chối.
        foreach (['app', '../etc', 'storage/app/db-sync-backups', 'storage/app', '.'] as $bad) {
            $this->actingAs($this->owner)->post('/admin/system/sync', ['file_dirs' => $bad, 'backup_keep' => 5])->assertSessionHasErrors('file_dirs');
        }

        $this->actingAs($this->owner)->post('/admin/system/token')->assertSessionHas('new_token');
        $this->assertSame(64, strlen($editor->get('DB_SYNC_TOKEN')));
    }

    public function test_disabled_db_sync_rejects_api_calls(): void
    {
        config(['dbsync.enabled' => false]);
        $this->postJson('/api/db-sync/commit?id='.str_repeat('a', 32), [], ['Authorization' => 'Bearer '.str_repeat('t', 40)])->assertForbidden();
        $this->getJson('/api/db-sync/status', ['Authorization' => 'Bearer '.str_repeat('t', 40)])->assertOk()->assertJson(['db_sync_enabled' => false]);
    }

    // ---------------------------------------------------------------- lệnh

    public function test_owner_runs_whitelisted_commands_only(): void
    {
        $this->actingAs($this->owner)->post('/admin/system/command', ['command' => 'view-clear'])
            ->assertRedirect()->assertSessionHas('command_result', fn ($r) => $r['ok'] === true);
        $this->actingAs($this->owner)->post('/admin/system/command', ['command' => 'db:wipe'])->assertNotFound();
        $this->actingAs($this->admin)->post('/admin/system/command', ['command' => 'view-clear'])->assertForbidden();
    }

    // ---------------------------------------------------------------- sao lưu

    public function test_owner_creates_and_restores_backup(): void
    {
        $this->actingAs($this->owner)->post('/admin/system/backups')->assertSessionHas('status');
        $name = app(DatabaseSync::class)->backups()[0]['name'];
        $this->assertStringStartsWith('manual-', $name);

        // Thay đổi dữ liệu rồi khôi phục.
        $this->staff->update(['name' => 'Đã đổi']);
        $this->actingAs($this->owner)->post("/admin/system/backups/{$name}/restore")->assertSessionHas('status');
        $this->assertSame('Staff', $this->staff->fresh()->name);

        $this->actingAs($this->owner)->get("/admin/system/backups/{$name}")->assertOk();
        $this->actingAs($this->owner)->get('/admin/system/backups/..%2F..%2F.env')->assertNotFound();
        $this->actingAs($this->owner)->delete("/admin/system/backups/{$name}")->assertSessionHas('status');
        $this->assertNull(app(DatabaseSync::class)->backupPath($name));

        $this->actingAs($this->owner)->get('/admin/system?tab=logs')->assertSee('Khôi phục');
    }

    public function test_owner_uploads_backup_from_another_machine(): void
    {
        $sync = app(DatabaseSync::class);
        $tmp = storage_path('framework/testing/up-'.bin2hex(random_bytes(3)).'.ndjson.gz');
        $sync->exportToFile(['users'], $tmp);

        $this->actingAs($this->owner)->post('/admin/system/backups/upload', [
            'file' => new UploadedFile($tmp, 'from-local.ndjson.gz', null, null, true),
        ])->assertSessionHas('status');
        $this->assertStringStartsWith('upload-', $sync->backups()[0]['name']);

        $bad = UploadedFile::fake()->createWithContent('bad.gz', 'not a backup');
        $this->actingAs($this->owner)->post('/admin/system/backups/upload', ['file' => $bad])->assertSessionHasErrors();
    }

    // ---------------------------------------------------------------- đồng bộ file

    private function api(string $method, string $uri, string $body = '', array $json = [])
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer '.str_repeat('t', 40), 'HTTP_ACCEPT' => 'application/json'];
        if ($json) {
            return $this->json($method, $uri, $json, ['Authorization' => $server['HTTP_AUTHORIZATION']]);
        }

        return $this->call($method, $uri, [], [], [], $server + ['CONTENT_TYPE' => 'application/octet-stream'], $body);
    }

    public function test_file_sync_api_put_get_manifest_delete(): void
    {
        $path = $this->filesDir.'/posts/anh.jpg';
        $this->api('POST', '/api/db-sync/files/put?path='.urlencode($path).'&mtime=1700000000', 'JPEGDATA')->assertOk();

        $this->assertSame('JPEGDATA', File::get(base_path($path)));
        $this->assertSame(1700000000, filemtime(base_path($path)));

        $this->api('GET', '/api/db-sync/files/manifest')->assertOk()->assertJson(['files' => [$path => [8, 1700000000]]]);
        $download = $this->api('GET', '/api/db-sync/files/get?path='.urlencode($path))->assertOk();
        $this->assertSame('JPEGDATA', File::get($download->baseResponse->getFile()->getPathname()));

        $this->api('POST', '/api/db-sync/files/delete', '', ['paths' => [$path]])->assertOk()->assertJson(['deleted' => [$path]]);
        $this->assertFileDoesNotExist(base_path($path));
    }

    public function test_file_sync_blocks_dangerous_paths(): void
    {
        foreach ([
            $this->filesDir.'/shell.php',
            $this->filesDir.'/anh.php.jpg',
            $this->filesDir.'/.htaccess',
            $this->filesDir.'/../../../.env',
            'app/Models/User.php',
            '/etc/passwd',
            'storage/app/db-sync-backups/x.gz',
        ] as $bad) {
            $this->api('POST', '/api/db-sync/files/put?path='.urlencode($bad), 'x')->assertStatus(422);
        }
        $this->assertFileDoesNotExist(base_path($this->filesDir.'/shell.php'));

        config(['dbsync.files_enabled' => false]);
        $this->api('GET', '/api/db-sync/files/manifest')->assertForbidden();
    }

    public function test_files_push_command_end_to_end(): void
    {
        // "Máy chủ" và "máy local" dùng chung thư mục trong test, nên giả lập máy chủ
        // bằng cách đổi thư mục đồng bộ của API sang một thư mục khác.
        $local = $this->filesDir.'/local';
        $remote = $this->filesDir.'/remote';
        File::ensureDirectoryExists(base_path($local.'/img'));
        File::put(base_path($local.'/img/a.jpg'), 'AAA');
        File::put(base_path($local.'/img/b.png'), 'BBBB');
        File::put(base_path($local.'/hack.php'), '<?php');

        Http::fake(function (ClientRequest $req) use ($local, $remote) {
            config(['dbsync.file_dirs' => [$remote]]);
            parse_str((string) parse_url($req->url(), PHP_URL_QUERY), $q);
            if (isset($q['path'])) {
                $q['path'] = str_replace($local, $remote, $q['path']);
            }
            unset($q['dirs']);
            $uri = parse_url($req->url(), PHP_URL_PATH).'?'.http_build_query($q);
            $res = str_ends_with($uri, '/files/delete?') || str_contains($uri, '/files/report')
                ? $this->json('POST', $uri, $req->data(), ['Authorization' => $req->header('Authorization')[0]])
                : $this->call($req->method(), $uri, [], [], [], ['HTTP_AUTHORIZATION' => $req->header('Authorization')[0], 'HTTP_ACCEPT' => 'application/json'], $req->body());
            config(['dbsync.file_dirs' => [$local]]);
            // Máy chủ trả manifest theo thư mục của nó; quy đổi về đường dẫn local để so sánh.
            $body = str_replace(str_replace('/', '\/', $remote), str_replace('/', '\/', $local), $res->getContent());
            $body = str_replace('"dirs":["'.str_replace('/', '\/', $local).'"]', '"dirs":["'.str_replace('/', '\/', $local).'"]', $body);

            return Http::response($body, $res->getStatusCode());
        });

        config(['dbsync.file_dirs' => [$local], 'dbsync.remote_url' => 'https://server.test']);
        $this->artisan('files:sync-push', ['--force' => true])->expectsOutputToContain('đã gửi 2')->assertSuccessful();

        $this->assertSame('AAA', File::get(base_path($remote.'/img/a.jpg')));
        $this->assertSame('BBBB', File::get(base_path($remote.'/img/b.png')));
        $this->assertFileDoesNotExist(base_path($remote.'/hack.php'));

        // Lần 2: không có gì thay đổi.
        $this->artisan('files:sync-push', ['--force' => true])->expectsOutputToContain('Không có gì thay đổi')->assertSuccessful();
    }
}
