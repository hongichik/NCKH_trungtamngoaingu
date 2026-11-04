<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DbSync\DatabaseSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DbSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = str_repeat('a', 40);
        $this->tmp = storage_path('framework/testing/dbsync-'.bin2hex(random_bytes(4)));
        config(['dbsync.token' => $this->token, 'dbsync.remote_url' => 'https://server.test']);
    }

    protected function tearDown(): void
    {
        foreach (['db-sync-backups', 'db-sync-sessions', 'db-sync-tmp'] as $d) {
            File::deleteDirectory(storage_path('app/'.$d));
        }
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    private function auth(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'HTTP_ACCEPT' => 'application/json'];
    }

    private function sendChunk(string $id, int $seq, string $body)
    {
        return $this->call('POST', "/api/db-sync/chunk?id={$id}&seq={$seq}", [], [], [], $this->auth() + ['CONTENT_TYPE' => 'application/gzip'], $body);
    }

    private function commit(string $id)
    {
        return $this->call('POST', "/api/db-sync/commit?id={$id}&migrate=0", [], [], [], $this->auth());
    }

    /** Tạo dữ liệu users kiểu "từ máy khác" rồi khôi phục DB về trạng thái cũ. */
    private function remoteUsersPayload(int $count, int $partBytes = 1 << 30): array
    {
        DB::table('users')->delete();
        for ($i = 1; $i <= $count; $i++) {
            DB::table('users')->insert(['id' => $i + 100, 'name' => "Người {$i}", 'email' => "u{$i}@x.x", 'password' => 'x']);
        }
        $parts = app(DatabaseSync::class)->exportToParts(['users'], $this->tmp, $partBytes);
        DB::table('users')->delete();
        User::forceCreate(['name' => 'old', 'email' => 'old@x.x', 'password' => 'x']);

        return $parts;
    }

    public function test_api_is_disabled_without_token(): void
    {
        config(['dbsync.token' => null]);
        $this->getJson('/api/db-sync/status')->assertNotFound();
    }

    public function test_rejects_wrong_token(): void
    {
        $this->getJson('/api/db-sync/status', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
        $this->postJson('/api/db-sync/commit?id='.str_repeat('a', 32), [], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    }

    public function test_status_lists_tables_without_excluded_ones(): void
    {
        $res = $this->getJson('/api/db-sync/status', ['Authorization' => 'Bearer '.$this->token])->assertOk();
        $this->assertArrayHasKey('users', $res->json('tables'));
        $this->assertArrayNotHasKey('migrations', $res->json('tables'));
        $this->assertArrayNotHasKey('sessions', $res->json('tables'));
    }

    public function test_multi_part_upload_replaces_data_and_creates_backup(): void
    {
        $parts = $this->remoteUsersPayload(300, 2000);
        $this->assertGreaterThan(5, count($parts));

        $id = bin2hex(random_bytes(16));
        foreach ($parts as $i => $p) {
            $this->sendChunk($id, $i + 1, file_get_contents($p))->assertOk();
        }
        // Chưa commit thì DB chưa đổi.
        $this->assertSame(['old'], DB::table('users')->pluck('name')->all());

        $res = $this->commit($id)->assertOk()->assertJson(['ok' => true, 'tables' => ['users' => 300]]);

        $this->assertSame(300, DB::table('users')->count());
        $this->assertSame('Người 1', DB::table('users')->where('id', 101)->value('name'));
        $this->assertFileExists(storage_path('app/db-sync-backups/'.$res->json('backup')));
        $this->assertDirectoryDoesNotExist(storage_path("app/db-sync-sessions/{$id}"));
    }

    public function test_empty_table_in_payload_clears_target_table(): void
    {
        DB::table('users')->delete();
        $parts = app(DatabaseSync::class)->exportToParts(['users'], $this->tmp, 1 << 30);
        User::forceCreate(['name' => 'old', 'email' => 'old@x.x', 'password' => 'x']);

        $id = bin2hex(random_bytes(16));
        $this->sendChunk($id, 1, file_get_contents($parts[0]))->assertOk();
        $this->commit($id)->assertOk()->assertJson(['tables' => ['users' => 0]]);
        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_rejects_invalid_chunks_and_ids(): void
    {
        $this->sendChunk(bin2hex(random_bytes(16)), 1, 'not gzip')->assertStatus(422);
        $this->sendChunk('../../etc', 1, gzencode('x'))->assertStatus(422);
        $this->commit(bin2hex(random_bytes(16)))->assertStatus(422);

        $id = bin2hex(random_bytes(16));
        $this->sendChunk($id, 1, gzencode("{\"foo\":1}\n"))->assertOk();
        $this->commit($id)->assertStatus(422);
    }

    public function test_commit_refuses_when_server_is_missing_migrations(): void
    {
        $meta = ['meta' => ['format' => DatabaseSync::FORMAT_VERSION, 'migrations' => ['2099_01_01_000000_future']]];
        $id = bin2hex(random_bytes(16));
        $this->sendChunk($id, 1, gzencode(json_encode($meta)."\n{\"table\":\"users\"}\n"))->assertOk();

        $this->commit($id)->assertStatus(409)->assertJsonFragment(['missing_migrations' => ['2099_01_01_000000_future']]);
    }

    public function test_unknown_tables_and_columns_are_ignored(): void
    {
        $lines = [
            json_encode(['meta' => ['format' => DatabaseSync::FORMAT_VERSION, 'migrations' => []]]),
            '{"table":"users"}',
            json_encode(['row' => ['id' => 1, 'name' => 'a', 'email' => 'a@a.a', 'password' => 'x', 'cot_la' => 'z']]),
            '{"table":"bang_la"}',
            '{"row":{"id":1}}',
            '{"table":"migrations"}',
            '{"row":{"id":1,"migration":"x","batch":1}}',
        ];
        $id = bin2hex(random_bytes(16));
        $this->sendChunk($id, 1, gzencode(implode("\n", $lines)."\n"))->assertOk();

        $this->commit($id)->assertOk()
            ->assertJson(['tables' => ['users' => 1]])
            ->assertJsonFragment(['skipped' => ['bang_la', 'migrations']]);
    }

    public function test_export_endpoint_streams_decodable_payload(): void
    {
        User::forceCreate(['name' => 'x', 'email' => 'x@x.x', 'password' => 'x']);

        $res = $this->get('/api/db-sync/export?tables[]=users', ['Authorization' => 'Bearer '.$this->token])->assertOk();
        File::ensureDirectoryExists($this->tmp);
        file_put_contents($file = $this->tmp.'/dl.gz', $res->streamedContent());

        $sync = app(DatabaseSync::class);
        $this->assertSame(DatabaseSync::FORMAT_VERSION, $sync->readMeta([$file])['format']);
        $this->assertSame(['users'], $sync->tablesIn([$file]));
        $rows = collect(iterator_to_array($sync->readLines([$file]), false))->pluck('row')->filter()->values();
        $this->assertSame('x@x.x', $rows[0]['email']);
    }

    public function test_push_command_end_to_end(): void
    {
        // Chuyển các request HTTP của lệnh artisan vào chính ứng dụng này (đóng vai máy chủ).
        Http::fake(function (ClientRequest $req) {
            $path = parse_url($req->url(), PHP_URL_PATH).'?'.parse_url($req->url(), PHP_URL_QUERY);
            $res = $this->call($req->method(), $path, [], [], [], [
                'HTTP_AUTHORIZATION' => $req->header('Authorization')[0],
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/gzip',
            ], $req->body());

            return Http::response($res->getContent(), $res->getStatusCode());
        });
        config(['dbsync.part_bytes' => 1024]);

        $parts = $this->remoteUsersPayload(0); // chỉ để reset về 1 user "old"
        unset($parts);
        for ($i = 1; $i <= 50; $i++) {
            User::forceCreate(['name' => "Local {$i}", 'email' => "l{$i}@x.x", 'password' => 'x']);
        }

        $this->artisan('db:sync-push', ['--tables' => ['users'], '--force' => true, '--no-migrate' => true])
            ->expectsOutputToContain('Đồng bộ thành công')
            ->assertSuccessful();

        $this->assertSame(51, DB::table('users')->count());
        $this->assertGreaterThan(1, count(Http::recorded()) - 1); // nhiều phần + commit
    }
}
