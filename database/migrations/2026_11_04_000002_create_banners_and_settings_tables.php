<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            // image: a finished poster shown full-width; text: heading/text drawn over a colour or photo.
            $table->string('type', 10)->default('image');
            $table->string('title');
            $table->string('badge')->nullable();
            $table->text('description')->nullable();
            $table->text('bullet_points')->nullable();
            $table->string('image')->nullable();
            $table->string('image_mobile')->nullable();
            $table->string('background', 20)->default('brand');
            $table->string('button_text')->nullable();
            $table->string('button_url', 500)->nullable();
            $table->boolean('open_new_tab')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        $now = now();

        // The three slides that used to be hard-coded in public/home.blade.php.
        DB::table('banners')->insert([
            [
                'type' => 'text', 'title' => config('app.name'), 'badge' => 'Trực thuộc Trường Đại học Hạ Long',
                'description' => 'Đào tạo, luyện thi và cấp chứng chỉ ngoại ngữ, tin học, HSK chuẩn đầu ra. Tra cứu chứng chỉ trực tuyến nhanh chóng, chính xác.',
                'bullet_points' => null, 'image' => null, 'image_mobile' => null, 'background' => 'brand',
                'button_text' => 'Hotline 0974 024 655', 'button_url' => 'tel:0974024655', 'open_new_tab' => false,
                'starts_at' => null, 'ends_at' => null, 'is_active' => true, 'order' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'type' => 'text', 'title' => 'Tuyển sinh các lớp Ngoại ngữ · Tin học · HSK', 'badge' => 'Tuyển sinh',
                'description' => null,
                'bullet_points' => "Luyện thi chứng chỉ ứng dụng CNTT\nLuyện thi chứng chỉ tiếng Trung quốc tế HSK\nTiếng Anh, tiếng Nhật, tiếng Hàn mọi trình độ",
                'image' => null, 'image_mobile' => null, 'background' => 'accent',
                'button_text' => 'Đăng ký ngay: 0974 024 655', 'button_url' => 'tel:0974024655', 'open_new_tab' => false,
                'starts_at' => null, 'ends_at' => null, 'is_active' => true, 'order' => 2, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'type' => 'image', 'title' => 'Thông báo tuyển sinh các lớp Ngoại ngữ - Tin học - HSK', 'badge' => null,
                'description' => null, 'bullet_points' => null,
                'image' => 'slideshow/hero-image-tuyen-sinh-3-2.jpg', 'image_mobile' => null, 'background' => 'brand',
                'button_text' => null, 'button_url' => null, 'open_new_tab' => false,
                'starts_at' => null, 'ends_at' => null, 'is_active' => true, 'order' => 3, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // Current site texts, so nothing changes visually until an admin edits them.
        $defaults = [
            'site_name' => config('app.name'),
            'site_intro' => 'Đào tạo, luyện thi và cấp chứng chỉ ngoại ngữ - tin học. Đồng hành cùng học viên trên hành trình chinh phục chứng chỉ.',
            'parent_org_name' => 'Trường Đại học Hạ Long',
            'parent_org_url' => 'https://uhl.edu.vn/',
            'meta_description' => 'Trung tâm Ngoại ngữ - Tin học: đào tạo, luyện thi và cấp chứng chỉ ngoại ngữ, tin học uy tín. Tra cứu chứng chỉ trực tuyến nhanh chóng, chính xác.',
            'color_brand' => '#0a4a75',
            'color_accent' => '#ef5823',
            'contact_address' => config('site.contact.address'),
            'contact_staff' => json_encode(config('site.contact.staff', []), JSON_UNESCAPED_UNICODE),
            'contact_email' => null,
            'map_query' => null,
            'social_facebook' => null,
            'social_zalo' => null,
            'social_youtube' => null,
            'home_fields_title' => 'Lĩnh vực đào tạo',
            'home_fields_subtitle' => '3 lĩnh vực đào tạo chính của trung tâm',
            'home_fields' => json_encode([
                ['icon' => 'bi-laptop', 'title' => 'Chứng chỉ Tin học (CNTT)', 'desc' => 'Đào tạo và tổ chức thi chứng chỉ ứng dụng công nghệ thông tin theo chuẩn đầu ra.'],
                ['icon' => 'bi-translate', 'title' => 'Ngoại ngữ', 'desc' => 'Đào tạo tiếng Anh, tiếng Nhật, tiếng Hàn cho mọi trình độ, từ mất gốc đến nâng cao.'],
                ['icon' => 'bi-mortarboard', 'title' => 'Chứng chỉ HSK', 'desc' => 'Đào tạo và tổ chức thi chứng chỉ tiếng Trung Quốc tế HSK (HSK3 - HSK5).'],
            ], JSON_UNESCAPED_UNICODE),
            'home_reasons_title' => 'Vì sao chọn '.config('app.name'),
            'home_reasons_subtitle' => 'Đơn vị đào tạo trực thuộc Trường Đại học Hạ Long',
            'home_reasons' => json_encode([
                ['icon' => 'bi-patch-check', 'title' => 'Trực thuộc trường đại học', 'desc' => 'Chứng chỉ được cấp bởi đơn vị trực thuộc Trường Đại học Hạ Long, đảm bảo giá trị pháp lý.'],
                ['icon' => 'bi-person-workspace', 'title' => 'Đội ngũ giảng viên', 'desc' => 'Giảng viên giàu kinh nghiệm, tận tâm đồng hành cùng học viên trong suốt khóa học.'],
                ['icon' => 'bi-search-heart', 'title' => 'Tra cứu minh bạch', 'desc' => 'Hệ thống tra cứu chứng chỉ trực tuyến, kiểm tra thông tin nhanh chóng, chính xác.'],
                ['icon' => 'bi-calendar2-check', 'title' => 'Lịch thi rõ ràng', 'desc' => 'Thông tin tuyển sinh, lịch thi và kết quả được cập nhật thường xuyên trên website.'],
                ['icon' => 'bi-building', 'title' => 'Cơ sở vật chất', 'desc' => 'Phòng học và phòng thi đạt chuẩn trong khuôn viên Trường Đại học Hạ Long.'],
                ['icon' => 'bi-headset', 'title' => 'Hỗ trợ tận tâm', 'desc' => 'Đội ngũ tư vấn sẵn sàng hỗ trợ học viên trước, trong và sau khóa học.'],
            ], JSON_UNESCAPED_UNICODE),
        ];
        DB::table('settings')->insert(collect($defaults)->map(fn ($value, $key) => [
            'key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now,
        ])->values()->all());

        foreach (['banners', 'settings'] as $module) {
            $definition = config("permissions.modules.$module");
            foreach ($definition['actions'] as $action) {
                if (DB::table('permissions')->where('module', $module)->where('action', $action)->exists()) {
                    continue;
                }
                DB::table('permissions')->insert([
                    'module' => $module,
                    'action' => $action,
                    'label' => $definition['label'].' - '.config("permissions.actions.$action", $action),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('module', ['banners', 'settings'])->delete();
        Schema::dropIfExists('settings');
        Schema::dropIfExists('banners');
    }
};
