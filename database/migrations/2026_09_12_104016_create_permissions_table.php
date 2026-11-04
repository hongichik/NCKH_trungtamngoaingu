<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('action');
            $table->string('label');
            $table->timestamps();
            $table->unique(['module', 'action']);
        });

        $now = now();
        $rows = [];

        foreach (config('permissions.modules') as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $rows[] = [
                    'module' => $module,
                    'action' => $action,
                    'label' => $definition['label'].' - '.config("permissions.actions.$action", $action),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('permissions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
