{{--
    A complete, self-contained page for errors that may happen when the database or session is unavailable
    (500, 503 and the catch-all). It must NOT touch the database or the session: if it did, it would fail
    the same way as the original error and the visitor would see nothing at all.
    <x-error-shell title="Something went wrong"> <x-error-content .../> </x-error-shell>
--}}
@props(['title'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A0A0A">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col">
    <header class="border-b border-white/10">
        <div class="mx-auto flex max-w-6xl items-center px-4 py-3 sm:px-6">
            <a href="{{ url('/') }}" class="text-xl" aria-label="{{ config('app.name') }} home"><x-logo /></a>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-white/10 px-4 py-5 text-center text-xs text-cream/50 sm:px-6">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </footer>
</body>
</html>
