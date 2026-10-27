<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function create()
    {
        return view('admin.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        // The same message for "no such email" and "wrong password", so nobody can find out which emails exist
        // Ticked "Keep me signed in": Laravel also sets a long-lasting cookie. Its default is about 5 years, too long for an admin, so 30 days.
        Auth::guard('web')->setRememberDuration(60 * 24 * 30);

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'The email or password is not correct.']);
        }

        // A new session id after signing in stops "session fixation" (someone planting an id beforehand)
        $request->session()->regenerate();

        // intended(): back to the page they asked for before being sent to the login
        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'You are signed out.');
    }
}
