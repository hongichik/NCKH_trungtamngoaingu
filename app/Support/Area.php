<?php

namespace App\Support;

/**
 * Các trang giáo trình / Unit / bài tập sinh tự động dùng chung cho trang quản trị và cổng giáo viên.
 * Area chọn layout và tên route theo cổng của request hiện tại.
 */
class Area
{
    public static function isAdmin(): bool
    {
        return request()->routeIs('admin.*');
    }

    public static function prefix(): string
    {
        return self::isAdmin() ? 'admin.' : 'teacher.';
    }

    public static function route(string $name, mixed $params = [], bool $absolute = true): string
    {
        return route(self::prefix().$name, $params, $absolute);
    }

    public static function layout(): string
    {
        return self::isAdmin() ? 'layouts.admin' : 'layouts.teacher';
    }

    /** Làn hàng đợi AI của người đang thao tác. */
    public static function lane(): string
    {
        return self::isAdmin() ? 'user:'.auth()->id() : 'teacher:'.auth()->id();
    }

    /** teacher_id ghi vào bản ghi / việc nền (null khi quản trị viên làm). */
    public static function teacherId(): ?int
    {
        return self::isAdmin() ? null : auth()->id();
    }
}
