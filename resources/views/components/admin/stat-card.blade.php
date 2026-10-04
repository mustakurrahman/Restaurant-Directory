{{-- A number with a label: <x-admin.stat-card label="Cities" :value="5" hint="optional small text" :highlight="true" /> --}}
@props(['label', 'value', 'hint' => null, 'highlight' => false])

<div @class([
    'rounded-2xl border p-5',
    'border-gold-500/40 bg-gold-500/5' => $highlight,
    'border-white/10 bg-ink-900' => ! $highlight,
])>
    <p class="text-sm text-cream/60">{{ $label }}</p>
    <p @class([
        'mt-2 font-display text-4xl font-semibold',
        'text-gold-500' => $highlight,
        'text-cream' => ! $highlight,
    ])>{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-cream/50">{{ $hint }}</p>
    @endif
</div>
