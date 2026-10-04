<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewStatusRequest;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'rejected'];

    public function index(Request $request)
    {
        // Opens on "pending" because that is the work waiting for the owner; "all" shows everything
        $filter = $request->query('status');
        $filter = in_array($filter, [...self::STATUSES, 'all'], true) ? $filter : 'pending';

        $reviews = Review::query()
            ->with('restaurant:id,name,slug,status') // one extra query for the whole page (no N+1)
            ->when($filter !== 'all', fn ($q) => $q->where('status', $filter))
            ->latest()->latest('id')
            ->paginate(15)
            ->withQueryString(); // page links keep the chosen tab

        // After the last review of the last page is handled, that page no longer exists: step back
        if ($reviews->isEmpty() && $reviews->currentPage() > 1) {
            return redirect($reviews->url($reviews->lastPage()));
        }

        // Numbers for the tabs, in one query
        $counts = Review::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'filter' => $filter,
            'counts' => $counts->all() + ['all' => $counts->sum()],
        ]);
    }

    public function update(ReviewStatusRequest $request, Review $review)
    {
        $review->update(['status' => $request->validated('status')]);

        return back()->with('status', 'Review by '.$review->name.' is now '.$review->status.'.');
    }

    public function destroy(Review $review)
    {
        $review->delete();

        return back()->with('status', 'Review deleted.');
    }
}
