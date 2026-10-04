{{--
    <x-button>Save</x-button>                          gold button
    <x-button variant="outline" href="/cities">Back</x-button>   outlined link
    <x-button variant="danger" type="submit">Delete</x-button>
    Extra classes can be added: <x-button class="w-full">
--}}
@props(['variant' => 'primary', 'href' => null, 'type' => 'button'])

@php
    $classes = 'inline-flex items-center justify-center rounded-lg px-5 py-2.5 text-sm font-semibold transition '
        .'focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink-950 '
        .match ($variant) {
            'outline' => 'border border-gold-500 text-gold-500 hover:bg-gold-500 hover:text-ink-950',
            'danger' => 'bg-red-600 text-white hover:bg-red-700',
            default => 'bg-gold-500 text-ink-950 hover:bg-gold-600', // primary
        };
@endphp

{{-- With href it becomes a link that looks like a button --}}
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
