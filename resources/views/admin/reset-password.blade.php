{{-- Step 2 of a password reset: opened from the link in the email --}}
<x-admin.auth-shell title="Choose a new password">
    <form method="POST" action="{{ route('admin.password.update') }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-input name="email" type="email" label="Email" :value="$email" autocomplete="username" required />
        <x-input name="password" type="password" label="New password" hint="At least 12 characters. A few unrelated words and a number work well." autocomplete="new-password" autofocus required />
        <x-input name="password_confirmation" type="password" label="Type the new password again" autocomplete="new-password" required />

        <x-button type="submit" class="w-full">Save new password</x-button>
    </form>
</x-admin.auth-shell>
