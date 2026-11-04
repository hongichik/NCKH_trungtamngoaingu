@extends('layouts.admin')

@section('title', 'Liên hệ')

@section('content')
    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>Họ tên</th>
                <th>Email</th>
                <th>Tiêu đề</th>
                <th>Trạng thái</th>
                <th>Ngày gửi</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($contacts as $contact)
                <tr class="{{ $contact->status === 'unread' ? 'fw-bold' : '' }}">
                    <td>{{ $contact->name }}</td>
                    <td>{{ $contact->email }}</td>
                    <td>{{ $contact->subject }}</td>
                    <td>
                        @if ($contact->status === 'unread') <span class="badge bg-danger text-white">Chưa đọc</span>
                        @elseif ($contact->status === 'replied') <span class="badge bg-success text-white">Đã phản hồi</span>
                        @else <span class="badge bg-secondary text-white">Đã đọc</span>
                        @endif
                    </td>
                    <td>{{ $contact->created_at->format('d/m/Y') }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.contacts.show', $contact) }}" class="btn btn-sm btn-outline-secondary">Xem</a>
                        @if (auth()->user()->hasPermission('contacts', 'delete'))
                            <form method="POST" action="{{ route('admin.contacts.destroy', $contact) }}" class="d-inline" onsubmit="return confirm('Xoá liên hệ này?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $contacts->links() }}
@endsection
