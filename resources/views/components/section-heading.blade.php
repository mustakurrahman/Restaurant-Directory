{{--
    Heading row of a page section, with an optional "view all" link on the right:
    <x-section-heading id="cities-heading" title="Browse by city" :href="route('cities.index')" link="All cities" />
    Give the section aria-labelledby="the same id" so screen readers announce it.
--}}
@props(['id', 'title', 'href' => null, 'link' => 'View all'])

<div class="flex flex-wrap items-end justify-between gap-3">
    <h2 id="{{ $id }}" class="text-3xl font-semibold">{{ $title }}</h2>

    @if ($href)
        <a href="{{ $href }}" class="text-sm font-medium text-gold-500 hover:text-gold-600">{{ $link }} &rarr;</a>
    @endif
</div>
