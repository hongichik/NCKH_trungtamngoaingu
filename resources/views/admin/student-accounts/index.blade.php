@extends('layouts.admin')

@section('title', 'Tài khoản học viên')

@section('content')
    @php $u = auth()->user(); @endphp
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="h2 mb-0">{{ $counts['accounts'] }}</div><div class="text-muted">tài khoản học trực tuyến</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="h2 mb-0 {{ $counts['locked'] ? 'text-danger' : '' }}">{{ $counts['locked'] }}</div><div class="text-muted">đang bị khoá</div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <form class="card mb-3" method="GET">
                <div class="card-body row g-2 align-items-end">
                    <div class="col-md-5"><label class="form-label">Tìm</label><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Họ tên, email, số điện thoại, CCCD"></div>
                    <div class="col-md-4"><label class="form-label">Nhóm</label>
                        <select name="scope" class="form-select" onchange="this.form.submit()">@foreach ($scopes as $k => $l)<option value="{{ $k }}" @selected($scope === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Lớp</label>
                        <select name="classroom_id" class="form-select" onchange="this.form.submit()"><option value="">Mọi lớp</option>@foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected((int) request('classroom_id') === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                </div>
            </form>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>Học viên</th><th>Liên hệ</th><th>Lớp</th><th>Đăng nhập gần nhất</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($students as $s)
                                <tr>
                                    <td><a href="{{ route('admin.student-accounts.show', $s) }}" class="fw-semibold">{{ $s->full_name ?: '(chưa có tên)' }}</a>
                                        <div>
                                            @if (! $s->password)<span class="badge bg-secondary-lt">hồ sơ thí sinh</span>@endif
                                            @if ($s->must_change_password)<span class="badge bg-yellow-lt">chưa đổi mật khẩu</span>@endif
                                            @if ($s->login_locked)<span class="badge bg-red-lt">bị khoá</span>@endif
                                        </div></td>
                                    <td class="small">{{ $s->email ?: '—' }}<div class="text-muted">{{ $s->phone }}</div></td>
                                    <td class="small" style="min-width: 150px">@forelse ($s->classrooms as $c)<span class="badge bg-blue-lt text-wrap text-start d-inline-block mb-1 me-1" style="max-width: 180px">{{ $c->name }}</span>@empty — @endforelse</td>
                                    <td class="small text-muted">{{ $s->last_login_at?->format('H:i d/m/Y') ?? 'Chưa' }}</td>
                                    <td><a href="{{ route('admin.student-accounts.show', $s) }}" class="btn btn-sm btn-outline-primary">Quản lý</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-5">Không có học viên phù hợp.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $students->links() }}</div>
            </div>
        </div>

        <div class="col-xl-4">
            @if ($u->hasPermission('learning', 'create'))
                <form method="POST" action="{{ route('admin.student-accounts.store') }}" class="card mb-3">@csrf
                    <div class="card-header"><h3 class="card-title">Tạo một tài khoản</h3></div>
                    <div class="card-body row g-2">
                        <div class="col-12"><input name="name" class="form-control" placeholder="Họ tên" value="{{ old('name') }}" required></div>
                        <div class="col-12"><input type="email" name="email" class="form-control" placeholder="Email (tên đăng nhập)" value="{{ old('email') }}" required></div>
                        <div class="col-6"><input name="phone" class="form-control" placeholder="Số điện thoại" value="{{ old('phone') }}"></div>
                        <div class="col-6"><input type="date" name="dob" class="form-control" value="{{ old('dob') }}"></div>
                        <div class="col-12"><select name="classroom_id" class="form-select"><option value="">Thêm vào lớp (tuỳ chọn)</option>@foreach ($classrooms as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    </div>
                    <div class="card-footer d-flex align-items-center"><span class="small text-muted">Mật khẩu tự sinh, hiện sau khi tạo.</span><button class="btn btn-primary ms-auto">Tạo</button></div>
                </form>

                <form method="POST" action="{{ route('admin.student-accounts.import') }}" enctype="multipart/form-data" class="card">@csrf
                    <div class="card-header"><h3 class="card-title">Tạo tài khoản hàng loạt</h3></div>
                    <div class="card-body">
                        <textarea name="rows" class="form-control font-monospace small mb-2" rows="5" placeholder="Dán từ Excel: Họ tên · Email · Số điện thoại · Ngày sinh"></textarea>
                        <input type="file" name="file" class="form-control mb-2" accept=".csv,.xlsx,.txt">
                        <select name="classroom_id" class="form-select"><option value="">Không thêm vào lớp</option>@foreach ($classrooms as $c)<option value="{{ $c->id }}">Thêm vào lớp: {{ $c->name }}</option>@endforeach</select>
                        <div class="form-hint mt-2">Có bước xem trước (tạo mới / đã có tài khoản / lỗi). <a href="{{ route('admin.students.import-template') }}">Tải file mẫu</a>.</div>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary">Xem trước</button></div>
                </form>
            @endif
        </div>
    </div>
@endsection
