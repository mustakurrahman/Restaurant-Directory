{{-- All cities or all cuisines (only those with a published restaurant). Used by /cities and /cuisines --}}
@php
    $word = ucfirst($plural); // Cities / Cuisines
@endphp

<x-layout :title="$pageTitle"
          :description="'Browse '.$places->count().' '.Str::plural($kind, $places->count()).' with restaurants in the directory. '.$intro">

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <x-breadcrumbs :items="[['Home', route('home')], [$word, null]]" />

        <h1 class="mt-4 text-4xl font-bold">{{ $pageTitle }}</h1>
        <p class="mt-2 max-w-2xl text-cream/70">{{ $intro }}</p>

        @if ($places->isEmpty())
            <x-card class="mt-8 py-12 text-center">
                <p class="font-display text-2xl font-semibold">No {{ $plural }} yet</p>
                <p class="mx-auto mt-2 max-w-md text-cream/70">Please check back soon.</p>
            </x-card>
        @else
            <ul class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($places as $place)
                    <li>
                        <a href="{{ route("{$plural}.show", $place) }}"
                           class="block rounded-2xl border border-white/10 bg-ink-900 p-5 transition hover:-translate-y-0.5 hover:border-gold-500/50">
                            <span class="block font-display text-xl font-semibold">{{ $place->name }}</span>
                            <span class="mt-1 block text-sm text-cream/60">
                                {{ $place->published_restaurants_count }} {{ Str::plural('restaurant', $place->published_restaurants_count) }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layout>
