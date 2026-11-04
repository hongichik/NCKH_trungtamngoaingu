@php $icon = $row['icon'] ?? 'bi-star'; @endphp
<div class="repeat-row">
    <div class="row g-2">
        <div class="col-md-3">
            <div class="d-flex gap-2 align-items-center">
                <span class="icon-preview"><i class="bi {{ $icon }}"></i></span>
                <select name="{{ $name }}[{{ $i }}][icon]" class="form-select form-select-sm icon-select">
                    @foreach (\App\Http\Controllers\Admin\SettingController::ICONS as $value => $label)
                        <option value="{{ $value }}" @selected($icon === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-3"><input type="text" name="{{ $name }}[{{ $i }}][title]" class="form-control form-control-sm" value="{{ $row['title'] ?? '' }}" placeholder="Tiêu đề"></div>
        <div class="col-md-4"><textarea name="{{ $name }}[{{ $i }}][desc]" class="form-control form-control-sm" rows="2" placeholder="Mô tả ngắn">{{ $row['desc'] ?? '' }}</textarea></div>
        <div class="col-md-2 text-end">@include('admin.settings.partials.row-actions')</div>
    </div>
</div>
