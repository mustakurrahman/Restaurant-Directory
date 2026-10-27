<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The admin accounts: the people who can sign in to /admin. There are no public accounts on this website.
 */
class UserController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request)
    {
        // A search box filled with something odd (an array in the address, for example) is treated as empty
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';

        $users = User::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = Like::contains($search);

                $query->where(fn (Builder $q) => $q
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like]));
            })
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // The last account of the last page was deleted: step back to the last page that still exists
        if ($users->isEmpty() && $users->currentPage() > 1) {
            return redirect($users->url($users->lastPage()));
        }

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'currentId' => Auth::id(),
            'viewerIsOwner' => (bool) Auth::user()?->is_owner,
        ]);
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        User::create($request->safe()->only(['name', 'email', 'password']));

        return redirect()->route('admin.users.index')->with('status', 'Admin added. They can sign in now with that email and password.');
    }

    public function edit(User $user)
    {
        if ($refused = $this->refuseOwnerEdit($user)) {
            return $refused;
        }

        return view('admin.users.edit', ['user' => $user]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        if ($refused = $this->refuseOwnerEdit($user)) {
            return $refused;
        }

        $data = $request->safe()->only(['name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
            // A new remember token signs this account out of every computer that ticked "Keep me signed in"
            $data['remember_token'] = Str::random(60);
        }

        $user->forceFill($data)->save();

        return redirect()->route('admin.users.index')->with('status', 'Admin updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        // Nobody can delete the Owner, not even another admin
        if ($user->is_owner) {
            return redirect()->route('admin.users.index')->with('error', 'The Owner account cannot be deleted.');
        }

        // Deleting yourself would lock you out, and it is the only thing that could remove the last admin
        if ($user->is(Auth::user())) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete the account you are signed in with. Ask another admin, or sign in as someone else first.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'Admin deleted. They can no longer sign in.');
    }

    // Only the Owner may change the Owner account (its email and password), so another admin cannot take it over
    private function refuseOwnerEdit(User $user): ?RedirectResponse
    {
        if ($user->is_owner && ! Auth::user()?->is_owner) {
            return redirect()->route('admin.users.index')->with('error', 'Only the Owner can change the Owner account.');
        }

        return null;
    }
}
