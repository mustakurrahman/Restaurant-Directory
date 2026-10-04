<x-admin.layout title="Restaurants">
    <x-slot:actions>
        <x-button :href="route('admin.restaurants.create')">Add restaurant</x-button>
    </x-slot:actions>

    {{-- Filters: a GET form, so the choices live in the URL and survive paging --}}
    <x-card class="mb-6">
        <form method="GET" action="{{ route('admin.restaurants.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-4">
                <x-input name="q" label="Search" type="search" :value="request('q')" placeholder="Name or address" />
            </div>

            <x-select name="city" label="City">
                <option value="">All cities</option>
                @foreach ($cities as $city)
                    <option value="{{ $city->id }}" @selected(request('city') == $city->id)>{{ $city->name }}</option>
                @endforeach
            </x-select>

            <x-select name="status" label="Status">
                <option value="">Any status</option>
                <option value="published" @selected(request('status') === 'published')>Published</option>
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            </x-select>

            <div class="self-end pb-2.5">
                <x-checkbox name="featured" value="1" label="Featured only" :checked="request()->boolean('featured')" />
            </div>

            <div class="flex items-end gap-3">
                <x-button type="submit">Filter</x-button>
                <x-button variant="outline" :href="route('admin.restaurants.index')">Reset</x-button>
            </div>
        </form>
    </x-card>

    <p class="mb-3 text-sm text-cream/60">
        {{ $restaurants->total() }} {{ Str::plural('restaurant', $restaurants->total()) }}
    </p>

    <x-admin.table>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">Name</th>
                <th class="px-4 py-3 font-medium">City</th>
                <th class="px-4 py-3 font-medium">Cuisines</th>
                <th class="px-4 py-3 font-medium">Price</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium">Updated</th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
        </x-slot:head>

        @forelse ($restaurants as $restaurant)
            <tr>
                <td class="px-4 py-3">
                    <p class="font-medium">{{ $restaurant->name }}</p>
                    <p class="text-xs text-cream/50">{{ $restaurant->slug }}</p>
                </td>
                <td class="px-4 py-3">{{ $restaurant->city->name }}</td>
                <td class="px-4 py-3 text-cream/70">{{ $restaurant->cuisines->pluck('name')->join(', ') ?: '—' }}</td>
                <td class="px-4 py-3" title="Price range {{ $restaurant->price_range }} of 4">
                    {{-- Filled dollar signs = price range, faded ones = the rest of the scale --}}
                    <span class="text-gold-500">{{ str_repeat('$', $restaurant->price_range) }}</span><span class="text-cream/25">{{ str_repeat('$', 4 - $restaurant->price_range) }}</span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1">
                        <x-status-badge :status="$restaurant->status" />
                        @if ($restaurant->is_featured)
                            <x-badge variant="gold">Featured</x-badge>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-cream/60">{{ $restaurant->updated_at->diffForHumans() }}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-2">
                        <x-button variant="outline" class="px-3 py-1.5" :href="route('admin.restaurants.edit', $restaurant)">Edit</x-button>
                        <x-admin.delete-form :action="route('admin.restaurants.destroy', $restaurant)"
                                             :message="'Delete '.$restaurant->name.'? Its photos, opening hours and reviews are deleted too. This cannot be undone.'" />
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-cream/60">
                    No restaurants match your filters.
                    <a href="{{ route('admin.restaurants.index') }}" class="text-gold-500 underline">Reset filters</a>
                </td>
            </tr>
        @endforelse
    </x-admin.table>

    <div class="mt-6">
        {{ $restaurants->links() }}
    </div>

</x-admin.layout>
