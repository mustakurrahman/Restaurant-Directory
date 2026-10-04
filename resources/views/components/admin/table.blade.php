{{--
    <x-admin.table>
        <x-slot:head> <tr><th class="px-4 py-3 font-medium">Name</th></tr> </x-slot:head>
        <tr><td class="px-4 py-3">...</td></tr>
    </x-admin.table>
    Scrolls sideways on small screens instead of breaking the page.
--}}
<div class="overflow-x-auto rounded-2xl border border-white/10 bg-ink-900">
    <table class="w-full min-w-max text-left text-sm">
        @isset($head)
            <thead class="border-b border-white/10 text-xs uppercase tracking-wider text-cream/50">
                {{ $head }}
            </thead>
        @endisset
        <tbody class="divide-y divide-white/10">
            {{ $slot }}
        </tbody>
    </table>
</div>
