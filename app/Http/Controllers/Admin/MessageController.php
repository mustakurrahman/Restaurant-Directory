<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageReadRequest;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        // Opens on "unread" because that is the work waiting for the owner
        $filter = $request->query('show');
        $filter = in_array($filter, ['unread', 'read', 'all'], true) ? $filter : 'unread';

        $messages = ContactMessage::query()
            ->when($filter === 'unread', fn ($q) => $q->where('is_read', false))
            ->when($filter === 'read', fn ($q) => $q->where('is_read', true))
            ->latest()->latest('id')
            ->paginate(15)
            ->withQueryString(); // page links keep the chosen tab

        // After the last message of the last page is handled, that page no longer exists: step back
        if ($messages->isEmpty() && $messages->currentPage() > 1) {
            return redirect($messages->url($messages->lastPage()));
        }

        // Numbers for the tabs, in one query
        $counts = ContactMessage::selectRaw('is_read, count(*) as total')->groupBy('is_read')->pluck('total', 'is_read');
        $unread = (int) ($counts[0] ?? 0);
        $read = (int) ($counts[1] ?? 0);

        return view('admin.messages.index', [
            'messages' => $messages,
            'filter' => $filter,
            'counts' => ['unread' => $unread, 'read' => $read, 'all' => $unread + $read],
        ]);
    }

    public function update(MessageReadRequest $request, ContactMessage $message)
    {
        $message->update(['is_read' => $request->boolean('is_read')]);

        return back()->with('status', 'Message from '.$message->name.' marked as '.($message->is_read ? 'read' : 'unread').'.');
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        return back()->with('status', 'Message deleted.');
    }
}
