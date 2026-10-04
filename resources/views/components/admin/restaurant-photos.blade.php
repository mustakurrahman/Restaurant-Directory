{{--
    Cover photo and gallery for an existing restaurant: <x-admin.restaurant-photos :restaurant="$restaurant" />
    Needs $restaurant->images loaded. Each action is its own small form (forms cannot be nested).
--}}
@props(['restaurant'])

<section id="photos" class="max-w-3xl scroll-mt-6 space-y-6">
    <h2 class="text-2xl font-semibold">Photos</h2>

    {{-- Cover --}}
    <x-card class="space-y-4">
        <h3 class="text-xl font-semibold text-gold-500">Cover photo</h3>
        <p class="text-sm text-cream/60">The main picture, shown on restaurant cards and at the top of its page.</p>

        @if ($restaurant->cover_url)
            <img src="{{ $restaurant->cover_url }}" alt="Cover photo of {{ $restaurant->name }}"
                 class="aspect-video w-full max-w-sm rounded-lg border border-white/10 object-cover">
        @elseif ($restaurant->cover_image)
            <p class="rounded-lg border border-dashed border-white/20 p-4 text-sm text-cream/60">
                The saved cover ({{ $restaurant->cover_image }}) is sample data and has no file yet.
                Upload a real photo below to replace it.
            </p>
        @else
            <p class="rounded-lg border border-dashed border-white/20 p-4 text-sm text-cream/60">No cover photo yet.</p>
        @endif

        <form method="POST" action="{{ route('admin.restaurants.cover.store', $restaurant) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <x-file-input name="cover" :label="$restaurant->cover_image ? 'Replace cover photo' : 'Upload cover photo'"
                          hint="JPG, PNG or WebP, up to 3 MB." />
            <x-button type="submit">Upload cover</x-button>
        </form>

        @if ($restaurant->cover_image)
            <x-admin.delete-form :action="route('admin.restaurants.cover.destroy', $restaurant)"
                                 message="Remove the cover photo?">Remove cover</x-admin.delete-form>
        @endif
    </x-card>

    {{-- Gallery --}}
    <x-card class="space-y-4">
        <h3 class="text-xl font-semibold text-gold-500">Gallery</h3>
        <p class="text-sm text-cream/60">
            More pictures for the restaurant page. The description (alt text) helps blind visitors and Google Images.
        </p>

        @error('alt_text')
            <p class="text-sm text-red-400">The description can be at most 255 characters.</p>
        @enderror

        @if ($restaurant->images->isEmpty())
            <p class="rounded-lg border border-dashed border-white/20 p-4 text-sm text-cream/60">No gallery photos yet.</p>
        @else
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ($restaurant->images as $image)
                    <li class="overflow-hidden rounded-xl border border-white/10 bg-ink-950">
                        @if ($image->url)
                            <img src="{{ $image->url }}" alt="{{ $image->alt_text }}" loading="lazy" class="aspect-video w-full object-cover">
                        @else
                            <div class="flex aspect-video items-center justify-center p-3 text-center text-xs text-cream/50">
                                Sample data: no file yet<br>{{ $image->path }}
                            </div>
                        @endif

                        <div class="space-y-2 p-3">
                            <form method="POST" action="{{ route('admin.restaurants.images.update', [$restaurant, $image]) }}" class="flex gap-2">
                                @csrf
                                @method('PATCH')
                                <label for="alt-{{ $image->id }}" class="sr-only">Photo description</label>
                                <input id="alt-{{ $image->id }}" name="alt_text" maxlength="255" value="{{ $image->alt_text }}"
                                       placeholder="Describe the photo"
                                       class="min-w-0 flex-1 rounded-lg border border-white/20 bg-ink-900 px-3 py-1.5 text-sm text-cream placeholder:text-cream/40 focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                                <x-button type="submit" variant="outline" class="px-3 py-1.5">Save</x-button>
                            </form>

                            <x-admin.delete-form :action="route('admin.restaurants.images.destroy', [$restaurant, $image])"
                                                 message="Delete this photo? This cannot be undone." />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.restaurants.images.store', $restaurant) }}" enctype="multipart/form-data" class="space-y-3 border-t border-white/10 pt-4">
            @csrf
            <x-file-input name="images" label="Add photos" multiple
                          hint="Pick up to 10 at once. JPG, PNG or WebP, up to 3 MB each." />
            <x-button type="submit">Upload photos</x-button>
        </form>
    </x-card>
</section>
