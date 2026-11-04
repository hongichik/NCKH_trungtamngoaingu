<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đưa tài khoản admin@gmail.com về id = 1 (chủ hệ thống, toàn quyền).
 * Nếu id 1 đang thuộc tài khoản khác, tài khoản đó được chuyển sang id mới.
 * Mọi bảng tham chiếu tới users.id (bài viết, trang, phân quyền...) được cập nhật theo.
 */
return new class extends Migration
{
    private const OWNER_EMAIL = 'admin@gmail.com';

    public function up(): void
    {
        $owner = DB::table('users')->where('email', self::OWNER_EMAIL)->first();

        if (! $owner) {
            return;
        }

        if ((int) $owner->id !== 1) {
            $refs = $this->references();

            DB::transaction(function () use ($owner, $refs) {
                if (DB::table('users')->where('id', 1)->exists()) {
                    $this->moveUser(1, (int) DB::table('users')->max('id') + 1, $refs);
                }
                $this->moveUser((int) $owner->id, 1, $refs);
            });
        }

        DB::table('users')->where('id', 1)->update(['is_admin' => true]);
    }

    public function down(): void
    {
        // Không hoàn tác: id cũ không còn ý nghĩa sau khi dữ liệu liên quan đã được cập nhật.
    }

    /**
     * Chuyển user sang id mới mà không cần tắt kiểm tra khoá ngoại:
     * tạo bản sao với id mới -> chuyển các tham chiếu -> xoá bản cũ.
     */
    private function moveUser(int $from, int $to, array $refs): void
    {
        $row = (array) DB::table('users')->where('id', $from)->first();

        // Email là unique: đổi tạm email bản cũ để chèn được bản mới.
        DB::table('users')->where('id', $from)->update(['email' => $row['email'].'#moving-'.$from]);
        DB::table('users')->insert(['id' => $to] + $row);

        foreach ($refs as [$table, $column]) {
            DB::table($table)->where($column, $from)->update([$column => $to]);
        }

        DB::table('users')->where('id', $from)->delete();
    }

    /**
     * @return list<array{0: string, 1: string}> các cặp [bảng, cột] trỏ tới users.id
     */
    private function references(): array
    {
        $refs = [];

        foreach (Schema::getTables() as $table) {
            foreach (Schema::getForeignKeys($table['name']) as $fk) {
                if ($fk['foreign_table'] === 'users') {
                    foreach ($fk['columns'] as $column) {
                        $refs[$table['name'].'.'.$column] = [$table['name'], $column];
                    }
                }
            }
        }

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            $refs['sessions.user_id'] = ['sessions', 'user_id'];
        }

        return array_values($refs);
    }
};
