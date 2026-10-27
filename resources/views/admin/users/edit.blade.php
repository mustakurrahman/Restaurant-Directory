<x-admin.layout title="Edit admin">
    <x-admin.user-form :user="$user" method="PUT"
                       :action="route('admin.users.update', $user)"
                       :cancel="route('admin.users.index')" />
</x-admin.layout>
