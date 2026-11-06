@extends('layouts.admin')

@section('title', 'Thư viện dạng bài')

@php
    use App\Support\QuestionTypes\QuestionType;
    use App\Support\QuestionTypes\Registry;
    $tab = 'question-types';
@endphp

@section('content')
    @include('admin.system.partials.tabs')

    <div class="row row-cards mb-3">
        <div class="col-sm-4"><div class="card card-sm"><div class="card-body"><div class="h2 mb-0">{{ $total }}</div><div class="text-muted">dạng bài trong thư viện</div></div></div></div>
        <div class="col-sm-4"><div class="card card-sm"><div class="card-body"><div class="h2 mb-0">{{ count($engines) }}</div><div class="text-muted">kiểu giao diện làm bài</div></div></div></div>
        <div class="col-sm-4"><div class="card card-sm"><div class="card-body"><div class="h2 mb-0 {{ $disabled ? 'text-warning' : '' }}">{{ $disabled }}</div><div class="text-muted">dạng đang tắt</div></div></div></div>
    </div>

    <div class="alert alert-info">
        Mỗi dạng bài được định nghĩa một lần (lược đồ dữ liệu, giao diện làm bài, cách chấm, cách tính điểm).
        Mọi nguồn bài tập — sinh từ giáo trình, giáo viên tự soạn, AI đọc file Word — đều dùng chung các dạng này.
        Dạng bị <b>tắt</b> sẽ không xuất hiện khi giáo viên / AI tạo bài mới; bài đã tạo vẫn làm được.
    </div>

    <form class="card mb-3" method="GET">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Tìm</label>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Tên hoặc mã, vd tfng">
            </div>
            @foreach (['lang' => ['Ngôn ngữ', QuestionType::LANGS], 'skill' => ['Kỹ năng', QuestionType::SKILLS], 'standard' => ['Chuẩn đề', QuestionType::STANDARDS], 'engine' => ['Giao diện', collect($engines)->map->label()->all()]] as $key => [$label, $opts])
                <div class="col-6 col-md-2">
                    <label class="form-label">{{ $label }}</label>
                    <select name="{{ $key }}" class="form-select" onchange="this.form.submit()">
                        <option value="">Tất cả</option>
                        @foreach ($opts as $v => $l)<option value="{{ $v }}" @selected(($filters[$key] ?? '') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-6 col-md-1">
                <label class="form-label">Trạng thái</label>
                <select name="enabled" class="form-select" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    <option value="1" @selected(($filters['enabled'] ?? '') === '1')>Bật</option>
                    <option value="0" @selected(($filters['enabled'] ?? '') === '0')>Tắt</option>
                </select>
            </div>
        </div>
        @if (array_filter($filters))
            <div class="card-footer py-2 small">Đang hiện {{ $shown }} / {{ $total }} dạng · <a href="{{ route('admin.system.question-types.index') }}">Bỏ lọc</a></div>
        @endif
    </form>

    @forelse ($groups as $standard => $types)
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">{{ QuestionType::STANDARDS[$standard] }} <span class="badge bg-secondary-lt ms-1">{{ $types->count() }}</span></h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Dạng bài</th><th>Kỹ năng</th><th>Ngôn ngữ</th><th>Giao diện</th><th>Chấm</th><th class="w-1">Trạng thái</th></tr></thead>
                    <tbody>
                        @foreach ($types as $t)
                            @php $on = Registry::enabled($t->code); @endphp
                            <tr class="{{ $on ? '' : 'opacity-50' }}">
                                <td>
                                    <a href="{{ route('admin.system.question-types.show', $t->code) }}" class="fw-semibold">{{ $t->name }}</a>
                                    <div class="text-muted small font-monospace">{{ $t->code }}</div>
                                </td>
                                <td>{{ $t->label('skill') }}</td>
                                <td>{{ $t->label('lang') }}</td>
                                <td class="small">{{ $t->engine()->label() }}</td>
                                <td><span class="badge {{ $t->grading === 'auto' ? 'bg-green-lt' : 'bg-purple-lt' }}">{{ $t->label('grading') }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.system.question-types.toggle', $t->code) }}">
                                        @csrf
                                        <button class="btn btn-sm {{ $on ? 'btn-outline-success' : 'btn-outline-secondary' }}" title="Bấm để {{ $on ? 'tắt' : 'bật' }}">{{ $on ? 'Đang bật' : 'Đang tắt' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-muted">Không có dạng bài nào khớp bộ lọc.</div></div>
    @endforelse
@endsection
