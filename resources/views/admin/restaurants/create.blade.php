<x-admin.layout title="Add restaurant">
    {{-- Shown when the form was opened from a visitor's suggestion --}}
    @if ($submission)
        <div class="mb-6 max-w-3xl rounded-lg border border-gold-500/40 bg-gold-500/10 px-4 py-3 text-sm text-gold-500" role="status">
            Filled in from a suggestion by {{ $submission->submitter_name }} ({{ $submission->submitter_email }}). Check everything, then save.
            It is saved as a draft, so nothing is public until you publish it.
            @foreach ($notes as $note)
                <p class="mt-2 text-cream">{{ $note }}</p>
            @endforeach
        </div>
    @endif

    <x-admin.restaurant-form :restaurant="$restaurant"
                             :action="route('admin.restaurants.store')"
                             :cancel="route('admin.restaurants.index')"
                             :cities="$cities" :cuisines="$cuisines" :amenities="$amenities"
                             :from-submission="$submission?->id" :prefill-cuisines="$prefillCuisines" />

    <p class="mt-6 max-w-3xl text-sm text-cream/60">
        Opening hours and photos are added after saving: open the restaurant from the list and click Edit.
    </p>
</x-admin.layout>
