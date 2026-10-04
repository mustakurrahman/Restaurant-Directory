{{-- TEMPORARY preview page: delete this file and its route in routes/web.php after checking --}}
<x-admin.layout title="Components preview">

    <h2 class="mb-3 text-lg font-semibold text-cream/80">Buttons</h2>
    <div class="flex flex-wrap items-center gap-3">
        <x-button>Primary</x-button>
        <x-button variant="outline">Outline</x-button>
        <x-button variant="danger">Danger</x-button>
        <x-button href="{{ route('admin.dashboard') }}" variant="outline">Link button</x-button>
    </div>

    <h2 class="mb-3 mt-10 text-lg font-semibold text-cream/80">Badges</h2>
    <div class="flex flex-wrap gap-2">
        <x-badge>Gray</x-badge>
        <x-badge variant="gold">Featured</x-badge>
        <x-badge variant="green">Published</x-badge>
        <x-badge variant="red">Draft</x-badge>
    </div>

    <h2 class="mb-3 mt-10 text-lg font-semibold text-cream/80">Inputs (inside a card)</h2>
    <x-card class="max-w-xl space-y-5">
        <x-input name="city_name" label="City name" value="New York" hint="Shown to visitors." />
        {{-- This one shows the error style; the error is added by the route --}}
        <x-input name="name_error" label="Field with an error" />
        <div class="flex gap-3">
            <x-button type="button">Save</x-button>
            <x-admin.delete-form action="#" message="This is only a preview. Nothing will be deleted." />
        </div>
    </x-card>

    <h2 class="mb-3 mt-10 text-lg font-semibold text-cream/80">Table</h2>
    <x-admin.table>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">Name</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
        </x-slot:head>
        <tr>
            <td class="px-4 py-3 font-medium">Trattoria Bella Luna</td>
            <td class="px-4 py-3"><x-badge variant="green">Published</x-badge></td>
            <td class="px-4 py-3 text-right"><x-button variant="outline" class="px-3 py-1.5" href="#">Edit</x-button></td>
        </tr>
        <tr>
            <td class="px-4 py-3 font-medium">Pacific Catch</td>
            <td class="px-4 py-3"><x-badge variant="red">Draft</x-badge></td>
            <td class="px-4 py-3 text-right"><x-button variant="outline" class="px-3 py-1.5" href="#">Edit</x-button></td>
        </tr>
    </x-admin.table>

</x-admin.layout>
