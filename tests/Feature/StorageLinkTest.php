<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorageLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_run_storage_link(): void
    {
        $this->get('/admin/storage-link')->assertRedirect('/admin/login');

        // id 1 là chủ hệ thống (luôn toàn quyền) — tạo trước để các tài khoản dưới là tài khoản thường.
        User::forceCreate(['name' => 'o', 'email' => 'o@o.o', 'password' => bcrypt('x'), 'is_admin' => 1]);

        $staff = User::forceCreate(['name' => 's', 'email' => 's@s.s', 'password' => bcrypt('x'), 'is_admin' => 0]);
        $this->actingAs($staff)->get('/admin/storage-link')->assertForbidden();

        $admin = User::forceCreate(['name' => 'a', 'email' => 'a@a.a', 'password' => bcrypt('x'), 'is_admin' => 1]);
        $this->actingAs($admin)->get('/admin/storage-link')->assertRedirect(route('admin.dashboard'))->assertSessionHas('status');
    }
}
