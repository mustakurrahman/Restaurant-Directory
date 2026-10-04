{{-- Forbidden. The built-in message is ignored on purpose: it could reveal how the site works. --}}
<x-layout title="Access denied" :noindex="true">
    <x-error-content code="403" title="You don't have access to this page"
                     message="Sorry, you are not allowed to open this page. If you think this is a mistake, please contact us." />
</x-layout>
