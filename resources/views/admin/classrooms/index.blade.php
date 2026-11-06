@extends('layouts.admin')

@section('title', 'Lớp học')

@section('content')
    <form class="d-flex flex-wrap gap-2 mb-3" method="GET">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control w-auto" placeholder="Tìm lớp">
        <select name="course_id" class="form-select w-auto" onchange="this.form.submit()"><option value="">Mọi khoá học</option>
            @foreach ($courses as $c)<option value="{{ $c->id }}" @selected((int) request('course_id') === $c->id)>{{ $c->title }}</option>@endforeach</select>
        <select name="status" class="form-select w-auto" onchange="this.form.submit()"><option value="">Mọi trạng thái</option>
            @foreach (\App\Models\Classroom::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
        @if (auth()->user()->hasPermission('learning', 'create'))
            <a href="{{ route('admin.classrooms.create', ['course_id' => request('course_id')]) }}" class="btn btn-primary ms-auto"><i class="ti ti-plus me-1"></i>Tạo lớp</a>
        @endif
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Lớp</th><th>Khoá học</th><th>Thời gian</th><th>Giáo viên</th><th class="text-end">Học viên</th></tr></thead>
                <tbody>
                    @forelse ($classrooms as $c)
                        @php $st = $c->status(); @endphp
                        <tr>
                            <td><a href="{{ route('admin.classrooms.show', $c) }}" class="fw-semibold">{{ $c->name }}</a>
                                <div><span class="badge {{ ['upcoming' => 'bg-azure-lt', 'active' => 'bg-green-lt', 'ended' => 'bg-secondary-lt'][$st] }}">{{ \App\Models\Classroom::STATUSES[$st] }}</span>
                                    @if ($c->allow_after_end)<span class="badge bg-orange-lt">Cho vào sau kết thúc</span>@endif</div></td>
                            <td class="small">{{ $c->course?->title ?? '—' }}</td>
                            <td class="small text-muted">{{ $c->starts_at?->format('H:i d/m/Y') ?? '—' }}<br>→ {{ $c->ends_at?->format('H:i d/m/Y') ?? '—' }}</td>
                            <td class="small">{{ $c->teachers->pluck('name')->implode(', ') ?: '—' }}</td>
                            <td class="text-end">{{ $c->students_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">Chưa có lớp nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $classrooms->links() }}</div>
    </div>
@endsection
