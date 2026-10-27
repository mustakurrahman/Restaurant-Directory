{{--
    Add/edit form for an admin account.
    <x-admin.user-form :action="route('admin.users.store')" :cancel="route('admin.users.index')" />
    To edit, also pass :user="$user" method="PUT" and the update action.
--}}
@props(['action', 'cancel', 'user' => null, 'method' => 'POST'])

<x-card class="max-w-xl">
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        {{-- Browsers only send GET/POST, so edits are sent as a hidden PUT --}}
        @if ($method !== 'POST')
            @method($method)
        @endif

        <x-input name="name" label="Name" :value="$user?->name" maxlength="100" required autofocus />
        <x-input name="email" type="email" label="Email (used to sign in)" :value="$user?->email" autocomplete="off" required />

        <x-input name="password" type="password" :label="$user ? 'New password (optional)' : 'Password'" autocomplete="new-password"
                 :hint="$user ? 'Leave empty to keep the current password. Otherwise at least 12 characters.' : 'At least 12 characters. A few unrelated words and a number work well.'"
                 :required="! $user" />
        <x-input name="password_confirmation" type="password" label="Type the password again" autocomplete="new-password" :required="! $user" />

        <div class="flex flex-wrap gap-3 pt-2">
            <x-button type="submit">Save admin</x-button>
            <x-button variant="outline" :href="$cancel">Cancel</x-button>
        </div>
    </form>
</x-card>
