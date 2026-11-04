<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::forceCreate(['name' => 'a', 'email' => 'a@a.a', 'password' => bcrypt('x'), 'is_admin' => 1]);
    }

    public function test_seeded_banners_and_settings_render_on_homepage(): void
    {
        $this->assertSame(3, Banner::count());

        $this->get('/')->assertOk()
            ->assertSee('Tuyển sinh các lớp Ngoại ngữ')
            ->assertSee('Luyện thi chứng chỉ ứng dụng CNTT')
            ->assertSee('slideshow/hero-image-tuyen-sinh-3-2.jpg')
            ->assertSee('Lĩnh vực đào tạo')
            ->assertSee('Hỗ trợ tận tâm')
            ->assertSee('Ms. Bình');
    }

    public function test_admin_can_create_reorder_schedule_and_delete_banners(): void
    {
        $this->actingAs($this->admin)->get('/admin/banners')->assertOk();
        $this->get('/admin/banners/create')->assertOk();

        // Poster banners need an image.
        $this->post('/admin/banners', ['type' => 'image', 'title' => 'Thiếu ảnh', 'background' => 'brand'])
            ->assertSessionHasErrors('image');

        $this->post('/admin/banners', [
            'type' => 'image', 'title' => 'Khai giảng HSK', 'background' => 'brand', 'is_active' => 1,
            'button_url' => '/lien-he',
            'image' => UploadedFile::fake()->image('poster.jpg', 3000, 1000),
            'image_mobile' => UploadedFile::fake()->image('mobile.png', 800, 1000),
        ])->assertRedirect('/admin/banners')->assertSessionHasNoErrors();

        $banner = Banner::latest('id')->first();
        Storage::disk('public')->assertExists($banner->image);
        Storage::disk('public')->assertExists($banner->image_mobile);
        // Large uploads are shrunk to fit 2400px wide.
        $this->assertSame(2400, getimagesize(Storage::disk('public')->path($banner->image))[0]);

        $this->get('/admin/banners/'.$banner->id.'/edit')->assertOk();

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('alt="Khai giảng HSK"', $html);
        $this->assertStringContainsString('media="(max-width: 767.98px)"', $html);

        // Move it to the top.
        $ids = Banner::orderBy('order')->pluck('id')->reject(fn ($id) => $id === $banner->id)->prepend($banner->id)->values()->all();
        $this->postJson('/admin/banners/reorder', ['ids' => $ids])->assertOk();
        $this->assertSame($banner->id, Banner::visible()->first()->id);

        // Scheduled for the future: hidden from the site.
        $this->post('/admin/banners/'.$banner->id, [
            'type' => 'image', 'title' => 'Khai giảng HSK', 'background' => 'brand', 'is_active' => 1,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('alt="Khai giảng HSK"', $this->get('/')->getContent());

        // Hide / delete.
        $this->post('/admin/banners/'.$banner->id.'/toggle');
        $this->assertFalse($banner->fresh()->is_active);
        $path = $banner->fresh()->image;
        $this->delete('/admin/banners/'.$banner->id)->assertRedirect();
        $this->assertNull(Banner::find($banner->id));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_settings_update_changes_public_site(): void
    {
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk()->assertSee('Ms. Bình');

        $this->post('/admin/settings', [
            'site_name' => 'Trung tâm Thử Nghiệm',
            'site_intro' => 'Giới thiệu mới',
            'parent_org_name' => 'Đại học ABC',
            'parent_org_url' => 'https://abc.edu.vn',
            'color_brand' => '#112233',
            'color_accent' => '#ff0000',
            'contact_address' => '123 Đường Mới',
            'social_facebook' => 'https://facebook.com/trungtam',
            'social_zalo' => '0909 123 456',
            'contact_staff' => [
                ['name' => 'Thầy Nam', 'phone' => '0911 222 333', 'email' => 'nam@abc.edu.vn'],
                ['name' => '', 'phone' => '', 'email' => ''],
            ],
            'home_fields_title' => 'Khoá học',
            'home_fields' => [['icon' => 'bi-book', 'title' => 'Tiếng Hàn', 'desc' => 'Mới mở']],
            'home_reasons' => [],
            'logo' => UploadedFile::fake()->image('logo.png', 1600, 400),
            '_tab' => 'contact',
        ])->assertRedirect('/admin/settings?tab=contact')->assertSessionHasNoErrors();

        $this->assertSame([['name' => 'Thầy Nam', 'phone' => '0911 222 333', 'email' => 'nam@abc.edu.vn']], setting('contact_staff'));
        Storage::disk('public')->assertExists(setting('logo'));

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<title>Trang chủ - Trung tâm Thử Nghiệm</title>', $html);
        $this->assertStringContainsString('--brand: #112233', $html);
        $this->assertStringContainsString('Giới thiệu mới', $html);
        $this->assertStringContainsString('href="https://abc.edu.vn"', $html);
        $this->assertStringContainsString('123 Đường Mới', $html);
        $this->assertStringContainsString('tel:0911222333', $html);
        $this->assertStringContainsString('https://zalo.me/0909123456', $html);
        $this->assertStringContainsString('Tiếng Hàn', $html);
        $this->assertStringNotContainsString('Ms. Bình', $html);
        // No reasons left: the whole section disappears.
        $this->assertStringNotContainsString('Hỗ trợ tận tâm', $html);
    }

    public function test_settings_validation(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', [
            'site_name' => '', 'color_brand' => 'blue', 'color_accent' => '#ff0000',
        ])->assertSessionHasErrors(['site_name', 'color_brand']);
    }

    public function test_editor_upload_returns_url_and_respects_permissions(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/admin/editor/upload-image', ['upload' => UploadedFile::fake()->image('anh.jpg', 3000, 2000)])
            ->assertOk();
        $path = str_replace('/storage/', '', $response->json('url'));
        Storage::disk('public')->assertExists($path);
        $this->assertSame(1600, getimagesize(Storage::disk('public')->path($path))[0]);

        $this->postJson('/admin/editor/upload-image', ['upload' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])
            ->assertStatus(422);

        $staff = User::forceCreate(['name' => 's', 'email' => 's@s.s', 'password' => bcrypt('x'), 'is_admin' => 0]);
        $this->actingAs($staff)->postJson('/admin/editor/upload-image', ['upload' => UploadedFile::fake()->image('a.jpg')])
            ->assertForbidden();
    }

    public function test_post_and_page_forms_use_rich_editor_and_render_content(): void
    {
        $this->actingAs($this->admin)->get('/admin/posts/create')->assertOk()
            ->assertSee('data-rich-editor', false)
            ->assertSee('vendor/ckeditor5/ckeditor5.js', false)
            ->assertDontSee('quill', false);

        $this->post('/admin/posts', [
            'title' => 'Bài có ảnh', 'status' => 'published',
            'content' => '<figure class="image image-style-side"><img src="/storage/editor/a.jpg"></figure><p>Xin chào</p>',
            'image' => UploadedFile::fake()->image('cover.jpg', 2000, 1125),
        ])->assertSessionHasNoErrors();
        $post = Post::latest('id')->first();
        $this->assertSame(1600, getimagesize(Storage::disk('public')->path($post->image))[0]);

        $this->get('/admin/posts/'.$post->id.'/edit')->assertOk();
        $this->get('/'.$post->slug)->assertOk()
            ->assertSee('post-content ck-content', false)
            ->assertSee('image-style-side', false)
            ->assertSee('ckeditor5-content.css', false);

        $this->get('/admin/pages/create')->assertOk()->assertSee('data-rich-editor', false);
        $page = Page::create(['title' => 'Giới thiệu', 'slug' => 'gioi-thieu', 'status' => 'published', 'image' => 'pages/old.jpg']);
        Storage::disk('public')->put('pages/old.jpg', 'x');
        $this->post('/admin/pages/'.$page->id, ['title' => 'Giới thiệu', 'status' => 'published', 'remove_image' => 1])->assertSessionHasNoErrors();
        $this->assertNull($page->fresh()->image);
        Storage::disk('public')->assertMissing('pages/old.jpg');
    }

    public function test_limited_staff_cannot_reach_banners_or_settings(): void
    {
        $staff = User::forceCreate(['name' => 's', 'email' => 's@s.s', 'password' => bcrypt('x'), 'is_admin' => 0]);
        $this->actingAs($staff)->get('/admin/banners')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
        $this->post('/admin/settings', ['site_name' => 'Hack'])->assertForbidden();
        $this->assertNotSame('Hack', Setting::get('site_name'));
    }
}
