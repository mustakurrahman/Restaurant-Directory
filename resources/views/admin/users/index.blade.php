{{-- The admin accounts: everyone who can sign in to /admin (see Admin\UserController) --}}
<x-admin.layout title="Users">
    <x-slot:actions>
        <x-button :href="route('admin.users.create')">Add admin</x-button>
    </x-slot:actions>

    <p class="mb-6 max-w-2xl text-sm text-cream/70">These are the people who can sign in to this admin panel. Visitors to the website do not have accounts.</p>

    {{-- Search: a GET form, so the search word lives in the address and survives paging --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 flex max-w-xl flex-wrap items-end gap-3">
        <div class="min-w-48 flex-1">
            <x-input name="q" label="Search users" type="search" :value="$search" placeholder="Name or email" />
        </div>
        <x-button type="submit">Search</x-button>
        @if ($search !== '')
            <x-button variant="outline" :href="route('admin.users.index')">Clear</x-button>
        @endif
    </form>

    <p class="mb-3 text-sm text-cream/60">
        {{ $users->total() }} {{ $users->total() === 1 ? 'admin' : 'admins' }}@if ($search !== '') matching “{{ $search }}”@endif
    </p>

    <x-admin.table>
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 font-medium">Name</th>
                <th class="px-4 py-3 font-medium">Email</th>
                <th class="px-4 py-3 font-medium">Added</th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
        </x-slot:head>

        @forelse ($users as $user)
            <tr>
                <td class="px-4 py-3 font-medium">
                    {{ $user->name }}
                    @if ($user->is_owner)
                        <x-badge variant="green" class="ml-2">Owner</x-badge>
                    @endif
                    @if ($user->id === $currentId)
                        <x-badge variant="gold" class="ml-2">You</x-badge>
                    @endif
                </td>
                <td class="px-4 py-3 text-cream/80">{{ $user->email }}</td>
                <td class="px-4 py-3 text-cream/60">{{ $user->created_at?->format('j M Y') }}</td>
                <td class="px-4 py-3 text-right">
                    {{-- Only the Owner can edit the Owner account --}}
                    @if (! $user->is_owner || $viewerIsOwner)
                        <x-button variant="outline" :href="route('admin.users.edit', $user)" class="px-3 py-1.5">Edit</x-button>
                    @endif
                    {{-- No Delete button for the Owner (never deletable) or for yourself (it would lock you out) --}}
                    @if (! $user->is_owner && $user->id !== $currentId)
                        <x-admin.delete-form :action="route('admin.users.destroy', $user)" :message="'Delete the admin '.$user->email.'? They will no longer be able to sign in.'" />
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-cream/60">No admins match your search.</td>
            </tr>
        @endforelse
    </x-admin.table>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
</x-admin.layout>
