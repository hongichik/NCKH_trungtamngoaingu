<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Classroom extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['allow_join' => 'boolean', 'show_leaderboard' => 'boolean', 'allow_after_end' => 'boolean',
            'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** Quyền riêng của giáo viên trong lớp (cột ở bảng classroom_teacher). */
    public const PERMS = [
        'can_customize' => 'Tuỳ chỉnh bài tập',
        'can_add_students' => 'Thêm / bớt học viên',
        'can_reset_password' => 'Cấp lại mật khẩu học viên',
    ];

    public const STATUSES = ['upcoming' => 'Sắp mở', 'active' => 'Đang học', 'ended' => 'Đã kết thúc'];

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class)->withPivot(array_keys(self::PERMS))->withTimestamps();
    }

    public function status(): string
    {
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'upcoming';
        }

        return $this->ends_at && $this->ends_at->isPast() ? 'ended' : 'active';
    }

    /** Hạn cuối học viên còn vào học / làm bài (null = không giới hạn). */
    public function accessUntil(): ?Carbon
    {
        if (! $this->ends_at) {
            return null;
        }
        if (! $this->allow_after_end) {
            return $this->ends_at;
        }

        return $this->grace_days ? $this->ends_at->copy()->addDays($this->grace_days) : null;
    }

    /** Học viên có được vào học / làm bài lúc này không (xem điểm cũ thì luôn được). */
    public function accessOpen(): bool
    {
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        $until = $this->accessUntil();

        return ! $until || $until->isFuture();
    }

    /** Lý do bị chặn để hiện cho học viên. */
    public function closedReason(): ?string
    {
        if ($this->accessOpen()) {
            return null;
        }

        return $this->status() === 'upcoming'
            ? 'Lớp bắt đầu lúc '.$this->starts_at->format('H:i d/m/Y').'.'
            : 'Lớp đã kết thúc ngày '.$this->ends_at->format('d/m/Y').'. Bạn vẫn xem được điểm và lịch sử bài làm.';
    }

    public function scopeStatus($q, string $status)
    {
        return match ($status) {
            'upcoming' => $q->where('starts_at', '>', now()),
            'ended' => $q->whereNotNull('ends_at')->where('ends_at', '<', now()),
            'active' => $q->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now())),
            default => $q,
        };
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->latest('id');
    }

    protected static function booted(): void
    {
        static::creating(function (Classroom $c) {
            $c->code ??= self::newCode();
            $c->invite_token ??= Str::random(32);
        });
        // Lớp tạo kèm "giáo viên chính" (dữ liệu cũ, lệnh demo, test) → gán giáo viên đó với đủ quyền.
        static::created(function (Classroom $c) {
            if ($c->teacher_id) {
                $c->teachers()->syncWithoutDetaching([$c->teacher_id => array_fill_keys(array_keys(self::PERMS), true)]);
            }
        });
    }

    /** Link mời học viên vào lớp. */
    public function inviteUrl(): string
    {
        return route('portal.classes.invite', $this->invite_token);
    }

    /** Mã nội bộ của lớp (không còn dùng để học viên tự tham gia). */
    public static function newCode(): string
    {
        do {
            $code = collect(range(1, 6))->map(fn () => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)])->implode('');
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
