{{-- Page not found. Also what visitors get for draft restaurants (they are hidden on purpose). --}}
<x-layout title="Page not found" :noindex="true">
    <x-error-content code="404" title="We can't find that page"
                     message="The page you are looking for does not exist, has moved, or the restaurant is no longer listed." />
</x-layout>
