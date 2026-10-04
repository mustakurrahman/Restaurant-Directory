{{-- Small status label: <x-badge variant="green">Published</x-badge> (gold, green, red or gray) --}}
@props(['variant' => 'gray'])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
    match ($variant) {
        'gold' => 'bg-gold-500/15 text-gold-500',
        'green' => 'bg-green-500/15 text-green-400',
        'red' => 'bg-red-500/15 text-red-400',
        default => 'bg-white/10 text-cream/70',
    },
]) }}>{{ $slot }}</span>
