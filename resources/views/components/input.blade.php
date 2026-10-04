{{--
    <x-input name="name" label="City name" :value="$city->name" hint="Shown to visitors" />
    Label, field, hint and validation error in one tag. After a failed save it
    refills what the user typed (old()) and shows the error message under the field.
--}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])

@php($hasError = $errors->has($name))

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-cream">{{ $label }}</label>

    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
           @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
           {{ $attributes->class([
               'w-full rounded-lg border bg-ink-950 px-4 py-2.5 text-cream placeholder:text-cream/40',
               'focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500',
               'border-red-500' => $hasError,
               'border-white/20' => ! $hasError,
           ]) }}>

    @if ($hint && ! $hasError)
        <p class="mt-1.5 text-xs text-cream/50">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
    @enderror
</div>
