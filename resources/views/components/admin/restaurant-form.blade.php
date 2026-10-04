{{--
    Add/edit form for a restaurant (photos and opening hours are separate steps).
    <x-admin.restaurant-form :restaurant="$restaurant" :action="route('admin.restaurants.store')"
        :cancel="route('admin.restaurants.index')" :cities="$cities" :cuisines="$cuisines" :amenities="$amenities" />
    For editing, also pass method="PUT" and the update action.
--}}
@props(['restaurant', 'action', 'cancel', 'cities', 'cuisines', 'amenities', 'method' => 'POST'])

@php
    // After a failed save, show what was typed/ticked; otherwise show the saved values.
    // (old() alone is not enough: an unticked box sends nothing, so old() would fall back to the saved value.)
    $hasOld = session()->hasOldInput();
    $selectedCuisines = $hasOld
        ? array_map('intval', (array) old('cuisines', []))
        : ($restaurant->exists ? $restaurant->cuisines->pluck('id')->all() : []);
    $selectedAmenities = $hasOld
        ? array_map('intval', (array) old('amenities', []))
        : ($restaurant->exists ? $restaurant->amenities->pluck('id')->all() : []);

    // 40.7128000 from the database -> 40.7128 in the field
    $latitude = $restaurant->latitude !== null ? (float) $restaurant->latitude : null;
    $longitude = $restaurant->longitude !== null ? (float) $restaurant->longitude : null;
@endphp

<form method="POST" action="{{ $action }}" class="max-w-3xl space-y-6">
    @csrf
    {{-- Browsers only send GET/POST, so edits are sent as a hidden PUT --}}
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- Basics --}}
    <x-card class="space-y-5">
        <h2 class="text-xl font-semibold text-gold-500">Basics</h2>

        <x-input name="name" label="Restaurant name" :value="$restaurant->name" required autofocus />

        <x-input name="slug" label="URL slug" :value="$restaurant->slug"
                 hint="The part used in the web address. Leave empty to create it from the name." />

        <x-select name="city_id" label="City" required>
            <option value="">Choose a city</option>
            @foreach ($cities as $city)
                <option value="{{ $city->id }}" @selected(old('city_id', $restaurant->city_id) == $city->id)>{{ $city->name }}</option>
            @endforeach
        </x-select>

        <x-input name="address" label="Address" :value="$restaurant->address" required />

        <x-textarea name="description" label="Description" :value="$restaurant->description" :rows="6"
                    hint="Shown on the restaurant page. Blank lines start a new paragraph." />
    </x-card>

    {{-- Contact --}}
    <x-card class="space-y-5">
        <h2 class="text-xl font-semibold text-gold-500">Contact</h2>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="phone" label="Phone" type="tel" :value="$restaurant->phone" />
            <x-input name="email" label="Email" type="email" :value="$restaurant->email" />
        </div>

        <x-input name="website" label="Website" type="url" :value="$restaurant->website"
                 placeholder="https://" hint="Include the https:// part." />
    </x-card>

    {{-- Details --}}
    <x-card class="space-y-5">
        <h2 class="text-xl font-semibold text-gold-500">Details</h2>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-select name="price_range" label="Price range" required>
                @foreach ([1 => '$ – Budget', 2 => '$$ – Moderate', 3 => '$$$ – Upscale', 4 => '$$$$ – Fine dining'] as $value => $text)
                    <option value="{{ $value }}" @selected((int) old('price_range', $restaurant->price_range) === $value)>{{ $text }}</option>
                @endforeach
            </x-select>

            <x-select name="status" label="Status" required>
                <option value="draft" @selected(old('status', $restaurant->status) === 'draft')>Draft (hidden from visitors)</option>
                <option value="published" @selected(old('status', $restaurant->status) === 'published')>Published (visible to everyone)</option>
            </x-select>
        </div>

        {{-- The hidden 0 is sent when the box is unticked, so old() knows it was unticked --}}
        <input type="hidden" name="is_featured" value="0">
        <x-checkbox name="is_featured" value="1" label="Featured on the homepage"
                    :checked="(bool) old('is_featured', $restaurant->is_featured)" />
    </x-card>

    {{-- Location --}}
    <x-card class="space-y-5">
        <h2 class="text-xl font-semibold text-gold-500">Map location</h2>
        <p class="-mt-2 text-sm text-cream/60">
            Optional. In Google Maps, right-click the restaurant and click the numbers shown to copy them
            (latitude first, then longitude).
        </p>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-input name="latitude" label="Latitude" type="number" step="any" :value="$latitude" placeholder="e.g. 40.7128" />
            <x-input name="longitude" label="Longitude" type="number" step="any" :value="$longitude" placeholder="e.g. -74.0060" />
        </div>
    </x-card>

    {{-- Categories --}}
    <x-card class="space-y-6">
        <h2 class="text-xl font-semibold text-gold-500">Categories</h2>

        <fieldset>
            <legend class="mb-2 text-sm font-medium text-cream">Cuisines</legend>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cuisines as $cuisine)
                    <x-checkbox name="cuisines[]" :value="$cuisine->id" :label="$cuisine->name"
                                :checked="in_array($cuisine->id, $selectedCuisines, true)" />
                @endforeach
            </div>
            @error('cuisines.*') <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p> @enderror
        </fieldset>

        <fieldset>
            <legend class="mb-2 text-sm font-medium text-cream">Amenities</legend>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($amenities as $amenity)
                    <x-checkbox name="amenities[]" :value="$amenity->id" :label="$amenity->name"
                                :checked="in_array($amenity->id, $selectedAmenities, true)" />
                @endforeach
            </div>
            @error('amenities.*') <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p> @enderror
        </fieldset>
    </x-card>

    {{-- SEO --}}
    <x-card class="space-y-5">
        <h2 class="text-xl font-semibold text-gold-500">Search engines (SEO)</h2>
        <p class="-mt-2 text-sm text-cream/60">Optional. What Google shows for this restaurant. Leave empty to use sensible defaults.</p>

        <x-input name="meta_title" label="Meta title" :value="$restaurant->meta_title"
                 hint="Aim for 60 characters or fewer." />
        <x-textarea name="meta_description" label="Meta description" :value="$restaurant->meta_description" :rows="3"
                    hint="Aim for 160 characters or fewer." />
    </x-card>

    <div class="flex flex-wrap gap-3">
        <x-button type="submit">Save restaurant</x-button>
        <x-button variant="outline" :href="$cancel">Cancel</x-button>
    </div>
</form>
