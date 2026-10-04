<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Restaurant;
use App\Support\Honeypot;

class ReviewController extends Controller
{
    public function store(ReviewRequest $request, Restaurant $restaurant)
    {
        // A robot filled the hidden field. Say "thanks" so it learns nothing, but save nothing.
        if (! Honeypot::tripped($request)) {
            // Always "pending": nothing is public until the owner approves it
            $restaurant->reviews()->create($request->safe()->only(['name', 'email', 'rating', 'comment']) + ['status' => 'pending']);
        }

        return redirect(route('restaurants.show', $restaurant).'#review-form')->with('review_submitted', true);
    }
}
