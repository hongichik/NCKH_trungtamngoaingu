<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::forceCreate(['name' => 'Cô Hương', 'email' => 'a@a.a', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $other = User::forceCreate(['name' => 'Thầy Nam', 'email' => 'b@b.b', 'password' => bcrypt('x'), 'is_admin' => 1]);

        $hsk = Category::create(['name' => 'Điểm thi HSK', 'slug' => 'hsk']);
        $cntt = Category::create(['name' => 'Chứng chỉ CNTT', 'slug' => 'cntt']);

        $make = function (array $attrs, array $categories = [], ?string $date = null) {
            $post = Post::create($attrs + ['slug' => \Str::slug($attrs['title']), 'status' => 'published']);
            $post->forceFill(['created_at' => $date ?? now(), 'updated_at' => $date ?? now()])->save();
            $post->categories()->sync($categories);

            return $post;
        };

        $make(['title' => 'Thông báo thi HSK tháng 8', 'views' => 500, 'user_id' => $this->admin->id, 'image' => 'posts/a.jpg', 'is_featured' => true], [$hsk->id], '2024-08-01');
        $make(['title' => 'Kết quả thi HSK tháng 5', 'views' => 50, 'user_id' => $other->id], [$hsk->id], '2025-05-20');
        $make(['title' => 'Lịch thi chứng chỉ CNTT', 'views' => 10, 'user_id' => $this->admin->id, 'image' => 'posts/b.jpg'], [$cntt->id], '2026-03-01');
        $make(['title' => 'Bản nháp chưa phân loại', 'views' => 0, 'status' => 'draft'], [], '2026-06-01');
    }

    protected function titles(array $query = []): array
    {
        return $this->actingAs($this->admin)->get(route('admin.posts.index', $query))
            ->assertOk()->viewData('posts')->pluck('title')->all();
    }

    public function test_default_lists_newest_first_with_status_counts(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/posts')->assertOk();
        $this->assertSame('Bản nháp chưa phân loại', $response->viewData('posts')->first()->title);
        $this->assertEquals(['published' => 3, 'draft' => 1], $response->viewData('statusCounts')->map(fn ($n) => (int) $n)->all());
        $response->assertSee('Thầy Nam')->assertSee('01/08/2024');
    }

    public function test_each_filter(): void
    {
        $this->assertSame(['Kết quả thi HSK tháng 5', 'Thông báo thi HSK tháng 8'], $this->titles(['q' => 'hsk']));
        $this->assertCount(2, $this->titles(['category' => Category::where('slug', 'hsk')->value('id')]));
        $this->assertSame(['Bản nháp chưa phân loại'], $this->titles(['category' => 'none']));
        $this->assertSame(['Bản nháp chưa phân loại'], $this->titles(['status' => 'draft']));
        $this->assertSame(['Thông báo thi HSK tháng 8'], $this->titles(['featured' => '1']));
        $this->assertCount(2, $this->titles(['image' => 'without']));
        $this->assertCount(2, $this->titles(['author' => $this->admin->id]));
        $this->assertSame(['Bản nháp chưa phân loại', 'Lịch thi chứng chỉ CNTT'], $this->titles(['from' => '2026-01-01']));
        $this->assertSame(['Kết quả thi HSK tháng 5', 'Thông báo thi HSK tháng 8'], $this->titles(['from' => '2024-01-01', 'to' => '2025-12-31']));
        $this->assertSame('Thông báo thi HSK tháng 8', $this->titles(['sort' => 'views_desc'])[0]);
        $this->assertSame('Thông báo thi HSK tháng 8', $this->titles(['sort' => 'oldest'])[0]);
        // Combined: HSK + published + views sorted ascending.
        $this->assertSame(['Kết quả thi HSK tháng 5', 'Thông báo thi HSK tháng 8'], $this->titles(['q' => 'HSK', 'status' => 'published', 'sort' => 'views_asc']));
    }

    public function test_status_counts_follow_other_filters_and_search_is_highlighted(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/posts?q=CNTT')->assertOk();
        $this->assertSame(['published' => 1], $response->viewData('statusCounts')->map(fn ($n) => (int) $n)->all());
        $response->assertSee('<mark>CNTT</mark>', false)->assertSee('Đang áp dụng 1 bộ lọc');
    }

    public function test_invalid_filter_values_are_ignored_not_redirected(): void
    {
        $this->assertCount(4, $this->titles(['from' => 'not-a-date', 'sort' => 'hack', 'per_page' => 9999, 'status' => 'x']));
    }

    public function test_saving_returns_to_the_filtered_list(): void
    {
        $this->actingAs($this->admin)->get('/admin/posts?category=none&page=1')->assertOk();
        $post = Post::where('status', 'draft')->first();

        $this->post('/admin/posts/'.$post->id, ['title' => $post->title, 'status' => 'published'])
            ->assertRedirect(url('/admin/posts?category=none&page=1'));
    }
}
