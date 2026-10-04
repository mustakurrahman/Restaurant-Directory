{{--
    <x-textarea name="description" label="Description" :value="$restaurant->description" :rows="6" />
    Label, multi-line field, hint and validation error. Refills what was typed after a failed save (old()).
--}}
@props(['name', 'label', 'value' => null, 'hint' => null, 'rows' => 4])

@php($hasError = $errors->has($name))

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-cream">{{ $label }}</label>

    {{-- No spaces between the tags: anything inside a textarea becomes part of its text --}}
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
              @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
              {{ $attributes->class([
                  'w-full rounded-lg border bg-ink-950 px-4 py-2.5 text-cream placeholder:text-cream/40',
                  'focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500',
                  'border-red-500' => $hasError,
                  'border-white/20' => ! $hasError,
              ]) }}>{{ old($name, $value) }}</textarea>

    @if ($hint && ! $hasError)
        <p class="mt-1.5 text-xs text-cream/50">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
    @enderror
</div>
