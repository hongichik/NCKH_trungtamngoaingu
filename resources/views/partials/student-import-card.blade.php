{{-- Thẻ nhập danh sách học viên. Biến: $action (URL xem trước), $template (URL file mẫu) --}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="card">@csrf
    <div class="card-header"><h3 class="card-title">Tạo tài khoản hàng loạt & thêm vào lớp</h3></div>
    <div class="card-body">
        <label class="form-label">Dán từ Excel (Họ tên · Email · Số điện thoại · Ngày sinh)</label>
        <textarea name="rows" class="form-control font-monospace small" rows="6" placeholder="Nguyễn Văn An	an@example.com	0912345678	15/08/2008"></textarea>
        <div class="text-center text-muted small my-2">hoặc</div>
        <input type="file" name="file" class="form-control" accept=".csv,.xlsx,.txt">
        <div class="form-hint mt-2">
            Chấp nhận .xlsx / .csv, tối đa 1.000 dòng. <a href="{{ $template }}">Tải file mẫu</a>.
            Bước tiếp theo sẽ hiện xem trước: tài khoản nào tạo mới, tài khoản nào <b>đã có</b> (trùng email hoặc số điện thoại), dòng nào lỗi.
        </div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">Xem trước</button></div>
</form>
