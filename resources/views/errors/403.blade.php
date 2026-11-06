@extends('errors.layout')
@section('code', '403')
@section('title', 'Không có quyền truy cập')
@section('message', $exception->getMessage() ?: 'Bạn không được phép xem trang này. Nếu cần quyền, hãy liên hệ quản trị viên.')
