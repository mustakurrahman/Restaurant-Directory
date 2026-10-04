{{--
    <x-select name="city" label="City">
        <option value="">All cities</option>
        <option value="1" @selected(request('city') == 1)>New York</option>
    </x-select>
    Label, dropdown and validation error. You write the <option> tags and mark the chosen one with @selected.
--}}
@props(['name', 'label'])

@php($hasError = $errors->has($name))

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-cream">{{ $label }}</label>

    <select id="{{ $name }}" name="{{ $name }}"
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
            {{ $attributes->class([
                'w-full rounded-lg border bg-ink-950 px-4 py-2.5 text-cream',
                'focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500',
                'border-red-500' => $hasError,
                'border-white/20' => ! $hasError,
            ]) }}>
        {{ $slot }}
    </select>

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
    @enderror
</div>
