<?php

namespace App\Support\Db;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Xoá khoá ngoại chỉ khi nó thật sự tồn tại.
 *
 * Trên hosting MySQL dùng MyISAM (hoặc CSDL được nhập mà không có ràng buộc) các khoá ngoại không tồn tại,
 * `dropForeign()` sẽ ném lỗi 1091 và làm migration dừng giữa chừng. Dùng trong migration thay cho dropForeign.
 */
final class Fk
{
    /** Xoá mọi khoá ngoại gắn trên cột $column của bảng $table nếu có. */
    public static function dropIfExists(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));

            return;
        }

        $names = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME')->unique();

        foreach ($names as $name) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($name));
        }
    }
}
