{{--
    A link when there is an address, a plain box when there is not:
    <x-maybe-link :href="Route::has('cities.show') ? route('cities.show', $city) : null" class="block ...">...</x-maybe-link>
    Used for pages that arrive in a later sprint, so nothing on the site is ever a dead link.
--}}
@props(['href' => null])

@if ($href)
    <a href="{{ $href }}" {{ $attributes }}>{{ $slot }}</a>
@else
    <div {{ $attributes }}>{{ $slot }}</div>
@endif
