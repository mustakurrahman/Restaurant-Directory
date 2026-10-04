{{-- Shell for every admin page: <x-admin.layout title="Cities"> ...page content... </x-admin.layout> --}}
@props(['title' => 'Admin'])

@php
    // [label, route name, pattern that marks the item as "current"]
    $nav = [
        ['Dashboard', 'admin.dashboard', 'admin.dashboard'],
        ['Restaurants', 'admin.restaurants.index', 'admin.restaurants.*'],
        ['Cities', 'admin.cities.index', 'admin.cities.*'],
        ['Cuisines', 'admin.cuisines.index', 'admin.cuisines.*'],
        ['Amenities', 'admin.amenities.index', 'admin.amenities.*'],
        ['Reviews', 'admin.reviews.index', 'admin.reviews.*'],
        ['Submissions', 'admin.submissions.index', 'admin.submissions.*'],
        ['Messages', 'admin.messages.index', 'admin.messages.*'],
    ];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Keeps the admin out of Google --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Admin · {{ config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="lg:flex lg:min-h-screen">

        {{-- Top bar: phones and tablets only --}}
        <header class="flex items-center justify-between border-b border-white/10 bg-ink-900 px-4 py-3 lg:hidden">
            <a href="{{ route('admin.dashboard') }}" class="font-display text-lg font-semibold text-gold-500">Admin</a>
            <button type="button" data-toggle="admin-sidebar" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open or close menu"
                    class="rounded-lg border border-white/20 px-3 py-2 text-sm font-medium text-cream hover:border-gold-500">
                Menu
            </button>
        </header>

        {{-- Sidebar: hidden on phones until "Menu" is tapped, always visible on large screens --}}
        <aside id="admin-sidebar"
               class="hidden w-full shrink-0 border-white/10 bg-ink-900 lg:sticky lg:top-0 lg:block lg:h-screen lg:w-64 lg:overflow-y-auto lg:border-r">
            <div class="hidden p-6 lg:block">
                <a href="{{ route('admin.dashboard') }}" class="font-display text-xl font-semibold text-gold-500">
                    {{ config('app.name') }}
                </a>
                <p class="mt-1 text-xs uppercase tracking-widest text-cream/50">Admin panel</p>
            </div>

            <nav class="space-y-1 px-3 pb-4 pt-3 lg:pt-0" aria-label="Admin menu">
                @foreach ($nav as [$label, $routeName, $pattern])
                    @php
                        $exists = Route::has($routeName); // pages we have not built yet stay greyed out
                        $active = request()->routeIs($pattern);
                    @endphp
                    <a href="{{ $exists ? route($routeName) : '#' }}"
                       @unless ($exists) title="Coming soon" aria-disabled="true" @endunless
                       @class([
                           'block rounded-lg px-3 py-2 text-sm font-medium transition',
                           'bg-gold-500/10 text-gold-500' => $active,
                           'text-cream/70 hover:bg-white/5 hover:text-cream' => ! $active && $exists,
                           'cursor-not-allowed text-cream/30' => ! $exists,
                       ])>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-white/10 px-6 py-4">
                <a href="{{ url('/') }}" class="text-sm text-cream/60 hover:text-gold-500">&larr; View website</a>
            </div>
        </aside>

        {{-- Page area --}}
        <div class="min-w-0 flex-1">
            <main class="mx-auto max-w-6xl p-4 sm:p-8">

                <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <h1 class="text-3xl font-semibold">{{ $title }}</h1>
                    {{-- Optional buttons beside the title: <x-slot:actions>...</x-slot:actions> --}}
                    @isset($actions)
                        <div>{{ $actions }}</div>
                    @endisset
                </div>

                {{-- One-time message after saving, e.g. "City created." --}}
                @if (session('status'))
                    <div class="mb-6 rounded-lg border border-gold-500/40 bg-gold-500/10 px-4 py-3 text-sm text-gold-500" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                {{-- One-time problem message, e.g. "This city still has restaurants" --}}
                @if (session('error'))
                    <div class="mb-6 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-400" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
