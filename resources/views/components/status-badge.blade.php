{{--
    A badge whose colour follows the status word, so every page shows a status the same way:
    <x-status-badge :status="$restaurant->status" />      published = green, draft = gray
    <x-status-badge :status="$review->status" />          approved = green, pending = gold, rejected = red
    An unknown word is shown in gray rather than failing.
--}}
@props(['status'])

<x-badge :variant="match ($status) {
    'published', 'approved' => 'green',
    'pending' => 'gold',
    'rejected' => 'red',
    default => 'gray',
}" {{ $attributes }}>{{ ucfirst($status) }}</x-badge>
