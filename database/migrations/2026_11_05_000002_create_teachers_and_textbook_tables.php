<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Giáo viên: tài khoản riêng, đăng nhập ở cổng /giao-vien (không dùng chung với admin).
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // Một quyển giáo trình.
        Schema::create('textbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('language', 5);                // en | zh
            $table->string('level', 30)->nullable();      // A1, B1, HSK 2...
            $table->string('source_type', 10);            // pdf | docx | text
            $table->string('original_name')->nullable();
            $table->string('file_path')->nullable();      // file gốc (storage/app/private)
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('file_hash', 64)->nullable()->index();
            $table->unsignedInteger('page_count')->default(0);
            $table->json('outline')->nullable();          // mục lục điện tử / tiêu đề Word: [{title, page, level}]
            // uploading → extracting → ocr → detecting → review_units → processing → ready | failed
            $table->string('status', 20)->default('uploading');
            $table->string('detect_method', 20)->nullable(); // outline | pattern | ai | fallback | manual
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('textbook_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('textbook_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_no');
            $table->longText('text')->nullable();
            $table->unsignedInteger('char_count')->default(0);
            $table->boolean('needs_ocr')->default(false);
            $table->boolean('ocr_done')->default(false);
            $table->string('image_path')->nullable();     // ảnh trang để OCR
            $table->timestamps();

            $table->unique(['textbook_id', 'page_no']);
        });

        Schema::create('textbook_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('textbook_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->unsignedInteger('start_page');
            $table->unsignedInteger('end_page');
            $table->boolean('skipped')->default(false);   // bìa, phụ lục, đáp án...
            // pending → analyzing → ready | failed
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('textbook_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('textbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('textbook_units')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->longText('text');
            $table->unsignedInteger('size');              // số từ (EN) / số chữ (ZH)
            $table->json('labels')->nullable();           // reading, dialogue, vocabulary, grammar, exercise
            $table->char('hash', 64)->index();
            $table->json('analysis')->nullable();
            $table->string('status', 20)->default('pending'); // pending | done | failed
            $table->timestamps();
        });

        // Hồ sơ Unit: gộp từ các đoạn — đầu vào cho bước sinh bài tập.
        Schema::create('unit_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->unique()->constrained('textbook_units')->cascadeOnDelete();
            $table->json('data');
            $table->boolean('edited')->default(false);    // giáo viên đã sửa tay
            $table->timestamps();
        });

        Schema::create('audio_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('textbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('textbook_units')->nullOnDelete();
            $table->foreignId('suggested_unit_id')->nullable()->constrained('textbook_units')->nullOnDelete();
            $table->decimal('match_score', 5, 3)->nullable();
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->longText('transcript')->nullable();
            $table->string('status', 20)->default('pending'); // pending | transcribing | done | failed
            $table->timestamps();
        });

        // Quyền quản lý tài khoản giáo viên trong trang admin.
        $now = now();
        foreach (['view' => 'Xem', 'create' => 'Thêm', 'edit' => 'Sửa', 'delete' => 'Xoá'] as $action => $label) {
            if (! DB::table('permissions')->where('module', 'teachers')->where('action', $action)->exists()) {
                DB::table('permissions')->insert([
                    'module' => 'teachers', 'action' => $action, 'label' => "Giáo viên - {$label}",
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('module', 'teachers')->delete();
        Schema::dropIfExists('audio_tracks');
        Schema::dropIfExists('unit_profiles');
        Schema::dropIfExists('textbook_chunks');
        Schema::dropIfExists('textbook_units');
        Schema::dropIfExists('textbook_pages');
        Schema::dropIfExists('textbooks');
        Schema::dropIfExists('teachers');
    }
};
