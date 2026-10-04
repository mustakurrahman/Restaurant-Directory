<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
use App\Support\Honeypot;

class ContactController extends Controller
{
    public function create()
    {
        return view('contact.create');
    }

    public function store(ContactRequest $request)
    {
        // A robot filled the hidden field. Say "thanks" so it learns nothing, but save nothing.
        if (! Honeypot::tripped($request)) {
            // is_read starts false so the admin sees it as new
            ContactMessage::create($request->safe()->only(['name', 'email', 'subject', 'message']) + ['is_read' => false]);
        }

        return redirect(route('contact.create').'#contact-form')->with('message_sent', true);
    }
}
