<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nhật ký: ai tạo tài khoản học viên, ai cấp lại mật khẩu, ai thêm / bớt học viên khỏi lớp, lúc nào. */
class AccountLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'create' => 'Tạo tài khoản', 'reset_password' => 'Cấp lại mật khẩu', 'add_to_class' => 'Thêm vào lớp',
        'remove_from_class' => 'Xoá khỏi lớp', 'change_password' => 'Tự đổi mật khẩu', 'lock' => 'Khoá đăng nhập', 'unlock' => 'Mở khoá đăng nhập',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** Ghi một dòng; người làm lấy từ guard đang đăng nhập (admin / giáo viên / học viên). */
    public static function record(string $action, ?int $studentId, ?int $classroomId = null, array $meta = []): self
    {
        [$type, $actor] = match (true) {
            auth('teacher')->check() && request()->routeIs('teacher.*') => ['teacher', auth('teacher')->user()],
            auth('web')->check() && request()->routeIs('admin.*') => ['user', auth('web')->user()],
            auth('student')->check() && request()->routeIs('portal.*') => ['student', auth('student')->user()],
            auth('web')->check() => ['user', auth('web')->user()],
            auth('teacher')->check() => ['teacher', auth('teacher')->user()],
            default => ['system', null],
        };

        return self::create([
            'actor_type' => $type, 'actor_id' => $actor?->id, 'actor_name' => $actor?->name ?? $actor?->full_name ?? 'Hệ thống',
            'action' => $action, 'student_id' => $studentId, 'classroom_id' => $classroomId, 'meta' => $meta ?: null,
        ]);
    }
}
