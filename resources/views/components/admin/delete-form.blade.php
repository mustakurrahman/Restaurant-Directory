{{--
    <x-admin.delete-form :action="route('admin.cities.destroy', $city)" message="Delete this city?" />
    A Delete button that asks for confirmation before sending the request.
--}}
@props(['action', 'message' => 'Are you sure? This cannot be undone.'])

<form method="POST" action="{{ $action }}" onsubmit="return confirm(@js($message))" class="inline">
    @csrf
    @method('DELETE')
    <x-button type="submit" variant="danger" class="px-3 py-1.5">
        {{ $slot->isEmpty() ? 'Delete' : $slot }}
    </x-button>
</form>
