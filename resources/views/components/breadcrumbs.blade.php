{{--
    Trail shown above the page title, and the same trail described to Google (JSON-LD BreadcrumbList):
    <x-breadcrumbs :items="[['Home', route('home')], ['Restaurants', route('restaurants.index')], ['Chez Marie', null]]" />
    Each item is [label, address]. The last one is the current page and has no address (null).
--}}
@props(['items'])

<nav aria-label="Breadcrumb" class="text-sm">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-cream/60">
        @foreach ($items as [$label, $url])
            <li class="flex items-center gap-2">
                @if ($url)
                    <a href="{{ $url }}" class="hover:text-gold-500">{{ $label }}</a>
                @else
                    <span aria-current="page" class="text-cream">{{ $label }}</span>
                @endif

                @unless ($loop->last)
                    <span aria-hidden="true">/</span>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>

@push('jsonld')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($items)->values()->map(fn ($item, $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $item[0],
            'item' => $item[1] ?? url()->current(),
        ])->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush
