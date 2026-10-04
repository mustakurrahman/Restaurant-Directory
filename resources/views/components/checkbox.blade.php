{{--
    <x-checkbox name="featured" value="1" label="Featured only" :checked="request()->boolean('featured')" />
    For a group of boxes use an array name: name="cuisines[]" with a different value for each.
    The caller decides :checked, because "ticked" depends on old input vs saved data.
--}}
@props(['name', 'value', 'label', 'checked' => false])

<label class="flex cursor-pointer items-center gap-2 text-sm text-cream">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked)
           {{ $attributes->class(['size-4 rounded border-white/20 bg-ink-950 accent-gold-500']) }}>
    {{ $label }}
</label>
