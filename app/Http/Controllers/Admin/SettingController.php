<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    /** Bootstrap Icons offered for the homepage feature cards. */
    public const ICONS = [
        'bi-laptop' => 'Máy tính', 'bi-translate' => 'Ngôn ngữ', 'bi-mortarboard' => 'Mũ tốt nghiệp',
        'bi-book' => 'Sách', 'bi-journal-text' => 'Giáo trình', 'bi-pencil-square' => 'Bài tập',
        'bi-award' => 'Giải thưởng', 'bi-patch-check' => 'Chứng nhận', 'bi-trophy' => 'Cúp',
        'bi-person-workspace' => 'Giảng viên', 'bi-people' => 'Học viên', 'bi-chat-dots' => 'Giao tiếp',
        'bi-search-heart' => 'Tra cứu', 'bi-calendar2-check' => 'Lịch', 'bi-clock-history' => 'Thời gian',
        'bi-building' => 'Cơ sở', 'bi-geo-alt' => 'Địa điểm', 'bi-headset' => 'Hỗ trợ',
        'bi-shield-check' => 'Uy tín', 'bi-lightning-charge' => 'Nhanh chóng', 'bi-globe2' => 'Quốc tế',
        'bi-star' => 'Ngôi sao', 'bi-heart' => 'Tận tâm', 'bi-graph-up-arrow' => 'Tiến bộ',
    ];

    /** Image settings: key => [max width, max height]. */
    protected const IMAGES = ['logo' => [800, 300], 'logo_footer' => [600, 300], 'favicon' => [256, 256]];

    public function edit(): View
    {
        return view('admin.settings.edit', [
            's' => fn (string $key, $default = null) => old($key, setting($key, $default)),
            'icons' => self::ICONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:150'],
            'site_intro' => ['nullable', 'string', 'max:500'],
            'parent_org_name' => ['nullable', 'string', 'max:150'],
            'parent_org_url' => ['nullable', 'url', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'color_brand' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'contact_address' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'map_query' => ['nullable', 'string', 'max:500'],
            'social_facebook' => ['nullable', 'url', 'max:255'],
            'social_zalo' => ['nullable', 'string', 'max:255'],
            'social_youtube' => ['nullable', 'url', 'max:255'],
            'contact_staff' => ['nullable', 'array', 'max:10'],
            'contact_staff.*.name' => ['nullable', 'string', 'max:100'],
            'contact_staff.*.phone' => ['nullable', 'string', 'max:30'],
            'contact_staff.*.email' => ['nullable', 'email', 'max:150'],

            'home_fields_title' => ['nullable', 'string', 'max:150'],
            'home_fields_subtitle' => ['nullable', 'string', 'max:255'],
            'home_fields' => ['nullable', 'array', 'max:12'],
            'home_reasons_title' => ['nullable', 'string', 'max:150'],
            'home_reasons_subtitle' => ['nullable', 'string', 'max:255'],
            'home_reasons' => ['nullable', 'array', 'max:12'],
            'home_fields.*.icon' => ['nullable', 'string', 'max:50'],
            'home_fields.*.title' => ['nullable', 'string', 'max:150'],
            'home_fields.*.desc' => ['nullable', 'string', 'max:400'],
            'home_reasons.*.icon' => ['nullable', 'string', 'max:50'],
            'home_reasons.*.title' => ['nullable', 'string', 'max:150'],
            'home_reasons.*.desc' => ['nullable', 'string', 'max:400'],

            'logo' => ['nullable', 'image', 'max:5120'],
            'logo_footer' => ['nullable', 'image', 'max:5120'],
            'favicon' => ['nullable', 'image', 'max:2048'],
        ], [
            'color_brand.regex' => 'Màu chính phải có dạng #RRGGBB.',
            'color_accent.regex' => 'Màu nhấn phải có dạng #RRGGBB.',
            'contact_staff.*.email.email' => 'Email cán bộ liên hệ không hợp lệ.',
        ], [
            'site_name' => 'tên website',
            'parent_org_url' => 'website đơn vị chủ quản',
            'contact_email' => 'email liên hệ',
        ]);

        // Repeatable rows: drop empty ones, keep icons to the allowed list.
        $data['contact_staff'] = collect($data['contact_staff'] ?? [])
            ->filter(fn ($row) => filled($row['name'] ?? null) || filled($row['phone'] ?? null))
            ->map(fn ($row) => ['name' => $row['name'] ?? '', 'phone' => $row['phone'] ?? '', 'email' => $row['email'] ?? ''])
            ->values()->all();
        foreach (['home_fields', 'home_reasons'] as $key) {
            $data[$key] = collect($data[$key] ?? [])
                ->filter(fn ($row) => filled($row['title'] ?? null))
                ->map(fn ($row) => [
                    'icon' => array_key_exists($row['icon'] ?? '', self::ICONS) ? $row['icon'] : 'bi-star',
                    'title' => $row['title'],
                    'desc' => $row['desc'] ?? '',
                ])
                ->values()->all();
        }

        foreach (self::IMAGES as $key => [$w, $h]) {
            unset($data[$key]);
            $current = setting($key);
            if (($request->boolean('remove_'.$key) || $request->hasFile($key)) && $current) {
                Storage::disk('public')->delete($current);
                $data[$key] = null;
            }
            if ($request->hasFile($key)) {
                $data[$key] = ImageOptimizer::store($request->file($key), 'settings', $w, $h, 90);
            }
        }

        Setting::setMany($data);

        return redirect()->route('admin.settings.edit', ['tab' => $request->input('_tab')])->with('status', 'Đã lưu cài đặt website.');
    }
}
