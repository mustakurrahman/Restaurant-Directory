<x-admin.layout title="Dashboard">

    <h2 class="mb-3 text-lg font-semibold text-cream/80">Needs your attention</h2>
    <div class="grid gap-4 sm:grid-cols-3">
        {{-- Turns gold when something is waiting --}}
        <x-admin.stat-card label="Pending reviews" :value="$stats['pendingReviews']" :highlight="$stats['pendingReviews'] > 0" />
        <x-admin.stat-card label="Pending submissions" :value="$stats['pendingSubmissions']" :highlight="$stats['pendingSubmissions'] > 0" />
        <x-admin.stat-card label="Unread messages" :value="$stats['unreadMessages']" :highlight="$stats['unreadMessages'] > 0" />
    </div>

    <h2 class="mb-3 mt-10 text-lg font-semibold text-cream/80">Your directory</h2>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Restaurants" :value="$stats['restaurants']"
                           :hint="$stats['published'].' published, '.$stats['drafts'].' draft'" />
        <x-admin.stat-card label="Cities" :value="$stats['cities']" />
        <x-admin.stat-card label="Cuisines" :value="$stats['cuisines']" />
        <x-admin.stat-card label="Amenities" :value="$stats['amenities']" />
    </div>

</x-admin.layout>
