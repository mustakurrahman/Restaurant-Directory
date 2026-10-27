{{-- Step 1 of a password reset: ask for the email, then a link is sent to it --}}
<x-admin.auth-shell title="Forgot your password?">
    <p class="mt-3 text-sm text-cream/70">Type your admin email address. If it is the right one, we will email you a link to choose a new password. The link works for one hour.</p>

    <form method="POST" action="{{ route('admin.password.email') }}" class="mt-6 space-y-5">
        @csrf

        <x-input name="email" type="email" label="Email" :value="old('email')" autocomplete="username" autofocus required />

        <x-button type="submit" class="w-full">Email me a reset link</x-button>
    </form>

    <p class="mt-6 text-center text-sm"><a href="{{ route('admin.login') }}" class="text-cream/60 hover:text-gold-500">Back to sign in</a></p>
</x-admin.auth-shell>
