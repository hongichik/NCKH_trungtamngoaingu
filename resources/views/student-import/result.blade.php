@extends($layout)

@section('title', 'Đã nhập học viên'.($classroom ? ' — '.$classroom->name : ''))

@section('content')
    <div class="alert alert-success">
        Đã tạo <b>{{ $result['created'] }}</b> tài khoản mới@if ($classroom), thêm <b>{{ $result['added'] }}</b> học viên vào lớp@endif.
        @if ($result['skipped']) Bỏ qua {{ $result['skipped'] }} {{ $classroom ? 'học viên đã ở trong lớp' : 'tài khoản đã có' }}. @endif
        @if ($result['errors']) {{ $result['errors'] }} dòng lỗi không được lưu. @endif
    </div>
    @if ($result['credentials'])
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Tài khoản mới — phát cho học viên</h3>
                <div class="card-actions"><a href="{{ $downloadUrl }}" class="btn btn-primary btn-sm"><i class="ti ti-download me-1"></i>Tải file CSV</a></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm card-table">
                    <thead><tr><th>Họ tên</th><th>Email (tên đăng nhập)</th><th>Điện thoại</th><th>Mật khẩu</th></tr></thead>
                    <tbody>
                        @foreach ($result['credentials'] as $c)
                            <tr><td>{{ $c['name'] }}</td><td>{{ $c['email'] }}</td><td>{{ $c['phone'] }}</td><td class="font-monospace">{{ $c['password'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">Mật khẩu chỉ hiện một lần. Học viên đăng nhập ở trang Cổng thí sinh và phải đổi mật khẩu ngay lần đầu.</div>
        </div>
    @endif
    <a href="{{ $backUrl }}" class="btn btn-outline-primary">{{ $classroom ? 'Về trang lớp' : 'Về danh sách tài khoản' }}</a>
@endsection
