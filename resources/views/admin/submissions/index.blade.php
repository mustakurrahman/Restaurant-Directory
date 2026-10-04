@php
    $tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'];
    $emptyText = [
        'pending' => 'No suggestions are waiting. Nice and tidy!',
        'approved' => 'No approved suggestions yet.',
        'rejected' => 'No rejected suggestions.',
        'all' => 'Nobody has suggested a restaurant yet.',
    ];
@endphp

<x-admin.layout title="Submissions">

    {{-- Tabs: each one is a link, so the chosen tab lives in the address --}}
    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Submission status">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.submissions.index', ['status' => $key]) }}"
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

    @if ($submissions->isEmpty())
        <x-card class="py-12 text-center">
            <p class="text-cream/70">{{ $emptyText[$filter] }}</p>
            @if ($filter !== 'all')
                <x-button variant="outline" :href="route('admin.submissions.index', ['status' => 'all'])" class="mt-5">Show all suggestions</x-button>
            @endif
        </x-card>
    @else
        {{-- One card per suggestion: there is a lot to read, which a narrow table row handles badly --}}
        <ul class="space-y-4">
            @foreach ($submissions as $submission)
                <li>
                    <x-card>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="break-words text-xl font-semibold">{{ $submission->restaurant_name }}</h2>
                                <p class="mt-1 text-sm text-cream/60">
                                    Suggested <time datetime="{{ $submission->created_at->toIso8601String() }}">{{ $submission->created_at->format('M j, Y g:i A') }}</time>
                                </p>
                            </div>
                            <x-status-badge :status="$submission->status" />
                        </div>

                        <dl class="mt-4 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-cream/50">Address</dt>
                                <dd class="break-words">{{ $submission->address }}, {{ $submission->city }}</dd>
                            </div>
                            <div>
                                <dt class="text-cream/50">Cuisine</dt>
                                <dd>{{ $submission->cuisine ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-cream/50">Phone</dt>
                                <dd>{{ $submission->phone ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-cream/50">Website</dt>
                                <dd class="break-all">
                                    {{-- Only real web addresses become links --}}
                                    @if ($submission->website && preg_match('#^https?://#i', $submission->website))
                                        <a href="{{ $submission->website }}" target="_blank" rel="nofollow noopener noreferrer" class="text-gold-500 hover:underline">{{ $submission->website }}<span class="sr-only"> (opens in a new tab)</span></a>
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-cream/50">Suggested by</dt>
                                <dd class="break-words">{{ $submission->submitter_name }} · <a href="mailto:{{ $submission->submitter_email }}" class="text-gold-500 hover:underline">{{ $submission->submitter_email }}</a></dd>
                            </div>
                            @if ($submission->description)
                                <div class="sm:col-span-2">
                                    <dt class="text-cream/50">Notes</dt>
                                    {{-- Escaped by Blade, so nothing a visitor typed can run as code --}}
                                    <dd class="whitespace-pre-line break-words text-cream/80">{{ $submission->description }}</dd>
                                </div>
                            @endif
                        </dl>

                        <div class="mt-5 flex flex-wrap gap-2 border-t border-white/10 pt-4">
                            @if ($submission->status === 'pending')
                                <x-button :href="route('admin.restaurants.create', ['submission' => $submission->id])" class="px-3 py-1.5">Create restaurant</x-button>
                            @endif

                            @foreach (['rejected' => ['Reject', 'outline'], 'pending' => ['Move back to pending', 'outline']] as $newStatus => [$text, $variant])
                                {{-- Pending ones can be rejected; handled ones can be reopened --}}
                                @if (($newStatus === 'rejected' && $submission->status === 'pending') || ($newStatus === 'pending' && $submission->status !== 'pending'))
                                    <form method="POST" action="{{ route('admin.submissions.update', $submission) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $newStatus }}">
                                        <x-button type="submit" :variant="$variant" class="px-3 py-1.5">{{ $text }}</x-button>
                                    </form>
                                @endif
                            @endforeach

                            <x-admin.delete-form :action="route('admin.submissions.destroy', $submission)" message="Delete this suggestion for good?" />
                        </div>
                    </x-card>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $submissions->links() }}
        </div>
    @endif
</x-admin.layout>
