{{-- Too many requests: the spam protection (rate limit) on forms. Laravel tells us how long to wait in Retry-After. --}}
@php
    $seconds = (int) ($exception->getHeaders()['Retry-After'] ?? 0);
    $wait = match (true) {
        $seconds <= 0 => 'a minute',
        $seconds < 60 => $seconds.' '.Str::plural('second', $seconds),
        default => ceil($seconds / 60).' '.Str::plural('minute', (int) ceil($seconds / 60)),
    };
@endphp

<x-layout title="Too many requests" :noindex="true">
    <x-error-content code="429" title="Slow down a little"
                     :message="'You have sent too many requests in a short time. Please wait '.$wait.' and try again.'" />
</x-layout>
