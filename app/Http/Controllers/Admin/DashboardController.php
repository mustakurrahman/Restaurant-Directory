<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\City;
use App\Models\ContactMessage;
use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\Submission;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'stats' => [
                'restaurants' => Restaurant::count(),
                'published' => Restaurant::published()->count(),
                'drafts' => Restaurant::where('status', 'draft')->count(),
                'cities' => City::count(),
                'cuisines' => Cuisine::count(),
                'amenities' => Amenity::count(),
                // The three "needs your attention" counts
                'pendingReviews' => Review::where('status', 'pending')->count(),
                'pendingSubmissions' => Submission::where('status', 'pending')->count(),
                'unreadMessages' => ContactMessage::where('is_read', false)->count(),
            ],
        ]);
    }
}
