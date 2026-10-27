{{-- Admin sign-in page --}}
<x-admin.auth-shell title="Sign in">
    <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 space-y-5">
        @csrf

        <x-input name="email" type="email" label="Email" :value="old('email')" autocomplete="username" autofocus required />
        <x-input name="password" type="password" label="Password" autocomplete="current-password" required />

        <div class="flex items-center justify-between gap-4">
            <x-checkbox name="remember" value="1" label="Keep me signed in on this computer" :checked="old('remember') === '1'" />
            <a href="{{ route('admin.password.request') }}" class="shrink-0 text-sm text-cream/60 hover:text-gold-500">Forgot your password?</a>
        </div>

        <x-button type="submit" class="w-full">Sign in</x-button>
    </form>
</x-admin.auth-shell>
