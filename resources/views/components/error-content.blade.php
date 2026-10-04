{{--
    The centre of every error page:
    <x-error-content code="404" title="We can't find that page" message="..." />
    Without anything inside the tag it shows the default buttons (home, and restaurants when that page exists).
    Put your own buttons inside the tag to replace them.
--}}
@props(['code', 'title', 'message'])

<section class="relative isolate mx-auto flex max-w-3xl flex-col items-center px-4 py-24 text-center sm:py-32">
    {{-- Soft golden glow behind the number --}}
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_center,rgba(245,158,11,0.12),transparent_65%)]"></div>

    {{-- The number is decoration: the heading below carries the meaning for screen readers --}}
    <p class="font-display text-8xl font-bold text-gold-500 sm:text-9xl" aria-hidden="true">{{ $code }}</p>

    <h1 class="mt-4 text-3xl font-semibold sm:text-4xl">{{ $title }}</h1>
    <p class="mt-4 max-w-lg text-lg text-cream/70">{{ $message }}</p>

    <div class="mt-8 flex flex-wrap justify-center gap-3">
        @if ($slot->isEmpty())
            <x-button :href="url('/')" class="px-6 py-3">Back to the homepage</x-button>
            @if (Route::has('restaurants.index'))
                <x-button variant="outline" :href="route('restaurants.index')" class="px-6 py-3">Browse restaurants</x-button>
            @endif
        @else
            {{ $slot }}
        @endif
    </div>
</section>
