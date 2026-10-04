{{--
    Frame for every public page:
    <x-layout title="Italian restaurants in Chicago" description="Browse 12 Italian restaurants...">
        ...page content...
        @push('jsonld') <script type="application/ld+json">{...}</script> @endpush   (structured data for Google)
    </x-layout>

    title        page-specific part; the site name is added after it
    description  meta description (aim for about 155 characters; make it unique per page)
    canonical    the one official address of this page (defaults to the current address without ?filters)
    image        picture for social sharing (full address)
    type         Open Graph type: website, article ...
    noindex      keeps the page out of Google (use for search results and thank-you pages)
--}}
@props(['title', 'description' => null, 'canonical' => null, 'image' => null, 'type' => 'website', 'noindex' => false])

@php
    $siteName = config('app.name');
    $description = $description ?: 'Discover and compare the best restaurants by city, cuisine and price. Read reviews, see opening hours and find your next great meal.';
    $canonical = $canonical ?: url()->current(); // url()->current() has no ?query string
    $fullTitle = $title.' | '.$siteName;

    // Social sharing picture: the page's own, otherwise a default one if it exists
    $image = $image ?: (file_exists(public_path('images/og-default.png')) ? asset('images/og-default.png') : null);

    // [label, route name, pattern marking the item as current]. Pages that do not exist yet are left out
    // automatically, then appear as soon as their route is added in a later sprint.
    $nav = collect([
        ['Home', 'home', 'home'],
        ['Restaurants', 'restaurants.index', 'restaurants.*'],
        ['Cities', 'cities.index', 'cities.*'],
        ['Cuisines', 'cuisines.index', 'cuisines.*'],
        ['Contact', 'contact.create', 'contact.*'],
    ])->filter(fn ($item) => Route::has($item[1]));

    $submitUrl = Route::has('submit.create') ? route('submit.create') : null;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A0A0A">

    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta name="robots" content="{{ $noindex ? 'noindex, follow' : 'index, follow' }}">

    {{-- Open Graph: the preview shown when the page is shared on Facebook, WhatsApp, LinkedIn... --}}
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
    @endif
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $fullTitle }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if ($image)
        <meta name="twitter:image" content="{{ $image }}">
    @endif

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-gold-500 focus:px-4 focus:py-2 focus:font-semibold focus:text-ink-950">
        Skip to content
    </a>

    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b border-white/10 bg-ink-950/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="text-xl" aria-label="{{ $siteName }} home">
                <x-logo />
            </a>

            {{-- Desktop menu --}}
            <nav class="hidden items-center gap-1 md:flex" aria-label="Main menu">
                @foreach ($nav as [$label, $routeName, $pattern])
                    <a href="{{ route($routeName) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif
                       @class([
                           'rounded-lg px-3 py-2 text-sm font-medium transition',
                           'text-gold-500' => request()->routeIs($pattern),
                           'text-cream/80 hover:text-gold-500' => ! request()->routeIs($pattern),
                       ])>{{ $label }}</a>
                @endforeach

                @if ($submitUrl)
                    <x-button variant="outline" :href="$submitUrl" class="ml-3 px-4 py-2">Submit a restaurant</x-button>
                @endif
            </nav>

            {{-- Phone menu button --}}
            <button type="button" data-toggle="mobile-menu" aria-controls="mobile-menu" aria-expanded="false"
                    class="rounded-lg border border-white/20 px-3 py-2 text-sm font-medium text-cream hover:border-gold-500 md:hidden">
                Menu
            </button>
        </div>

        {{-- Phone menu: hidden until the button is tapped --}}
        <nav id="mobile-menu" class="hidden border-t border-white/10 px-4 py-3 md:hidden" aria-label="Main menu">
            <div class="mx-auto flex max-w-6xl flex-col gap-1">
                @foreach ($nav as [$label, $routeName, $pattern])
                    <a href="{{ route($routeName) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif
                       @class([
                           'rounded-lg px-3 py-2.5 text-base font-medium',
                           'bg-gold-500/10 text-gold-500' => request()->routeIs($pattern),
                           'text-cream/80 hover:bg-white/5' => ! request()->routeIs($pattern),
                       ])>{{ $label }}</a>
                @endforeach

                @if ($submitUrl)
                    <x-button :href="$submitUrl" class="mt-2">Submit a restaurant</x-button>
                @endif
            </div>
        </nav>
    </header>

    {{-- Page --}}
    <main id="main" class="flex-1">
        {{-- One-time message after a form, e.g. "Thank you, your review is awaiting approval" --}}
        @if (session('status'))
            <div class="mx-auto max-w-6xl px-4 pt-6 sm:px-6">
                <div class="rounded-lg border border-gold-500/40 bg-gold-500/10 px-4 py-3 text-sm text-gold-500" role="status">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="mt-16 border-t border-white/10 bg-ink-900">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-3">
            <div>
                <a href="{{ route('home') }}" class="text-xl"><x-logo /></a>
                <p class="mt-4 max-w-xs text-sm text-cream/60">
                    Find great places to eat, compare them by cuisine and price, and read honest reviews.
                </p>
            </div>

            <div>
                <h2 class="font-display text-lg font-semibold">Explore</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($nav as [$label, $routeName])
                        <li><a href="{{ route($routeName) }}" class="text-cream/70 hover:text-gold-500">{{ $label }}</a></li>
                    @endforeach
                    @if ($submitUrl)
                        <li><a href="{{ $submitUrl }}" class="text-cream/70 hover:text-gold-500">Submit a restaurant</a></li>
                    @endif
                </ul>
            </div>

            <div>
                <h2 class="font-display text-lg font-semibold">Own a restaurant?</h2>
                <p class="mt-4 text-sm text-cream/60">
                    Tell us about it and we will review it for the directory.
                </p>
                @if ($submitUrl)
                    <x-button variant="outline" :href="$submitUrl" class="mt-4">Submit your restaurant</x-button>
                @endif
            </div>
        </div>

        <div class="border-t border-white/10 px-4 py-5 text-center text-xs text-cream/50 sm:px-6">
            &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
        </div>
    </footer>

    {{-- Structured data (JSON-LD) pushed by pages; placed last because it is read by search engines, not shown --}}
    @stack('jsonld')
</body>
</html>
