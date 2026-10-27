{{-- Admin sign-in page: its own small page because the admin layout (sidebar, menu) is only for signed-in owners --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A0A0A">
    {{-- Keeps the login page out of Google --}}
    <meta name="robots" content="noindex, nofollow">
    <title>Admin sign in · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center px-4 py-10">
    <main class="w-full max-w-md">
        <p class="text-center font-display text-3xl font-semibold text-gold-500">{{ config('app.name') }}</p>
        <p class="mt-1 text-center text-xs uppercase tracking-widest text-cream/50">Admin panel</p>

        <x-card class="mt-8">
            <h1 class="text-2xl font-semibold">Sign in</h1>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-gold-500/40 bg-gold-500/10 px-4 py-3 text-sm text-gold-500" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 space-y-5">
                @csrf

                <x-input name="email" type="email" label="Email" :value="old('email')" autocomplete="username" autofocus required />
                <x-input name="password" type="password" label="Password" autocomplete="current-password" required />

                <x-button type="submit" class="w-full">Sign in</x-button>
            </form>
        </x-card>

        <p class="mt-6 text-center text-sm"><a href="{{ url('/') }}" class="text-cream/60 hover:text-gold-500">&larr; Back to the website</a></p>
    </main>
</body>
</html>
