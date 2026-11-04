<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamRegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->get('type') === 'cntt' ? 'cntt' : 'hsk';

        $registrations = ExamRegistration::type($type)
            ->with(['student', 'examSession', 'examLevel'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.exam-registrations.index', [
            'type' => $type,
            'registrations' => $registrations,
        ]);
    }

    public function confirmPayment(Request $request, ExamRegistration $registration): RedirectResponse
    {
        $registration->update([
            'status' => 'paid',
            'paid_at' => now(),
            'confirmed_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Đã xác nhận thanh toán.');
    }

    public function cancel(ExamRegistration $registration): RedirectResponse
    {
        $registration->update(['status' => 'cancelled']);

        return back()->with('status', 'Đã huỷ đăng ký.');
    }

    public function destroy(ExamRegistration $registration): RedirectResponse
    {
        $registration->delete();

        return back()->with('status', 'Đã xoá bản ghi đăng ký.');
    }
}
