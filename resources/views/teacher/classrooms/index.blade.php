@extends('layouts.teacher')

@section('title', 'Lớp của tôi')

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Lớp</th><th>Khoá học</th><th>Thời gian</th><th>Quyền của tôi</th><th class="text-end">Học viên</th><th class="text-end">Bài học</th></tr></thead>
                <tbody>
                    @forelse ($classrooms as $c)
                        @php $st = $c->status(); @endphp
                        <tr>
                            <td><a href="{{ route('teacher.classrooms.show', $c) }}" class="fw-semibold">{{ $c->name }}</a>
                                <div><span class="badge {{ ['upcoming' => 'bg-azure-lt', 'active' => 'bg-green-lt', 'ended' => 'bg-secondary-lt'][$st] }}">{{ \App\Models\Classroom::STATUSES[$st] }}</span></div></td>
                            <td class="text-muted small">{{ $c->course?->title ?? '—' }}</td>
                            <td class="small text-muted">{{ $c->starts_at?->format('d/m/Y') ?? '—' }} → {{ $c->ends_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="small">
                                <span class="badge bg-secondary-lt me-1" title="Luôn được ở mọi lớp mình dạy">Giao bài · chấm bài · bảng điểm</span>
                                @foreach (\App\Models\Classroom::PERMS as $k => $label)
                                    @if ($c->pivot->{$k})<span class="badge bg-blue-lt me-1">{{ $label }}</span>@endif
                                @endforeach
                            </td>
                            <td class="text-end">{{ $c->students_count }}</td>
                            <td class="text-end">{{ $c->course?->lessons()->count() ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">Bạn chưa được gán vào lớp nào. Quản trị viên tạo lớp và gán giáo viên.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
