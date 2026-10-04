{{--
    <x-file-input name="cover" label="Upload cover photo" hint="JPG, PNG or WebP, up to 3 MB." />
    <x-file-input name="images" label="Add photos" multiple />   (sends images[], lets you pick several)
    Shows every validation error for the field, including per-file ones (images.0, images.1 ...).
    The parent <form> needs enctype="multipart/form-data" or the file is not sent.
--}}
@props(['name', 'label', 'hint' => null, 'multiple' => false])

@php
    // Errors can be on "images" itself or on one file ("images.0"); gather both, without repeats
    $messages = collect($errors->get($name))
        ->merge(collect($errors->get($name.'.*'))->flatten())
        ->unique()
        ->values();
@endphp

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-cream">{{ $label }}</label>

    <input id="{{ $name }}" type="file" name="{{ $name }}{{ $multiple ? '[]' : '' }}"
           accept="image/jpeg,image/png,image/webp" @if ($multiple) multiple @endif
           @if ($messages->isNotEmpty()) aria-invalid="true" @endif
           {{ $attributes->class([
               'block w-full cursor-pointer rounded-lg border bg-ink-950 text-sm text-cream/70',
               'file:mr-4 file:cursor-pointer file:border-0 file:bg-gold-500 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-ink-950 hover:file:bg-gold-600',
               'border-red-500' => $messages->isNotEmpty(),
               'border-white/20' => $messages->isEmpty(),
           ]) }}>

    @if ($hint && $messages->isEmpty())
        <p class="mt-1.5 text-xs text-cream/50">{{ $hint }}</p>
    @endif

    @foreach ($messages as $message)
        <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
    @endforeach
</div>
