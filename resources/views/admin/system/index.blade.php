@extends('layouts.admin')

@section('title', 'Quản trị hệ thống')

@section('content')
    @include('admin.system.partials.tabs')

    {{-- ================================================================ TỔNG QUAN --}}
    @if ($tab === 'overview')
        <div class="alert alert-info">
            <i class="ti ti-crown me-1"></i>
            Bạn đang đăng nhập bằng tài khoản <strong>chủ hệ thống</strong> ({{ $owner?->email }}, id 1) — có toàn quyền,
            không thể bị xoá hay hạ quyền. <a href="{{ route('admin.users.index') }}">Quản lý tài khoản &rarr;</a>
        </div>

        <div class="row row-cards">
            @foreach ($info as $group => $rows)
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">{{ $group }}</h3></div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                @foreach ($rows as $label => $value)
                                    <tr>
                                        <td class="text-muted w-50">{{ $label }}</td>
                                        <td>
                                            @if (is_array($value))
                                                <span class="badge {{ $value['ok'] ? 'bg-success-lt' : 'bg-danger-lt' }}">{{ $value['text'] }}</span>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ================================================================ CẤU HÌNH --}}
    @if ($tab === 'config')
        @unless ($envWritable)
            <div class="alert alert-warning">File .env không ghi được từ PHP. Hãy sửa trực tiếp qua File Manager của hosting.</div>
        @endunless

        <form method="POST" action="{{ route('admin.system.config') }}" class="card">
            @csrf
            <div class="card-header"><h3 class="card-title">Cấu hình ứng dụng (.env)</h3></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tên ứng dụng (APP_NAME)</label>
                        <input name="APP_NAME" class="form-control" value="{{ old('APP_NAME', $env->get('APP_NAME')) }}" required>
                        <div class="form-hint">Tên hiển thị trên website được ưu tiên lấy từ Cài đặt website.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Địa chỉ website (APP_URL)</label>
                        <input name="APP_URL" type="url" class="form-control" value="{{ old('APP_URL', $env->get('APP_URL')) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Môi trường (APP_ENV)</label>
                        @php $appEnv = old('APP_ENV', $env->get('APP_ENV')); @endphp
                        <select name="APP_ENV" class="form-select">
                            @foreach (['production' => 'production — máy chủ thật', 'local' => 'local — máy phát triển', 'staging' => 'staging — thử nghiệm'] as $val => $text)
                                <option value="{{ $val }}" @selected($appEnv === $val)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Chế độ debug (APP_DEBUG)</label>
                        <label class="form-check form-switch mt-2">
                            <input type="hidden" name="APP_DEBUG" value="0">
                            <input class="form-check-input" type="checkbox" name="APP_DEBUG" value="1" @checked(old('APP_DEBUG', filter_var($env->get('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)))>
                            <span class="form-check-label">Hiện chi tiết lỗi</span>
                        </label>
                        <div class="form-hint text-warning">Trên máy chủ thật nên TẮT — nếu bật, khi có lỗi người ngoài có thể thấy thông tin nhạy cảm.</div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary" @disabled(! $envWritable)>Lưu cấu hình</button>
            </div>
        </form>
        <p class="text-muted small mt-2">Mỗi lần lưu, bản .env cũ được giữ lại trong storage/app/env-backups.</p>
    @endif

    {{-- ================================================================ ĐỒNG BỘ --}}
    @if ($tab === 'sync')
        @if (session('new_token'))
            <div class="alert alert-warning">
                <div class="fw-bold mb-1">Token mới (chỉ hiện 1 lần — hãy chép vào DB_SYNC_TOKEN ở máy còn lại):</div>
                <code class="user-select-all fs-4">{{ session('new_token') }}</code>
            </div>
        @endif

        @php
            $defaultExcluded = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
                'ai_jobs', 'ai_usage', 'teachers', 'textbooks', 'textbook_pages', 'textbook_units', 'textbook_chunks', 'unit_profiles', 'audio_tracks'];
            $extraExcluded = old('exclude', array_filter(explode(',', (string) $env->get('DB_SYNC_EXCLUDE'))));
            $token = (string) config('dbsync.token');
        @endphp

        <div class="row row-cards">
            <div class="col-lg-7">
                <form method="POST" action="{{ route('admin.system.sync') }}" class="card">
                    @csrf
                    <div class="card-header"><h3 class="card-title">Cấu hình đồng bộ</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-check form-switch">
                                <input type="hidden" name="enabled" value="0">
                                <input class="form-check-input" type="checkbox" name="enabled" value="1" @checked(old('enabled', config('dbsync.enabled')))>
                                <span class="form-check-label fw-semibold">Cho phép đồng bộ CSDL</span>
                            </label>
                            <div class="form-hint">Cho phép máy có token đẩy dữ liệu lên (<code>db:sync-push</code>) hoặc kéo dữ liệu về (<code>db:sync-pull</code>).</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-check form-switch">
                                <input type="hidden" name="files_enabled" value="0">
                                <input class="form-check-input" type="checkbox" name="files_enabled" value="1" @checked(old('files_enabled', config('dbsync.files_enabled')))>
                                <span class="form-check-label fw-semibold">Cho phép đồng bộ thư mục</span>
                            </label>
                            <div class="form-hint">Ảnh &amp; file tải lên (<code>files:sync-push</code> / <code>files:sync-pull</code>). File .php và file ẩn luôn bị chặn.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Thư mục đồng bộ</label>
                            <textarea name="file_dirs" rows="2" class="form-control font-monospace" placeholder="storage/app/public">{{ old('file_dirs', implode("\n", config('dbsync.file_dirs'))) }}</textarea>
                            <div class="form-hint">Mỗi dòng 1 thư mục, tính từ thư mục gốc dự án. Chỉ chấp nhận trong <code>storage/app/</code> hoặc <code>public/</code>.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Địa chỉ máy đích (DB_SYNC_REMOTE_URL)</label>
                            <input name="remote_url" type="url" class="form-control" value="{{ old('remote_url', config('dbsync.remote_url')) }}" placeholder="https://demo.cnttuhl.site">
                            <div class="form-hint">Chỉ cần ở máy chạy lệnh đẩy/kéo (thường là máy local).</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Số bản sao lưu tự động giữ lại</label>
                            <input name="backup_keep" type="number" min="1" max="200" class="form-control w-25" value="{{ old('backup_keep', config('dbsync.backup_keep')) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bảng KHÔNG đồng bộ</label>
                            <div class="row g-1">
                                @foreach ($allTables as $t)
                                    <div class="col-6 col-md-4">
                                        <label class="form-check mb-0">
                                            @if (in_array($t, $defaultExcluded, true))
                                                <input class="form-check-input" type="checkbox" checked disabled>
                                                <span class="form-check-label text-muted">{{ $t }}</span>
                                            @else
                                                <input class="form-check-input" type="checkbox" name="exclude[]" value="{{ $t }}" @checked(in_array($t, $extraExcluded, true))>
                                                <span class="form-check-label">{{ $t }}</span>
                                            @endif
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-hint">Bảng mờ luôn bị loại (bảng hệ thống, hàng đợi AI, giáo viên &amp; giáo trình — do giáo viên tạo trên máy chủ). Ví dụ tick <code>users</code> để không bao giờ ghi đè tài khoản trên máy này.</div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label">Đặt token thủ công (tuỳ chọn)</label>
                            <input name="token" class="form-control font-monospace" autocomplete="off" placeholder="Để trống nếu không đổi">
                            <div class="form-hint">Dán token của máy còn lại vào đây để 2 máy dùng chung. Tối thiểu 32 ký tự.</div>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button class="btn btn-primary" @disabled(! $envWritable)>Lưu cấu hình đồng bộ</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title">Token bảo mật</h3></div>
                    <div class="card-body">
                        @if ($token)
                            <div class="mb-2">Token hiện tại: <code>{{ substr($token, 0, 6) }}…{{ substr($token, -4) }}</code> ({{ strlen($token) }} ký tự)</div>
                        @else
                            <div class="alert alert-warning mb-2">Chưa có token — API đồng bộ đang tắt hoàn toàn.</div>
                        @endif
                        <form method="POST" action="{{ route('admin.system.token') }}" onsubmit="return confirm('Tạo token mới? Máy còn lại sẽ không đồng bộ được cho tới khi cập nhật token mới.')">
                            @csrf
                            <button class="btn btn-outline-warning" @disabled(! $envWritable)><i class="ti ti-key me-1"></i>Tạo token mới</button>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title">Lệnh chạy ở máy local</h3></div>
                    <div class="card-body small">
                        <div class="mb-2"><code>php artisan db:sync-push --exclude=users</code><br><span class="text-muted">Đẩy CSDL lên máy chủ (giữ tài khoản trên máy chủ)</span></div>
                        <div class="mb-2"><code>php artisan db:sync-pull</code><br><span class="text-muted">Kéo CSDL từ máy chủ về</span></div>
                        <div class="mb-2"><code>php artisan files:sync-push</code><br><span class="text-muted">Đẩy ảnh/file mới lên (thêm <code>--delete</code> để xoá file thừa)</span></div>
                        <div><code>php artisan files:sync-pull</code><br><span class="text-muted">Tải ảnh/file từ máy chủ về</span></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Dữ liệu trên máy này</h3></div>
                    <div class="table-responsive" style="max-height: 320px">
                        <table class="table table-sm card-table">
                            @foreach ($tables as $t => $n)
                                <tr><td>{{ $t }}</td><td class="text-end text-muted">{{ number_format($n) }}</td></tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ================================================================ AI --}}
    @if ($tab === 'ai')
        @php
            $key = (string) config('ai.openai.key');
            $budget = (float) config('ai.limits.monthly_budget_usd');
            $pct = $budget > 0 ? min(100, round($aiStats['month_cost'] / $budget * 100)) : 0;
            $featureLabels = ['analysis' => 'Phân tích tài liệu', 'generate' => 'Sinh bài tập', 'grading' => 'Chấm bài', 'ocr' => 'Đọc trang scan', 'transcribe' => 'Chép lời', 'tts' => 'Giọng đọc'];
            $statusCls = ['pending' => 'bg-secondary-lt', 'running' => 'bg-blue-lt', 'done' => 'bg-success-lt', 'failed' => 'bg-danger-lt'];
        @endphp

        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body">
                <div class="text-muted small">Lượt AI hôm nay</div><div class="h2 mb-0">{{ number_format($aiStats['today_calls']) }}</div>
            </div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body">
                <div class="text-muted small">Lượt AI tháng này</div>
                <div class="h2 mb-0">{{ number_format($aiStats['month_calls']) }}@if (config('ai.limits.monthly_calls'))<span class="text-muted fs-5"> / {{ number_format(config('ai.limits.monthly_calls')) }}</span>@endif</div>
                @if ($aiStats['month_failed'])<div class="small text-danger">{{ $aiStats['month_failed'] }} lượt lỗi</div>@endif
            </div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body">
                <div class="text-muted small">Chi phí ước tính tháng này</div>
                <div class="h2 mb-1">${{ number_format($aiStats['month_cost'], 2) }}@if ($budget)<span class="text-muted fs-5"> / ${{ number_format($budget, 2) }}</span>@endif</div>
                @if ($budget)<div class="progress progress-sm"><div class="progress-bar {{ $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-primary') }}" style="width: {{ $pct }}%"></div></div>@endif
            </div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body">
                <div class="text-muted small">Hàng đợi</div>
                <div class="d-flex gap-2 flex-wrap mt-1">
                    @foreach (\App\Models\AiJob::STATUS_LABELS as $st => $label)
                        <span class="badge {{ $statusCls[$st] }}">{{ $label }}: {{ $jobCounts[$st] }}</span>
                    @endforeach
                </div>
                <div class="small mt-2 {{ $heartbeat ? 'text-muted' : 'text-warning' }}">
                    Cron: {{ $heartbeat ? 'chạy lần cuối '.\Illuminate\Support\Carbon::parse($heartbeat)->diffForHumans() : 'chưa thấy chạy' }}
                </div>
            </div></div></div>
        </div>

        @if ($lanes)
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Hàng đợi theo người <span class="text-muted fw-normal small">(mỗi người một làn)</span></h3></div>
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter card-table">
                        <thead><tr><th>Người gửi</th><th class="text-end">Đang chờ</th><th>Trạng thái</th></tr></thead>
                        @foreach ($lanes as $l)
                            <tr>
                                <td>{{ $l['label'] }}</td>
                                <td class="text-end">{{ number_format($l['pending']) }}</td>
                                <td>{!! $l['running'] ? '<span class="badge bg-blue-lt">Đang xử lý</span>' : '<span class="badge bg-secondary-lt">Chờ tới lượt</span>' !!}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif

        <div class="row row-cards">
            <div class="col-lg-7">
                <form method="POST" action="{{ route('admin.system.ai') }}" class="card">
                    @csrf
                    <div class="card-header"><h3 class="card-title">Cấu hình AI (OpenAI)</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">API key</label>
                            <div class="mb-1 small">
                                @if ($key)
                                    Đang dùng: <code>{{ substr($key, 0, 7) }}…{{ substr($key, -4) }}</code>
                                @else
                                    <span class="text-danger">Chưa có API key — AI chưa dùng được.</span>
                                @endif
                            </div>
                            <input name="api_key" class="form-control font-monospace" autocomplete="off" placeholder="Dán key mới để thay (để trống nếu không đổi)">
                        </div>

                        <label class="form-label">Model cho từng chức năng</label>
                        <div class="row g-2 mb-3">
                            @foreach (['analysis' => 'Phân tích tài liệu', 'generate' => 'Sinh bài tập', 'grading' => 'Chấm Nói / Viết', 'ocr' => 'Đọc trang scan (OCR)', 'transcribe' => 'Chép lời âm thanh', 'tts' => 'Giọng đọc'] as $f => $label)
                                <div class="col-md-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="min-width: 150px">{{ $label }}</span>
                                        <input name="model_{{ $f }}" class="form-control font-monospace" value="{{ old('model_'.$f, config('ai.models.'.$f)) }}" required>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-check form-switch mt-1">
                                    <input type="hidden" name="tts_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" name="tts_enabled" value="1" @checked(old('tts_enabled', config('ai.tts_enabled')))>
                                    <span class="form-check-label">Bật giọng đọc AI cho bài Nghe</span>
                                </label>
                                <div class="form-hint">Tắt thì dùng audio giáo trình hoặc giọng của trình duyệt (miễn phí).</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Thời gian chờ mỗi lượt (giây)</label>
                                <input name="timeout" type="number" min="10" max="120" class="form-control" value="{{ old('timeout', config('ai.timeout')) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Giữ ghi âm bài Nói (ngày)</label>
                                <input name="recordings_keep_days" type="number" min="0" max="3650" class="form-control" value="{{ old('recordings_keep_days', config('ai.recordings_keep_days')) }}">
                                <div class="form-hint">Quá hạn tự xoá file (điểm vẫn giữ). 0 = giữ mãi.</div>
                            </div>
                        </div>

                        <h4 class="mt-2">Hạn mức &amp; ngân sách <span class="text-muted fw-normal small">(0 = không giới hạn)</span></h4>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Lượt / người / ngày</label>
                                <input name="limit_actor_daily" type="number" min="0" class="form-control" value="{{ old('limit_actor_daily', config('ai.limits.per_actor_daily')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Lượt / tháng (toàn hệ thống)</label>
                                <input name="limit_monthly_calls" type="number" min="0" class="form-control" value="{{ old('limit_monthly_calls', config('ai.limits.monthly_calls')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ngân sách / tháng (USD)</label>
                                <input name="budget_monthly_usd" type="number" min="0" step="0.01" class="form-control" value="{{ old('budget_monthly_usd', config('ai.limits.monthly_budget_usd')) }}">
                            </div>
                        </div>

                        <h4 class="mt-2">Đơn giá để ước tính chi phí <span class="text-muted fw-normal small">(USD — lấy theo bảng giá của OpenAI cho model đang dùng)</span></h4>
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label small">Input / 1M token</label><input name="price_input_1m" type="number" min="0" step="0.0001" class="form-control" value="{{ old('price_input_1m', config('ai.pricing.input_per_1m_tokens')) }}"></div>
                            <div class="col-md-3"><label class="form-label small">Output / 1M token</label><input name="price_output_1m" type="number" min="0" step="0.0001" class="form-control" value="{{ old('price_output_1m', config('ai.pricing.output_per_1m_tokens')) }}"></div>
                            <div class="col-md-3"><label class="form-label small">Chép lời / phút</label><input name="price_transcribe_min" type="number" min="0" step="0.0001" class="form-control" value="{{ old('price_transcribe_min', config('ai.pricing.transcribe_per_minute')) }}"></div>
                            <div class="col-md-3"><label class="form-label small">Giọng đọc / 1M ký tự</label><input name="price_tts_1m_chars" type="number" min="0" step="0.0001" class="form-control" value="{{ old('price_tts_1m_chars', config('ai.pricing.tts_per_1m_chars')) }}"></div>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button class="btn btn-primary" @disabled(! $envWritable)>Lưu cấu hình AI</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title">Kiểm tra</h3></div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.system.ai.test') }}">@csrf
                            <button class="btn btn-primary" @disabled(! $key)><i class="ti ti-plug-connected me-1"></i>Gửi thử trực tiếp</button>
                        </form>
                        <form method="POST" action="{{ route('admin.system.ai.test-job') }}">@csrf
                            <button class="btn btn-outline-primary" @disabled(! $key)><i class="ti ti-list-check me-1"></i>Thử qua hàng đợi</button>
                        </form>
                        <form method="POST" action="{{ route('admin.system.ai.tick') }}">@csrf
                            <button class="btn btn-outline-secondary"><i class="ti ti-player-play me-1"></i>Chạy hàng đợi ngay</button>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title">Cài cron trên cPanel (1 lần)</h3></div>
                    <div class="card-body small">
                        <p class="mb-2">cPanel → <strong>Cron Jobs</strong> → thêm <strong>{{ count($cronLines) }} dòng</strong>, mỗi dòng chọn <em>Once Per Minute (* * * * *)</em> và dán 1 lệnh. Số dòng = số giáo viên được xử lý <strong>cùng lúc</strong> (mỗi giáo viên một làn, việc của cùng người luôn chạy lần lượt theo thứ tự gửi; nhiều người hơn số dòng thì xoay vòng).</p>
                        @foreach ($cronLines as $line)
                            <code class="d-block p-2 bg-light user-select-all mb-1" style="word-break: break-all">{{ $line }}</code>
                        @endforeach
                        <p class="text-muted mt-2 mb-0">Mỗi tiến trình làm <strong>liên tục việc này xong tới việc sau cho tới khi hết hàng đợi</strong> (tối đa 50 phút/lượt). Cron mỗi phút chỉ khởi động lại khi tiến trình đã dừng — việc mới bắt đầu trong vòng 1 phút. Đường dẫn PHP <code>/usr/local/bin/ea-php82</code> theo PHP 8.2 của hosting; không dùng <code>schedule:run</code> (hosting tắt <code>proc_open</code>).</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Theo chức năng (tháng này)</h3></div>
                    <div class="table-responsive">
                        <table class="table table-sm card-table">
                            <thead><tr><th>Chức năng</th><th class="text-end">Lượt</th><th class="text-end">Chi phí</th></tr></thead>
                            @forelse ($aiStats['by_feature'] as $row)
                                <tr>
                                    <td>{{ $featureLabels[$row->feature] ?? $row->feature }}</td>
                                    <td class="text-end">{{ number_format($row->calls) }}</td>
                                    <td class="text-end text-muted">${{ number_format((float) $row->cost, 4) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">Chưa có lượt nào.</td></tr>
                            @endforelse
                        </table>
                    </div>
                    @if ($aiStats['recent_errors']->isNotEmpty())
                        <div class="card-body border-top small">
                            <div class="fw-semibold mb-1">Lỗi gần đây</div>
                            @foreach ($aiStats['recent_errors'] as $err)
                                <div class="text-danger mb-1">{{ $err->created_at->format('d/m H:i') }} · {{ $err->feature }}: {{ $err->error }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Việc nền gần đây</h3>
                @if ($jobCounts['failed'])
                    <div class="card-actions">
                        <form method="POST" action="{{ route('admin.system.ai.retry-failed') }}">@csrf
                            <button class="btn btn-sm btn-warning"><i class="ti ti-refresh me-1"></i>Chạy lại tất cả {{ $jobCounts['failed'] }} việc lỗi</button>
                        </form>
                    </div>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>#</th><th>Loại</th><th>Nhóm</th><th>Trạng thái</th><th>Lần thử</th><th>Kết quả / lỗi</th><th>Tạo lúc</th><th class="w-1"></th></tr></thead>
                    <tbody>
                        @forelse ($jobs as $job)
                            <tr>
                                <td class="text-muted">{{ $job->id }}</td>
                                <td><code>{{ $job->type }}</code></td>
                                <td class="text-muted small">{{ $job->batch ?? '—' }}</td>
                                <td>
                                    @if ($job->isPaused())
                                        <span class="badge bg-yellow-lt">Tạm dừng</span>
                                    @else
                                        <span class="badge {{ $statusCls[$job->status] ?? '' }}">{{ \App\Models\AiJob::STATUS_LABELS[$job->status] ?? $job->status }}</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $job->attempts }}/{{ $job->max_attempts }}</td>
                                <td class="small" style="max-width: 360px">
                                    @if ($job->isPaused())
                                        <span class="text-warning">{{ \Illuminate\Support\Str::limit($job->last_error, 160) }}</span>
                                    @elseif ($job->last_error)
                                        <span class="text-danger">{{ \Illuminate\Support\Str::limit($job->last_error, 160) }}</span>
                                    @elseif ($job->result)
                                        <span class="text-muted">{{ \Illuminate\Support\Str::limit(json_encode($job->result, JSON_UNESCAPED_UNICODE), 160) }}</span>
                                    @endif
                                </td>
                                <td class="text-muted text-nowrap small">{{ $job->created_at->format('d/m H:i:s') }}</td>
                                <td>
                                    @if ($job->status === 'failed')
                                        <form method="POST" action="{{ route('admin.system.ai.retry', $job) }}">@csrf<button class="btn btn-sm btn-outline-warning">Chạy lại</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Chưa có việc nào.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ================================================================ SAO LƯU --}}
    @if ($tab === 'backups')
        <div class="row row-cards mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="card-title">Tạo bản sao lưu ngay</h3>
                        <p class="text-muted">Lưu toàn bộ dữ liệu (trừ bảng hệ thống) của máy này.</p>
                        <form method="POST" action="{{ route('admin.system.backups.create') }}">
                            @csrf
                            <button class="btn btn-primary"><i class="ti ti-database-export me-1"></i>Sao lưu</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="card-title">Tải lên bản sao lưu</h3>
                        <p class="text-muted">File <code>.ndjson.gz</code> tải từ máy khác (tối đa {{ ini_get('upload_max_filesize') }}).</p>
                        <form method="POST" action="{{ route('admin.system.backups.upload') }}" enctype="multipart/form-data" class="d-flex gap-2">
                            @csrf
                            <input type="file" name="file" accept=".gz" class="form-control" required>
                            <button class="btn btn-outline-primary text-nowrap">Tải lên</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Các bản sao lưu</h3>
            </div>
            <div class="card-body pb-0 text-muted small">
                <code>backup-*</code>: tự tạo trước mỗi lần nhận dữ liệu đồng bộ / khôi phục (khôi phục bản này = hoàn tác lần đồng bộ đó) ·
                <code>manual-*</code>: tạo tay · <code>upload-*</code>: tải lên.
                Khôi phục sẽ ghi đè các bảng có trong bản sao lưu; dữ liệu hiện tại được tự sao lưu trước.
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Tên</th><th>Thời gian</th><th class="text-end">Dung lượng</th><th class="w-1"></th></tr></thead>
                    <tbody>
                        @forelse ($backups as $b)
                            <tr>
                                <td class="font-monospace small">{{ $b['name'] }}</td>
                                <td class="text-muted">{{ \Illuminate\Support\Carbon::createFromTimestamp($b['time'])->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                                <td class="text-end text-muted">{{ number_format($b['size'] / 1024, 1) }} KB</td>
                                <td class="text-nowrap">
                                    <form method="POST" action="{{ route('admin.system.backups.restore', $b['name']) }}" class="d-inline" onsubmit="return confirm('Khôi phục dữ liệu từ {{ $b['name'] }}?\nCác bảng trong bản sao lưu sẽ bị GHI ĐÈ (dữ liệu hiện tại được tự sao lưu trước).')">
                                        @csrf
                                        <button class="btn btn-sm btn-warning">Khôi phục</button>
                                    </form>
                                    <a href="{{ route('admin.system.backups.download', $b['name']) }}" class="btn btn-sm btn-outline-secondary">Tải về</a>
                                    <form method="POST" action="{{ route('admin.system.backups.delete', $b['name']) }}" class="d-inline" onsubmit="return confirm('Xoá {{ $b['name'] }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Xoá</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Chưa có bản sao lưu nào.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ================================================================ LỆNH --}}
    @if ($tab === 'commands')
        @if ($result = session('command_result'))
            <div class="card mb-3 border-{{ $result['ok'] ? 'success' : 'danger' }}">
                <div class="card-header">
                    <h3 class="card-title">
                        <span class="badge {{ $result['ok'] ? 'bg-success' : 'bg-danger' }} text-white me-2">{{ $result['ok'] ? 'Thành công' : 'Lỗi' }}</span>
                        {{ $result['label'] }}
                    </h3>
                </div>
                <pre class="m-0 p-3 bg-dark text-white small" style="white-space: pre-wrap; max-height: 400px; overflow: auto">{{ $result['output'] }}</pre>
            </div>
        @endif

        <div class="row row-cards">
            @foreach ($commands as $key => [$label, $command, $params, $desc, $confirm])
                <div class="col-md-6 col-xl-4">
                    <form method="POST" action="{{ route('admin.system.command') }}" class="card h-100"
                        @if ($confirm) onsubmit="return confirm('Chạy “{{ $label }}” trên máy này?')" @endif>
                        @csrf
                        <input type="hidden" name="command" value="{{ $key }}">
                        <div class="card-body">
                            <h3 class="card-title mb-1">{{ $label }}</h3>
                            <code class="small">php artisan {{ $command }}{{ $params ? ' '.implode(' ', array_keys($params)) : '' }}</code>
                            @if ($desc)<p class="text-muted small mt-2 mb-0">{{ $desc }}</p>@endif
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-sm {{ $confirm ? 'btn-warning' : 'btn-outline-primary' }}"><i class="ti ti-player-play me-1"></i>Chạy</button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
        <p class="text-muted small mt-3">Chỉ các lệnh trong danh sách mới chạy được (không cho nhập lệnh tuỳ ý để đảm bảo an toàn).</p>
    @endif

    {{-- ================================================================ NHẬT KÝ --}}
    @if ($tab === 'logs')
        @php
            $actionLabels = [
                'db-push' => ['Nhận CSDL đồng bộ', 'bg-blue-lt'],
                'files-push' => ['Nhận file đồng bộ', 'bg-cyan-lt'],
                'restore' => ['Khôi phục', 'bg-orange-lt'],
                'backup' => ['Sao lưu tay', 'bg-green-lt'],
                'backup-upload' => ['Tải lên sao lưu', 'bg-green-lt'],
                'config' => ['Sửa cấu hình', 'bg-purple-lt'],
                'command' => ['Chạy lệnh', 'bg-secondary-lt'],
            ];
        @endphp
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Thời gian</th><th>Hành động</th><th>Chi tiết</th></tr></thead>
                    <tbody>
                        @forelse ($log as $entry)
                            @php [$text, $cls] = $actionLabels[$entry['action']] ?? [$entry['action'], 'bg-secondary-lt']; @endphp
                            <tr>
                                <td class="text-muted text-nowrap">{{ \Illuminate\Support\Carbon::parse($entry['time'])->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                                <td><span class="badge {{ $cls }}">{{ $text }}</span></td>
                                <td class="small">
                                    @foreach (\Illuminate\Support\Arr::except($entry, ['time', 'action']) as $k => $v)
                                        <div>
                                            <span class="text-muted">{{ $k }}:</span>
                                            @if (is_array($v))
                                                {{ collect($v)->map(fn ($n, $t) => is_int($t) ? $n : "$t ($n)")->implode(', ') ?: '—' }}
                                            @else
                                                {{ $v === null || $v === '' ? '—' : $v }}
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">Chưa có hoạt động nào.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
