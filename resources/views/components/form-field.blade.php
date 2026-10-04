{{--
    Label, hint and error message around ANY control you place inside (a file picker, a radio group, a date field...):
    <x-form-field name="opens_at" label="Opening time" hint="24-hour clock">
        <input type="time" id="opens_at" name="opens_at" value="{{ old('opens_at') }}" class="...">
    </x-form-field>
    Give the control id="{{ name }}" so the label points at it. For the usual text field, textarea and
    dropdown use <x-input>, <x-textarea> and <x-select> instead: they already do all of this.
--}}
@props(['name', 'label', 'hint' => null])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-cream">{{ $label }}</label>

    {{ $slot }}

    @if ($hint && ! $errors->has($name))
        <p class="mt-1.5 text-xs text-cream/50">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
    @enderror
</div>
