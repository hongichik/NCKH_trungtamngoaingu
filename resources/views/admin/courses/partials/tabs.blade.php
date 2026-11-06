{{-- Tab của trang khoá học. Biến: $course, $active --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link {{ $active === 'info' ? 'active' : '' }}" href="{{ route('admin.courses.edit', $course) }}"><i class="ti ti-info-circle me-1"></i>Thông tin</a></li>
    @if (Route::has('admin.courses.lessons.index'))
        <li class="nav-item"><a class="nav-link {{ $active === 'lessons' ? 'active' : '' }}" href="{{ route('admin.courses.lessons.index', $course) }}"><i class="ti ti-list-numbers me-1"></i>Bài học & bài tập</a></li>
    @endif
    <li class="nav-item"><a class="nav-link" href="{{ route('admin.classrooms.index', ['course_id' => $course->id]) }}"><i class="ti ti-users-group me-1"></i>Lớp của khoá</a></li>
</ul>
