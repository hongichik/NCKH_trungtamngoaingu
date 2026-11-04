@extends('layouts.admin')

@section('title', 'Chi tiết liên hệ')

@section('content')
    <div class="card mb-3" style="max-width: 700px;">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-3">Họ tên</dt><dd class="col-9">{{ $contact->name }}</dd>
                <dt class="col-3">Email</dt><dd class="col-9">{{ $contact->email }}</dd>
                <dt class="col-3">Điện thoại</dt><dd class="col-9">{{ $contact->phone }}</dd>
                <dt class="col-3">Tiêu đề</dt><dd class="col-9">{{ $contact->subject }}</dd>
                <dt class="col-3">Nội dung</dt><dd class="col-9">{{ $contact->content }}</dd>
            </dl>
        </div>
    </div>

    @if ($contact->replies->isNotEmpty())
        <h2 class="h6">Lịch sử phản hồi</h2>
        <ul class="list-group mb-3" style="max-width: 700px;">
            @foreach ($contact->replies as $reply)
                <li class="list-group-item">
                    <div class="small text-muted">{{ $reply->created_at->format('d/m/Y H:i') }}</div>
                    {{ $reply->message }}
                </li>
            @endforeach
        </ul>
    @endif

    @if (auth()->user()->hasPermission('contacts', 'reply'))
        <form method="POST" action="{{ route('admin.contacts.reply', $contact) }}" style="max-width: 700px;">
            @csrf
            <div class="mb-3">
                <label class="form-label">Phản hồi</label>
                <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>
            <button class="btn btn-primary">Gửi phản hồi</button>
        </form>
    @endif
@endsection
