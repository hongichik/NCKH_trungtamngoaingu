@extends('layouts.app')

@section('title', 'Liên hệ')

@section('content')
    <div class="container py-5">
        <h1 class="h3 mb-4">Liên hệ</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('contact.store') }}" style="max-width: 600px;">
            {{-- Bẫy bot: người thật không thấy và không điền --}}
            <div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            @csrf
            <div class="mb-3">
                <label class="form-label">Họ tên</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Điện thoại</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Tiêu đề</label>
                <input type="text" name="subject" class="form-control" value="{{ $subject }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Nội dung</label>
                <textarea name="content" class="form-control" rows="5" required>{{ old('content') }}</textarea>
            </div>
            <button class="btn btn-primary">Gửi liên hệ</button>
        </form>
    </div>
@endsection
