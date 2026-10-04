<x-admin.layout :title="'Add '.$noun">
    <x-admin.name-slug-form :noun="$noun" :features="$features"
                            :action="route($routePrefix.'.store')"
                            :cancel="route($routePrefix.'.index')" />
</x-admin.layout>
