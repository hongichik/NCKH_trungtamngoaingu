<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DbSync\DatabaseSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DbSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = str_repeat('a', 40);
        config(['dbsync.token' => $this->token]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/db-sync-backups'));
        parent::tearDown();
    }

    private function postPayload(string $body, ?string $token = null)
    {
        return $this->call('POST', '/api/db-sync/import?migrate=0', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.($token ?? $this->token),
            'CONTENT_TYPE' => 'application/gzip',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    public function test_api_is_disabled_without_token(): void
    {
        config(['dbsync.token' => null]);
        $this->getJson('/api/db-sync/status')->assertNotFound();
    }

    public function test_rejects_wrong_token(): void
    {
        $this->getJson('/api/db-sync/status', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
        $this->postPayload('x', 'wrong')->assertUnauthorized();
    }

    public function test_status_lists_tables_without_excluded_ones(): void
    {
        $res = $this->getJson('/api/db-sync/status', ['Authorization' => 'Bearer '.$this->token])->assertOk();
        $this->assertArrayHasKey('users', $res->json('tables'));
        $this->assertArrayNotHasKey('migrations', $res->json('tables'));
        $this->assertArrayNotHasKey('sessions', $res->json('tables'));
    }

    public function test_import_replaces_table_data_and_creates_backup(): void
    {
        User::forceCreate(['name' => 'old', 'email' => 'old@x.x', 'password' => 'x']);

        $sync = app(DatabaseSync::class);
        // Giả lập dữ liệu từ máy khác.
        DB::table('users')->delete();
        DB::table('users')->insert(['id' => 7, 'name' => 'Người mới', 'email' => 'new@x.x', 'password' => 'x']);
        $payload = $sync->export(['users']);
        DB::table('users')->delete();
        User::forceCreate(['name' => 'old', 'email' => 'old@x.x', 'password' => 'x']);

        $res = $this->postPayload($payload)->assertOk()->assertJson(['ok' => true, 'tables' => ['users' => 1]]);

        $this->assertSame(['Người mới'], DB::table('users')->pluck('name')->all());
        $this->assertSame(7, (int) DB::table('users')->value('id'));
        $this->assertFileExists(storage_path('app/db-sync-backups/'.$res->json('backup')));
    }

    public function test_import_rejects_invalid_payload(): void
    {
        $this->postPayload('not gzip')->assertStatus(422);
        $this->postPayload(gzencode('{"foo":1}'))->assertStatus(422);
    }

    public function test_import_refuses_when_server_is_missing_migrations(): void
    {
        $payload = json_decode(gzdecode(app(DatabaseSync::class)->export(['users'])), true);
        $payload['meta']['migrations'][] = '2099_01_01_000000_future_migration';

        $this->postPayload(gzencode(json_encode($payload)))->assertStatus(409)
            ->assertJsonFragment(['missing_migrations' => ['2099_01_01_000000_future_migration']]);
    }

    public function test_unknown_tables_and_columns_are_ignored(): void
    {
        $payload = json_decode(gzdecode(app(DatabaseSync::class)->export(['users'])), true);
        $payload['tables']['users'] = [['id' => 1, 'name' => 'a', 'email' => 'a@a.a', 'password' => 'x', 'cot_la' => 'z']];
        $payload['tables']['bang_la'] = [['id' => 1]];
        $payload['tables']['migrations'] = [['id' => 1, 'migration' => 'x', 'batch' => 1]];

        $this->postPayload(gzencode(json_encode($payload)))->assertOk()
            ->assertJson(['tables' => ['users' => 1]])
            ->assertJsonFragment(['skipped' => ['bang_la', 'migrations']]);
    }

    public function test_export_endpoint_returns_decodable_payload(): void
    {
        User::forceCreate(['name' => 'x', 'email' => 'x@x.x', 'password' => 'x']);

        $res = $this->get('/api/db-sync/export?tables[]=users', ['Authorization' => 'Bearer '.$this->token])->assertOk();
        $payload = app(DatabaseSync::class)->decode($res->getContent());

        $this->assertSame(['users'], array_keys($payload['tables']));
        $this->assertSame('x@x.x', $payload['tables']['users'][0]['email']);
    }
}
