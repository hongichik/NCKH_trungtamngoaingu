<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hàng đợi việc nền AI. Trên hosting hiện tại cron gọi THẲNG `php artisan ai:work --slot=N` mỗi phút
// (hosting tắt proc_open nên schedule:run không chạy được). Các dòng dưới dành cho máy có proc_open (máy local: schedule:work):
// mỗi tiến trình (slot) chạy nền liên tục, hết việc thì chờ thêm ~1 phút rồi mới dừng; tiến trình còn sống thì lần gọi sau tự bỏ qua.
foreach (range(1, max(1, (int) config('ai.jobs.workers', 3))) as $slot) {
    Schedule::command("ai:work --slot={$slot}")->everyMinute()->runInBackground();
}
