<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Certificate;
use App\Models\Contact;
use App\Models\ContactReply;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportLegacyData extends Command
{
    protected $signature = 'app:import-legacy-data';

    protected $description = 'Import real content from the legacy daihocha_vbcc_2 database into the new schema';

    protected string $legacyStoragePath;

    protected array $slugsByReference = [];

    public function handle(): int
    {
        $this->legacyStoragePath = rtrim(env('LEGACY_STORAGE_PATH'), '/');

        $this->loadLegacySlugs();

        $this->importUsers();

        $categoryMap = $this->importCategories();
        $this->importPosts($categoryMap);
        $studentMap = $this->importStudents();
        $this->importCertificates($studentMap);
        $this->importGalleries();
        $this->importPages();
        $this->importContacts();

        $this->info('Import hoàn tất.');

        return self::SUCCESS;
    }

    protected function loadLegacySlugs(): void
    {
        DB::connection('legacy')->table('slugs')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                $this->slugsByReference["{$row->reference_type}:{$row->reference_id}"] = $row->key;
            }
        });
    }

    protected function slugFor(string $referenceType, int $id, string $fallback): string
    {
        return $this->slugsByReference["{$referenceType}:{$id}"] ?? Str::slug($fallback).'-'.$id;
    }

    protected function copyFile(?string $filename, string $targetDir): ?string
    {
        if (! $filename) {
            return null;
        }

        $source = "{$this->legacyStoragePath}/{$filename}";

        if (! is_file($source)) {
            return null;
        }

        $target = "{$targetDir}/".basename($filename);
        Storage::disk('public')->put($target, file_get_contents($source));

        return $target;
    }

    protected function importUsers(): void
    {
        $rows = DB::connection('legacy')->table('users')->where('super_user', 1)->get();

        foreach ($rows as $row) {
            User::updateOrCreate(
                ['id' => $row->id],
                [
                    'name' => trim("{$row->first_name} {$row->last_name}") ?: $row->username,
                    'email' => $row->email,
                    'password' => $row->password,
                    'is_admin' => true,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]
            );
        }

        $this->info("Đã import {$rows->count()} tài khoản quản trị.");
    }

    protected function importCategories(): array
    {
        $rows = DB::connection('legacy')->table('categories')->get();
        $map = [];

        foreach ($rows as $row) {
            $category = Category::create([
                'name' => $row->name,
                'slug' => $this->slugFor('Platform\\Blog\\Models\\Category', $row->id, $row->name),
                'description' => $row->description,
                'is_featured' => (bool) $row->is_featured,
                'order' => $row->order,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $map[$row->id] = $category->id;
        }

        $this->info('Đã import '.count($map).' danh mục.');

        return $map;
    }

    protected function importPosts(array $categoryMap): void
    {
        $rows = DB::connection('legacy')->table('posts')->get();
        $postCategoryRows = DB::connection('legacy')->table('post_categories')->get()->groupBy('post_id');
        $galleryMeta = DB::connection('legacy')->table('gallery_meta')
            ->where('reference_type', 'Platform\\Blog\\Models\\Post')
            ->whereNotNull('images')
            ->get()
            ->groupBy('reference_id');

        $bar = $this->output->createProgressBar($rows->count());

        foreach ($rows as $row) {
            $image = $this->copyFile($row->image, 'posts');

            $post = Post::create([
                'id' => $row->id,
                'user_id' => User::find($row->author_id) ? $row->author_id : null,
                'title' => $row->name,
                'slug' => $this->slugFor('Platform\\Blog\\Models\\Post', $row->id, $row->name),
                'description' => $row->description,
                'content' => $row->content,
                'image' => $image,
                'is_featured' => (bool) $row->is_featured,
                'views' => $row->views,
                'status' => $row->status === 'published' ? 'published' : 'draft',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $categoryIds = ($postCategoryRows[$row->id] ?? collect())
                ->map(fn ($pc) => $categoryMap[$pc->category_id] ?? null)
                ->filter()
                ->all();
            $post->categories()->sync($categoryIds);

            foreach ($galleryMeta[$row->id] ?? [] as $meta) {
                foreach (json_decode($meta->images, true) ?: [] as $img) {
                    $imagePath = $this->copyFile($img['img'] ?? null, 'posts');
                    if ($imagePath) {
                        $post->images()->create([
                            'image' => $imagePath,
                            'description' => $img['description'] ?? null,
                        ]);
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Đã import {$rows->count()} bài viết.");
    }

    protected function importStudents(): array
    {
        $rows = DB::connection('legacy')->table('students')->get();
        $map = [];

        $bar = $this->output->createProgressBar($rows->count());

        foreach ($rows as $row) {
            $student = Student::create([
                'numbering' => $row->numbering,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'candidate_number' => $row->candidate_number,
                'description' => $row->description,
                'gender' => $row->gender,
                'email' => $row->email,
                'phone' => $row->phone,
                'dob' => $row->dob,
                'birthplace' => $row->birthplace,
                'ethnic' => $row->ethnic,
                'identity_number' => $row->identity_number,
                'identity_date' => $row->identity_date,
                'identity_place' => $row->identity_place,
                'status' => $row->status === 'published' ? 'published' : 'draft',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $map[$row->id] = $student->id;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Đã import '.count($map).' học viên.');

        return $map;
    }

    protected function importCertificates(array $studentMap): void
    {
        $rows = DB::connection('legacy')->table('certificates')->get();
        $bar = $this->output->createProgressBar($rows->count());

        foreach ($rows as $row) {
            if (! isset($studentMap[$row->student_id])) {
                $bar->advance();
                continue;
            }

            Certificate::create([
                'student_id' => $studentMap[$row->student_id],
                'numbering' => $row->numbering,
                'exam_date' => $row->exam_date,
                'exam_place' => $row->exam_place,
                'exam_room' => $row->exam_room,
                'practice_score' => $row->practice_score,
                'theoretical_score' => $row->theoretical_score,
                'scores' => $row->scores,
                'classification' => $row->classification,
                'certificate_type' => $row->certificate_type,
                'serial_number' => $row->serial_number,
                'certificate_number' => $row->certificate_number,
                'issue_date' => $row->issue_date,
                'issue_place' => $row->issue_place,
                'status' => $row->status === 'published' ? 'published' : 'draft',
                'description' => $row->description,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Đã import {$rows->count()} chứng chỉ.");
    }

    protected function importGalleries(): void
    {
        $rows = DB::connection('legacy')->table('galleries')->get();

        foreach ($rows as $row) {
            Gallery::create([
                'user_id' => User::find($row->user_id) ? $row->user_id : null,
                'name' => $row->name,
                'slug' => Str::slug($row->name).'-'.$row->id,
                'description' => $row->description,
                'image' => $this->copyFile($row->image, 'galleries'),
                'is_featured' => (bool) $row->is_featured,
                'order' => $row->order,
                'status' => $row->status === 'published' ? 'published' : 'draft',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        $this->info("Đã import {$rows->count()} thư viện ảnh.");
    }

    protected function importPages(): void
    {
        $rows = DB::connection('legacy')->table('pages')->get();

        foreach ($rows as $row) {
            Page::create([
                'user_id' => User::find($row->user_id) ? $row->user_id : null,
                'title' => $row->name,
                'slug' => $this->slugFor('Platform\\Page\\Models\\Page', $row->id, $row->name),
                'description' => $row->description,
                'content' => $row->content,
                'image' => $this->copyFile($row->image, 'pages'),
                'is_featured' => (bool) $row->is_featured,
                'status' => $row->status === 'published' ? 'published' : 'draft',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        $this->info("Đã import {$rows->count()} trang tĩnh.");
    }

    protected function importContacts(): void
    {
        $rows = DB::connection('legacy')->table('contacts')->get();
        $replies = DB::connection('legacy')->table('contact_replies')->get()->groupBy('contact_id');

        foreach ($rows as $row) {
            $contact = Contact::create([
                'name' => $row->name,
                'email' => $row->email,
                'phone' => $row->phone,
                'address' => $row->address,
                'subject' => $row->subject,
                'content' => $row->content,
                'status' => $row->status,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            foreach ($replies[$row->id] ?? [] as $reply) {
                ContactReply::create([
                    'contact_id' => $contact->id,
                    'message' => $reply->message,
                    'created_at' => $reply->created_at,
                    'updated_at' => $reply->updated_at,
                ]);
            }
        }

        $this->info("Đã import {$rows->count()} liên hệ.");
    }
}
