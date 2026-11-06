@extends($layout)

@section('title', 'Xem trước danh sách'.($classroom ? ' — '.$classroom->name : ''))

@section('content')
    @php $labels = \App\Support\Students\StudentImport::STATUSES; @endphp
    <div class="row row-cards mb-3">
        @foreach ($labels as $k => [$label, $dot])
            <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body"><div class="h2 mb-0">{{ $counts[$k] }}</div><div class="text-muted">{{ $dot }} {{ $label }}</div></div></div></div>
        @endforeach
    </div>

    @if (! $preview)
        <div class="alert alert-warning">Không đọc được dòng nào. Kiểm tra lại dữ liệu (cột: Họ tên, Email, Số điện thoại, Ngày sinh). <a href="{{ $backUrl }}">Quay lại</a></div>
    @else
        <div class="card mb-3">
            <div class="table-responsive" style="max-height: 60vh">
                <table class="table table-sm table-vcenter card-table">
                    <thead><tr><th>Dòng</th><th>Trạng thái</th><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Ngày sinh</th><th>Ghi chú</th></tr></thead>
                    <tbody>
                        @foreach ($preview as $r)
                            <tr class="{{ $r['status'] === 'error' ? 'table-danger' : '' }}">
                                <td class="text-muted">{{ $r['line'] }}</td>
                                <td class="text-nowrap">{{ $labels[$r['status']][1] }} {{ $labels[$r['status']][0] }}</td>
                                <td>{{ $r['name'] }}</td>
                                <td>{{ $r['email'] }}</td>
                                <td>{{ $r['phone'] }}</td>
                                <td>{{ $r['dob'] }}</td>
                                <td class="small">{{ $r['reason'] }}@if ($r['status'] === 'existing' && $r['student_name']) Tài khoản: {{ $r['student_name'] }} @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="{{ $commitUrl }}" class="card">@csrf
            <input type="hidden" name="rows" value="{{ $rowsJson }}">
            <div class="card-body">
                <div class="mb-2 fw-semibold">Mật khẩu ban đầu cho {{ $counts['new'] }} tài khoản mới</div>
                <label class="form-check"><input class="form-check-input" type="radio" name="password_mode" value="random" checked><span class="form-check-label">Tự sinh ngẫu nhiên (8 ký tự)</span></label>
                <label class="form-check"><input class="form-check-input" type="radio" name="password_mode" value="phone"><span class="form-check-label">Dùng số điện thoại (dòng không có số điện thoại sẽ tự sinh)</span></label>
                <div class="form-hint">Học viên phải đổi mật khẩu ở lần đăng nhập đầu. Sau khi lưu có thể tải file danh sách tài khoản + mật khẩu để phát cho học viên.</div>
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ $backUrl }}" class="btn btn-ghost-secondary">Huỷ</a>
                <button class="btn btn-primary ms-auto" @disabled($counts['new'] + ($classroom ? $counts['existing'] : 0) === 0)>
                    @if ($classroom) Lưu: tạo {{ $counts['new'] }} tài khoản, thêm {{ $counts['new'] + $counts['existing'] }} học viên vào lớp @else Lưu: tạo {{ $counts['new'] }} tài khoản @endif
                </button>
            </div>
        </form>
    @endif
@endsection
