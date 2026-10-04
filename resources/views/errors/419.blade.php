{{-- Page expired: the security code of a form ran out (the page was left open too long). --}}
<x-layout title="Page expired" :noindex="true">
    <x-error-content code="419" title="This page has expired"
                     message="The form was open for too long and its security code ran out. Nothing was lost on our side: go back, refresh the page and send it again.">
        <x-button :href="url()->previous()" class="px-6 py-3">Go back and try again</x-button>
        <x-button variant="outline" :href="url('/')" class="px-6 py-3">Back to the homepage</x-button>
    </x-error-content>
</x-layout>
