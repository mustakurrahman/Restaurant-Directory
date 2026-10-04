@php
    $tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'];
    $emptyText = [
        'pending' => 'Nothing is waiting for approval. Nice and tidy!',
        'approved' => 'No approved reviews yet.',
        'rejected' => 'No rejected reviews.',
        'all' => 'No reviews have been written yet.',
    ];
@endphp

<x-admin.layout title="Reviews">

    {{-- Tabs: each one is a link, so the chosen tab lives in the address --}}
    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Review status">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.reviews.index', ['status' => $key]) }}"
               @if ($filter === $key) aria-current="page" @endif
               @class([
                   'inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition',
                   'border-gold-500 bg-gold-500/10 text-gold-500' => $filter === $key,
                   'border-white/15 text-cream/70 hover:border-gold-500 hover:text-gold-500' => $filter !== $key,
               ])>
                {{ $label }}
                <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    @if ($reviews->isEmpty())
        <x-card class="py-12 text-center">
            <p class="text-cream/70">{{ $emptyText[$filter] }}</p>
            @if ($filter !== 'all')
                <x-button variant="outline" :href="route('admin.reviews.index', ['status' => 'all'])" class="mt-5">Show all reviews</x-button>
            @endif
        </x-card>
    @else
        <x-admin.table>
            <x-slot:head>
                <tr>
                    <th class="px-4 py-3 font-medium">Restaurant</th>
                    <th class="px-4 py-3 font-medium">Reviewer</th>
                    <th class="px-4 py-3 font-medium">Rating</th>
                    <th class="min-w-72 px-4 py-3 font-medium">Review</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                </tr>
            </x-slot:head>

            @foreach ($reviews as $review)
                <tr class="align-top">
                    <td class="px-4 py-3 font-medium">
                        {{ $review->restaurant->name }}
                        {{-- Draft restaurants have no public page, so no link --}}
                        @if ($review->restaurant->status === 'published')
                            <a href="{{ route('restaurants.show', $review->restaurant) }}#reviews" target="_blank" rel="noopener"
                               class="block text-xs font-normal text-gold-500 hover:underline">View page<span class="sr-only"> (opens in a new tab)</span></a>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <p>{{ $review->name }}</p>
                        <p class="text-xs text-cream/50">{{ $review->email }}</p>
                        <p class="mt-1 text-xs text-cream/50"><time datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->format('M j, Y g:i A') }}</time></p>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <span class="text-gold-500" aria-hidden="true">{{ str_repeat('★', $review->rating) }}</span><span class="text-cream/25" aria-hidden="true">{{ str_repeat('★', 5 - $review->rating) }}</span>
                        <span class="sr-only">{{ $review->rating }} out of 5</span>
                    </td>
                    <td class="px-4 py-3">
                        {{-- Escaped by Blade, so nothing a visitor typed can run as code --}}
                        <p class="max-w-md whitespace-pre-line break-words text-cream/80">{{ $review->comment }}</p>
                    </td>
                    <td class="px-4 py-3"><x-status-badge :status="$review->status" /></td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap justify-end gap-2">
                            @foreach (['approved' => ['Approve', 'primary'], 'rejected' => ['Reject', 'outline']] as $newStatus => [$text, $variant])
                                @if ($review->status !== $newStatus)
                                    <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $newStatus }}">
                                        <x-button type="submit" :variant="$variant" class="px-3 py-1.5">{{ $text }}</x-button>
                                    </form>
                                @endif
                            @endforeach
                            <x-admin.delete-form :action="route('admin.reviews.destroy', $review)" message="Delete this review for good?" />
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-admin.table>

        <div class="mt-6">
            {{ $reviews->links() }}
        </div>
    @endif
</x-admin.layout>
