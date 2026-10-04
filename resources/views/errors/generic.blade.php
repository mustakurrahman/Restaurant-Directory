{{-- Shared by 4xx.blade.php and 5xx.blade.php: every error code that has no page of its own. Keeps the real status code. --}}
@php
    $code = $exception->getStatusCode();
    $text = \Symfony\Component\HttpFoundation\Response::$statusTexts[$code] ?? 'Error';
@endphp

<x-error-shell :title="$text">
    <x-error-content :code="$code" :title="$text"
                     message="Sorry, something unexpected happened. Please go back to the homepage and try again." />
</x-error-shell>
