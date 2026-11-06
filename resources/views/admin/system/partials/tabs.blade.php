{{-- Thanh tab của trang Quản trị hệ thống. Biến: $tabs, $tab --}}
<div class="card mb-3">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-auto">
            @foreach ($tabs as $key => [$label, $icon])
                <li class="nav-item">
                    <a href="{{ $key === 'question-types' ? route('admin.system.question-types.index') : route('admin.system.index', ['tab' => $key]) }}" class="nav-link text-nowrap {{ $tab === $key ? 'active' : '' }}">
                        <i class="ti {{ $icon }} me-2"></i>{{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
