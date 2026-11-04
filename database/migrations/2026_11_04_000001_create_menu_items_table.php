<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location', 50)->index();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('title');
            // route | page | category | url
            $table->string('link_type', 20)->default('route');
            $table->string('link_value', 500)->nullable();
            $table->boolean('open_new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        // Seed with the menu that used to be hard-coded in layouts/app.blade.php,
        // so the public site looks identical right after migrating.
        $now = now();
        $defaults = [
            'header' => [
                ['Trang chủ', 'home'],
                ['Tin tức', 'blog.index'],
                ['Đăng ký thi HSK', 'portal.auth.hsk'],
                ['Đăng ký thi Chứng chỉ Tin học', 'portal.auth.cntt'],
                ['Tra cứu chứng chỉ', 'certificates.lookup'],
                ['Liên hệ', 'contact.create'],
            ],
            'footer_explore' => [
                ['Trang chủ', 'home'],
                ['Tin tức', 'blog.index'],
                ['Thư viện ảnh', 'galleries.index'],
            ],
            'footer_support' => [
                ['Tra cứu chứng chỉ', 'certificates.lookup'],
                ['Liên hệ', 'contact.create'],
            ],
        ];

        $rows = [];
        foreach ($defaults as $location => $items) {
            foreach ($items as $i => [$title, $key]) {
                $rows[] = [
                    'location' => $location,
                    'parent_id' => null,
                    'title' => $title,
                    'link_type' => 'route',
                    'link_value' => $key,
                    'open_new_tab' => false,
                    'is_active' => true,
                    'order' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('menu_items')->insert($rows);

        // Permission rows for the new "menus" module.
        $definition = config('permissions.modules.menus');
        foreach ($definition['actions'] as $action) {
            if (DB::table('permissions')->where('module', 'menus')->where('action', $action)->exists()) {
                continue;
            }
            DB::table('permissions')->insert([
                'module' => 'menus',
                'action' => $action,
                'label' => $definition['label'].' - '.config("permissions.actions.$action", $action),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('module', 'menus')->delete();
        Schema::dropIfExists('menu_items');
    }
};
