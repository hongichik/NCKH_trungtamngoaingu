<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Server chores that normally need SSH (`php artisan ...`), exposed as
 * super-admin-only links for shared hosting without a terminal.
 */
class MaintenanceController extends Controller
{
    public function storageLink(): RedirectResponse
    {
        $link = public_path('storage');

        if (is_link($link) && is_dir($link)) {
            return redirect()->route('admin.dashboard')->with('status', 'Storage link đã tồn tại (public/storage), không cần tạo lại.');
        }

        if (file_exists($link) && ! is_link($link)) {
            return redirect()->route('admin.dashboard')->withErrors(
                'Đã có một thư mục/file thật tên "public/storage". Hãy xoá hoặc đổi tên nó (qua File Manager) rồi mở lại link này.'
            );
        }

        if (! function_exists('symlink')) {
            return redirect()->route('admin.dashboard')->withErrors(
                'Hosting đã tắt hàm symlink() của PHP nên không tạo được storage link. Hãy nhờ nhà cung cấp hosting bật hàm này.'
            );
        }

        try {
            Artisan::call('storage:link');
        } catch (Throwable $e) {
            return redirect()->route('admin.dashboard')->withErrors('Không tạo được storage link: '.$e->getMessage());
        }

        return is_link($link)
            ? redirect()->route('admin.dashboard')->with('status', 'Đã tạo storage link thành công. Ảnh tải lên sẽ hiển thị bình thường.')
            : redirect()->route('admin.dashboard')->withErrors('Không tạo được storage link: '.trim(Artisan::output()));
    }
}
