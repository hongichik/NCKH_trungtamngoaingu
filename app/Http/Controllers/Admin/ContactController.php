<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        $contacts = Contact::latest()->paginate(20);

        return view('admin.contacts.index', compact('contacts'));
    }

    public function show(Contact $contact): View
    {
        if ($contact->status === 'unread') {
            $contact->update(['status' => 'read']);
        }

        $contact->load('replies');

        return view('admin.contacts.show', compact('contact'));
    }

    public function reply(Request $request, Contact $contact): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string']]);

        $contact->replies()->create($data);
        $contact->update(['status' => 'replied']);

        return back()->with('status', 'Đã gửi phản hồi.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('admin.contacts.index')->with('status', 'Đã xoá liên hệ.');
    }
}
