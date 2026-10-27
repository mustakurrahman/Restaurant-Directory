<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    // Step 1: the "forgot your password?" page
    public function create()
    {
        return view('admin.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        // The result is ignored on purpose: the answer is the same whether or not the email exists,
        // so nobody can use this page to find out which addresses have an account
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If that is the admin email address, a reset link is on its way. It works for one hour. Check your spam folder too.');
    }

    // Step 2: the page opened from the email link
    public function edit(Request $request, string $token)
    {
        return view('admin.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (CanResetPassword $user, string $password) {
                // A new remember token signs out every computer that ticked "Keep me signed in"
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // One message for a wrong, used or expired link, so it does not reveal which one it was
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This reset link is not valid any more. Ask for a new one on the "Forgot your password?" page.']);
        }

        return redirect()->route('admin.login')->with('status', 'Your password has been changed. Sign in with the new one.');
    }
}
