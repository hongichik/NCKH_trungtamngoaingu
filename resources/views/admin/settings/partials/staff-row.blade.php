<div class="repeat-row">
    <div class="row g-2 align-items-center">
        <div class="col-md-4"><input type="text" name="contact_staff[{{ $i }}][name]" class="form-control form-control-sm" value="{{ $row['name'] ?? '' }}" placeholder="Tên (VD: Ms. Bình)"></div>
        <div class="col-md-3"><input type="text" name="contact_staff[{{ $i }}][phone]" class="form-control form-control-sm" value="{{ $row['phone'] ?? '' }}" placeholder="Số điện thoại"></div>
        <div class="col-md-3"><input type="email" name="contact_staff[{{ $i }}][email]" class="form-control form-control-sm" value="{{ $row['email'] ?? '' }}" placeholder="Email"></div>
        <div class="col-md-2 text-end">@include('admin.settings.partials.row-actions')</div>
    </div>
</div>
