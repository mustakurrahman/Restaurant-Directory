<x-layout title="Contact us"
          description="Questions, corrections or ideas? Send us a message and we will get back to you as soon as we can."
          :canonical="route('contact.create')">

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <x-breadcrumbs :items="[['Home', route('home')], ['Contact', null]]" />

        <h1 class="mt-4 text-4xl font-bold">Contact us</h1>
        <p class="mt-2 text-cream/70">
            Spotted a mistake on a listing, or have a question or an idea? Write to us below.
            @if (Route::has('submit.create'))
                Want to add a restaurant? Use <a href="{{ route('submit.create') }}" class="text-gold-500 underline hover:text-gold-600">the submit form</a> instead.
            @endif
        </p>

        <div id="contact-form" class="mt-8 scroll-mt-24">
            @if (session('message_sent'))
                <div role="status" class="rounded-xl border border-green-500/40 bg-green-500/10 px-5 py-6 text-green-300">
                    <p class="font-display text-2xl font-semibold">Message sent!</p>
                    <p class="mt-1">Thank you for getting in touch. We will reply to the email address you gave us.</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <x-button :href="route('restaurants.index')">Browse restaurants</x-button>
                        <x-button variant="outline" :href="route('home')">Back to the homepage</x-button>
                    </div>
                </div>
            @else
                <x-card>
                    <form method="POST" action="{{ route('contact.store') }}" class="space-y-5" novalidate>
                        @csrf
                        <x-honeypot />

                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input name="name" label="Your name *" maxlength="100" autocomplete="name" />
                            <x-input name="email" label="Your email *" type="email" maxlength="255" autocomplete="email"
                                     hint="Never shown publicly. We only use it to reply." />
                        </div>

                        <x-input name="subject" label="Subject" maxlength="150" />

                        <x-textarea name="message" label="Your message *" :rows="6" maxlength="3000" hint="At least 10 characters." />

                        <div class="flex flex-wrap items-center gap-4">
                            <x-button type="submit">Send message</x-button>
                            <p class="text-xs text-cream/50">* required</p>
                        </div>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layout>
