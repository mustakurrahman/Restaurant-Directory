<x-admin.layout title="Add admin">
    <x-admin.user-form :action="route('admin.users.store')" :cancel="route('admin.users.index')" />
</x-admin.layout>
