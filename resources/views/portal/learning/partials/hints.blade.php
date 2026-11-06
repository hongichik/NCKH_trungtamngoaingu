@if (! empty($meta['ideas_vi']) || ! empty($meta['useful_language']))
    <div class="row g-2 mb-2 small">
        @if (! empty($meta['ideas_vi']))
            <div class="col-md-6"><div class="p-2 bg-light rounded h-100"><div class="fw-semibold"><i class="bi bi-lightbulb text-warning"></i> Gợi ý ý tưởng</div>
                <ul class="mb-0 ps-3">@foreach ($meta['ideas_vi'] as $x)<li>{{ $x }}</li>@endforeach</ul></div></div>
        @endif
        @if (! empty($meta['useful_language']))
            <div class="col-md-6"><div class="p-2 bg-light rounded h-100"><div class="fw-semibold"><i class="bi bi-chat-quote text-primary"></i> Cụm từ nên dùng</div>
                <ul class="mb-0 ps-3">@foreach ($meta['useful_language'] as $x)<li>{{ $x }}</li>@endforeach</ul></div></div>
        @endif
    </div>
@endif
