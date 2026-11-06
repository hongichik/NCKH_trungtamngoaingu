<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Tài khoản id = 1 là chủ hệ thống: toàn quyền, vào được trang Quản trị hệ thống,
     * chỉnh sửa được mọi tài khoản. Không thể bị xoá hay hạ quyền.
     */
    public const OWNER_ID = 1;

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isOwner()) {
                $user->is_admin = true;
            }
        });

        // Lúc tạo mới, id chưa có trong "saving" nên kiểm tra lại sau khi tạo.
        static::created(function (User $user) {
            if ($user->isOwner() && ! $user->is_admin) {
                $user->is_admin = true;
                $user->saveQuietly();
            }
        });

        static::deleting(function (User $user) {
            if ($user->isOwner()) {
                throw new \LogicException('Không thể xoá tài khoản chủ hệ thống.');
            }
        });
    }

    public function isOwner(): bool
    {
        return (int) $this->getKey() === self::OWNER_ID;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * The super admin (is_admin) implicitly has every permission and
     * bypasses the granular permission_user table entirely.
     */
    public function hasPermission(string $module, string $action): bool
    {
        if ($this->is_admin || $this->isOwner()) {
            return true;
        }

        return $this->permissions->contains(
            fn (Permission $permission) => $permission->module === $module && $permission->action === $action
        );
    }

    public function hasAnyPermissionFor(string $module): bool
    {
        if ($this->is_admin || $this->isOwner()) {
            return true;
        }

        return $this->permissions->contains(fn (Permission $permission) => $permission->module === $module);
    }

    // ---------------------------------------------------------------- khoá học / giáo trình (V2)
    // Quản trị viên có quyền "Khoá học & lớp học · Sửa" làm được mọi việc mà giáo viên được cấp quyền sửa khoá làm được.

    public function canEditCourse(Course $course): bool
    {
        return $this->hasPermission('learning', 'edit');
    }

    public function canEditTextbook(Textbook $textbook): bool
    {
        return $this->hasPermission('learning', 'edit');
    }

    /** @return list<int> */
    public function textbookIds(): array
    {
        return $this->hasPermission('learning', 'edit') ? Textbook::pluck('id')->all() : [];
    }
}
