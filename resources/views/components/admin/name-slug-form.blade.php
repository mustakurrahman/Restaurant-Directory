{{--
    Add/edit form for anything that has a name and a slug (cities, cuisines, amenities).
    <x-admin.name-slug-form noun="city" :action="route('admin.cities.store')" :cancel="route('admin.cities.index')" />
    To edit, also pass :item="$city" method="PUT" and the update action.
    features: extra optional fields, as a list: ['description'] shows a description box (cities),
              ['icon'] shows an icon-name field (amenities).
--}}
@props(['action', 'cancel', 'noun', 'item' => null, 'method' => 'POST', 'features' => []])

<x-card class="max-w-xl">
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        {{-- Browsers only send GET/POST, so edits are sent as a hidden PUT --}}
        @if ($method !== 'POST')
            @method($method)
        @endif

        <x-input name="name" :label="ucfirst($noun).' name'" :value="$item?->name" required autofocus />

        <x-input name="slug" label="URL slug" :value="$item?->slug"
                 hint="The part used in the web address. Leave empty to create it from the name." />

        @if (in_array('description', $features, true))
            <x-textarea name="description" label="Description (optional)" :value="$item?->description" :rows="4" maxlength="1000"
                        :hint="'A short introduction to the '.$noun.'. Up to 1000 characters.'" />
        @endif

        @if (in_array('icon', $features, true))
            <x-input name="icon" label="Icon name (optional)" :value="$item?->icon" maxlength="50" placeholder="wifi"
                     hint="A short lowercase name for the icon, such as wifi, parking or outdoor-seating." />
        @endif

        <div class="flex flex-wrap gap-3 pt-2">
            <x-button type="submit">Save {{ $noun }}</x-button>
            <x-button variant="outline" :href="$cancel">Cancel</x-button>
        </div>
    </form>
</x-card>
