<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
        if ($this->is_admin) {
            return true;
        }

        return $this->permissions->contains(
            fn (Permission $permission) => $permission->module === $module && $permission->action === $action
        );
    }

    public function hasAnyPermissionFor(string $module): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return $this->permissions->contains(fn (Permission $permission) => $permission->module === $module);
    }
}
