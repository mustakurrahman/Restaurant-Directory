<x-layout title="Submit a restaurant"
          description="Know a great restaurant that is missing from the directory? Tell us about it and we will review it. It only takes a minute."
          :canonical="route('submit.create')">

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <x-breadcrumbs :items="[['Home', route('home')], ['Submit a restaurant', null]]" />

        <h1 class="mt-4 text-4xl font-bold">Submit a restaurant</h1>
        <p class="mt-2 text-cream/70">
            Own a restaurant, or know one we should list? Fill in what you know. We review every suggestion before anything is published.
        </p>

        <div id="submit-form" class="mt-8 scroll-mt-24">
            @if (session('submission_received'))
                <div role="status" class="rounded-xl border border-green-500/40 bg-green-500/10 px-5 py-6 text-green-300">
                    <p class="font-display text-2xl font-semibold">Thank you!</p>
                    <p class="mt-1">We received your suggestion and will review it soon. If we have a question, we will email you.</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <x-button :href="route('restaurants.index')">Browse restaurants</x-button>
                        <x-button variant="outline" :href="route('home')">Back to the homepage</x-button>
                    </div>
                </div>
            @else
                <x-card>
                    <form method="POST" action="{{ route('submit.store') }}" class="space-y-5" novalidate>
                        @csrf
                        <x-honeypot />

                        <h2 class="text-xl font-semibold">About the restaurant</h2>

                        <x-input name="restaurant_name" label="Restaurant name *" maxlength="255" />
                        <x-input name="address" label="Street address *" maxlength="255" autocomplete="street-address" />

                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input name="city" label="City *" maxlength="100" />
                            <x-input name="cuisine" label="Cuisine" maxlength="100" hint="For example Italian, Thai or Seafood" />
                            <x-input name="phone" label="Phone" type="tel" maxlength="30" autocomplete="off" />
                            <x-input name="website" label="Website" type="url" maxlength="255" placeholder="https://" />
                        </div>

                        <x-textarea name="description" label="Anything else we should know?" :rows="4" maxlength="2000"
                                    hint="Opening hours, specialties, what makes it special. Up to 2000 characters." />

                        <h2 class="pt-2 text-xl font-semibold">About you</h2>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input name="submitter_name" label="Your name *" maxlength="100" autocomplete="name" />
                            <x-input name="submitter_email" label="Your email *" type="email" maxlength="255" autocomplete="email"
                                     hint="Never shown publicly. Only used if we need to contact you." />
                        </div>

                        <div class="flex flex-wrap items-center gap-4">
                            <x-button type="submit">Send suggestion</x-button>
                            <p class="text-xs text-cream/50">* required</p>
                        </div>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layout>
