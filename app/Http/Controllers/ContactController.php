<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(Request $request): View
    {
        return view('public.contact', [
            'subject' => old('subject', $request->query('subject')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Bẫy bot: trường ẩn "website" người thật không điền; bot điền thì giả vờ thành công.
        if (filled($request->input('website'))) {
            return back()->with('status', 'Cảm ơn bạn đã liên hệ, chúng tôi sẽ phản hồi sớm nhất.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:60'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:3000'],
        ]);

        Contact::create($data);

        return back()->with('status', 'Cảm ơn bạn đã liên hệ, chúng tôi sẽ phản hồi sớm nhất.');
    }
}
