{{-- Server error. Standalone page: it must not need the database or session, which may be the very thing that broke. --}}
<x-error-shell title="Something went wrong">
    <x-error-content code="500" title="Something went wrong on our side"
                     message="Sorry, we hit an unexpected problem. It is not your fault. Please try again in a few minutes." />
</x-error-shell>
