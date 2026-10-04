@php
    $tabs = ['unread' => 'Unread', 'read' => 'Read', 'all' => 'All'];
    $emptyText = [
        'unread' => 'No unread messages. You are all caught up!',
        'read' => 'No messages have been marked as read.',
        'all' => 'Nobody has written to you yet.',
    ];
@endphp

<x-admin.layout title="Messages">

    {{-- Tabs: each one is a link, so the chosen tab lives in the address --}}
    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Message filter">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.messages.index', ['show' => $key]) }}"
               @if ($filter === $key) aria-current="page" @endif
               @class([
                   'inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition',
                   'border-gold-500 bg-gold-500/10 text-gold-500' => $filter === $key,
                   'border-white/15 text-cream/70 hover:border-gold-500 hover:text-gold-500' => $filter !== $key,
               ])>
                {{ $label }}
                <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($messages->isEmpty())
        <x-card class="py-12 text-center">
            <p class="text-cream/70">{{ $emptyText[$filter] }}</p>
            @if ($filter !== 'all')
                <x-button variant="outline" :href="route('admin.messages.index', ['show' => 'all'])" class="mt-5">Show all messages</x-button>
            @endif
        </x-card>
    @else
        {{-- One card per message: they can be long, which a narrow table row handles badly --}}
        <ul class="space-y-4">
            @foreach ($messages as $message)
                <li>
                    {{-- Unread messages get a gold bar on the left edge (a shadow, so it cannot clash with the border colour) --}}
                    <x-card :class="$message->is_read ? '' : 'shadow-[inset_4px_0_0_0_var(--color-gold-500)]'">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="break-words text-xl font-semibold">{{ $message->subject ?: '(no subject)' }}</h2>
                                <p class="mt-1 break-words text-sm text-cream/60">
                                    From {{ $message->name }} ·
                                    <a href="mailto:{{ $message->email }}" class="text-gold-500 hover:underline">{{ $message->email }}</a> ·
                                    <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, Y g:i A') }}</time>
                                </p>
                            </div>
                            @if ($message->is_read)
                                <x-badge>Read</x-badge>
                            @else
                                <x-badge variant="gold">Unread</x-badge>
                            @endif
                        </div>

                        {{-- Escaped by Blade, so nothing a visitor typed can run as code --}}
                        <p class="mt-4 whitespace-pre-line break-words text-cream/80">{{ $message->message }}</p>

                        <div class="mt-5 flex flex-wrap gap-2 border-t border-white/10 pt-4">
                            {{-- Opens the owner's email program with the address and "Re: subject" already filled in --}}
                            <x-button :href="'mailto:'.$message->email.'?subject='.rawurlencode('Re: '.($message->subject ?: 'your message'))" class="px-3 py-1.5">Reply by email</x-button>

                            <form method="POST" action="{{ route('admin.messages.update', $message) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_read" value="{{ $message->is_read ? 0 : 1 }}">
                                <x-button type="submit" variant="outline" class="px-3 py-1.5">{{ $message->is_read ? 'Mark as unread' : 'Mark as read' }}</x-button>
                            </form>

                            <x-admin.delete-form :action="route('admin.messages.destroy', $message)" message="Delete this message for good?" />
                        </div>
                    </x-card>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $messages->links() }}
        </div>
    @endif
</x-admin.layout>
