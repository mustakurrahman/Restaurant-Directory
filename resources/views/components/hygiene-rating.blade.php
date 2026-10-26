{{--
    Food hygiene rating badge, drawn in our own colours (not the official artwork):
    <x-hygiene-rating :rating="$restaurant->hygiene_rating" />
    Shows nothing when the rating is empty (null).
--}}
@props(['rating'])

@php
    // Words used by the UK Food Standards Agency scheme
    $labels = [
        5 => 'Very good', 4 => 'Good', 3 => 'Generally satisfactory',
        2 => 'Improvement necessary', 1 => 'Major improvement necessary', 0 => 'Urgent improvement necessary',
    ];
@endphp

@if ($rating !== null && isset($labels[$rating]))
    <div {{ $attributes->class('flex items-center gap-4') }}>
        <ol class="flex items-center gap-1.5" aria-hidden="true">
            @foreach (range(0, 5) as $step)
                <li @class([
                    'flex size-7 items-center justify-center rounded-full border text-xs font-semibold',
                    'border-gold-500 bg-gold-500 text-ink-950' => $step === $rating,
                    'border-white/20 text-cream/40' => $step !== $rating,
                ])>{{ $step }}</li>
            @endforeach
        </ol>
        <p class="text-sm">
            <span class="font-semibold text-gold-500">{{ $labels[$rating] }}</span>
            <span class="sr-only">Food hygiene rating {{ $rating }} out of 5</span>
        </p>
    </div>
@endif
