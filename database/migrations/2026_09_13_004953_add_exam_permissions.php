<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach (['exams', 'exam_registrations'] as $module) {
            $definition = config("permissions.modules.$module");

            foreach ($definition['actions'] as $action) {
                if (DB::table('permissions')->where('module', $module)->where('action', $action)->exists()) {
                    continue;
                }

                $rows[] = [
                    'module' => $module,
                    'action' => $action,
                    'label' => $definition['label'].' - '.config("permissions.actions.$action", $action),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table('permissions')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('module', ['exams', 'exam_registrations'])->delete();
    }
};
