{{-- Maintenance mode (php artisan down) or temporary overload. Standalone, like the 500 page. --}}
<x-error-shell title="Back soon">
    <x-error-content code="503" title="We'll be right back"
                     message="The site is being updated and will be back in a few minutes. Thank you for your patience.">
        {{-- No "Back to homepage" button: the homepage would show this same page --}}
        <x-button :href="url()->current()" class="px-6 py-3">Try again</x-button>
    </x-error-content>
</x-error-shell>
