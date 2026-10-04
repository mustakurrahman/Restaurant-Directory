<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmissionStatusRequest;
use App\Models\Submission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'rejected'];

    public function index(Request $request)
    {
        // Opens on "pending" because that is the work waiting for the owner; "all" shows everything
        $filter = $request->query('status');
        $filter = in_array($filter, [...self::STATUSES, 'all'], true) ? $filter : 'pending';

        $submissions = Submission::query()
            ->when($filter !== 'all', fn ($q) => $q->where('status', $filter))
            ->latest()->latest('id')
            ->paginate(15)
            ->withQueryString(); // page links keep the chosen tab

        // After the last item of the last page is handled, that page no longer exists: step back
        if ($submissions->isEmpty() && $submissions->currentPage() > 1) {
            return redirect($submissions->url($submissions->lastPage()));
        }

        // Numbers for the tabs, in one query
        $counts = Submission::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.submissions.index', [
            'submissions' => $submissions,
            'filter' => $filter,
            'counts' => $counts->all() + ['all' => $counts->sum()],
        ]);
    }

    public function update(SubmissionStatusRequest $request, Submission $submission)
    {
        $submission->update(['status' => $request->validated('status')]);

        return back()->with('status', '"'.$submission->restaurant_name.'" is now '.$submission->status.'.');
    }

    public function destroy(Submission $submission)
    {
        $submission->delete();

        return back()->with('status', 'Suggestion deleted.');
    }
}
