<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Theme Test</title>

    {{-- @fonts loads Playfair Display + Inter; @vite loads our Tailwind CSS --}}
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="mx-auto max-w-3xl px-4 py-12 sm:py-20">

        <p class="text-sm font-medium uppercase tracking-widest text-gold-500">Theme test</p>

        {{-- Heading: should be Playfair Display (serif) --}}
        <h1 class="mt-3 text-4xl font-bold sm:text-6xl">Discover great restaurants</h1>

        {{-- Paragraph: should be Inter (clean sans-serif) --}}
        <p class="mt-4 text-lg text-cream/80">
            Search, filter and read about the best places to eat in your city.
            This paragraph uses the body font on a dark background.
        </p>

        {{-- Buttons --}}
        <div class="mt-8 flex flex-wrap gap-4">
            <a href="#" class="rounded-lg bg-gold-500 px-6 py-3 font-semibold text-ink-950 transition hover:bg-gold-600">
                Gold button
            </a>
            <a href="#" class="rounded-lg border border-gold-500 px-6 py-3 font-semibold text-gold-500 transition hover:bg-gold-500 hover:text-ink-950">
                Outlined button
            </a>
        </div>

        {{-- Charcoal card --}}
        <div class="mt-12 rounded-2xl border border-white/10 bg-ink-900 p-6 shadow-lg">
            <h2 class="text-2xl font-semibold text-gold-500">The Golden Fork</h2>
            <p class="mt-2 text-cream/70">Italian &middot; $$$ &middot; Downtown</p>
            <p class="mt-4 text-cream/80">
                A card on the <code>ink-900</code> charcoal color, sitting on the <code>ink-950</code> black page.
            </p>
        </div>

        {{-- Color swatches, to compare against the brand list --}}
        <div class="mt-12 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div class="rounded-lg bg-gold-500 p-4 text-sm font-medium text-ink-950">gold-500<br>#F59E0B</div>
            <div class="rounded-lg bg-gold-600 p-4 text-sm font-medium text-ink-950">gold-600<br>#D97706</div>
            <div class="rounded-lg border border-white/20 bg-ink-950 p-4 text-sm font-medium">ink-950<br>#0A0A0A</div>
            <div class="rounded-lg bg-ink-900 p-4 text-sm font-medium">ink-900<br>#1A1A1A</div>
            <div class="rounded-lg bg-cream p-4 text-sm font-medium text-ink-950">cream<br>#FFF8EB</div>
        </div>

    </main>
</body>
</html>
