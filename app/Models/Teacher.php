<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Giáo viên — đăng nhập ở cổng /giao-vien (guard "teacher"), tách biệt với admin.
 */
class Teacher extends Authenticatable
{
    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Lớp được gán (kèm quyền riêng ở pivot). */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class)->withPivot(array_keys(Classroom::PERMS))->withTimestamps()->latest('classrooms.id');
    }

    /** Khoá học được cấp quyền sửa. */
    public function editableCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_editors')->withTimestamps();
    }

    public function teaches(Classroom $classroom): bool
    {
        return $this->classrooms()->whereKey($classroom->id)->exists();
    }

    /** Quyền riêng trong lớp: can_customize | can_add_students | can_reset_password. */
    public function hasClassPermission(Classroom $classroom, string $perm): bool
    {
        return (bool) $this->classrooms()->whereKey($classroom->id)->first()?->pivot?->{$perm};
    }

    public function canEditCourse(Course $course): bool
    {
        return $this->editableCourses()->whereKey($course->id)->exists();
    }

    /** Giáo trình: của mình tải lên, hoặc là giáo trình nguồn của khoá mình được sửa (quyết định 6). */
    public function canEditTextbook(Textbook $textbook): bool
    {
        return $textbook->teacher_id === $this->id
            || $this->editableCourses()->where('courses.textbook_id', $textbook->id)->exists();
    }

    /** ID giáo trình giáo viên được làm việc. */
    public function textbookIds(): array
    {
        return Textbook::where('teacher_id', $this->id)->pluck('id')
            ->merge($this->editableCourses()->whereNotNull('courses.textbook_id')->pluck('courses.textbook_id'))
            ->unique()->values()->all();
    }

    public function textbooks(): HasMany
    {
        return $this->hasMany(Textbook::class);
    }
}
