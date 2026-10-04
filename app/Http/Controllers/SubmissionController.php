<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmissionRequest;
use App\Models\Submission;
use App\Support\Honeypot;

class SubmissionController extends Controller
{
    public function create()
    {
        return view('submissions.create');
    }

    public function store(SubmissionRequest $request)
    {
        // A robot filled the hidden field. Say "thanks" so it learns nothing, but save nothing.
        if (! Honeypot::tripped($request)) {
            // Always "pending": a suggestion only becomes a real restaurant when the owner creates it
            Submission::create($request->safe()->only([
                'restaurant_name', 'address', 'city', 'cuisine', 'phone', 'website', 'description', 'submitter_name', 'submitter_email',
            ]) + ['status' => 'pending']);
        }

        return redirect(route('submit.create').'#submit-form')->with('submission_received', true);
    }
}
