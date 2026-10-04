{{-- Charcoal panel on the black page: <x-card> ...content... </x-card> --}}
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-white/10 bg-ink-900 p-6']) }}>
    {{ $slot }}
</div>
