<x-admin.layout :title="'Edit '.$noun">
    <x-admin.name-slug-form :noun="$noun" :features="$features" method="PUT" :item="$item"
                            :action="route($routePrefix.'.update', $item)"
                            :cancel="route($routePrefix.'.index')" />
</x-admin.layout>
