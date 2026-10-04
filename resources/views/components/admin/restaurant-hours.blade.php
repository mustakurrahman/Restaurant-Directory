{{--
    Weekly opening hours for an existing restaurant: <x-admin.restaurant-hours :restaurant="$restaurant" />
    Needs $restaurant->openingHours loaded. The small script in resources/js/app.js adds the
    "Closed" greying-out and the "Copy Monday" button; the form works without it too.
--}}
@props(['restaurant'])

@php
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $saved = $restaurant->openingHours->keyBy('day_of_week');
    // The database gives 09:00:00; a time field wants 09:00
    $time = fn ($value) => $value ? substr($value, 0, 5) : '';
    $fieldClass = 'w-full rounded-lg border bg-ink-950 px-3 py-2 text-cream focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 disabled:opacity-30';
@endphp

<section id="hours" class="max-w-3xl scroll-mt-6 space-y-6">
    <h2 class="text-2xl font-semibold">Opening hours</h2>

    <x-card class="space-y-5">
        <p class="text-sm text-cream/60">
            Tick <strong class="text-cream">Closed</strong> for days off. Leave a day empty if you do not want to list it.
            A closing time earlier than the opening time means it closes after midnight (for example 18:00 to 01:00).
        </p>

        <form method="POST" action="{{ route('admin.restaurants.hours.update', $restaurant) }}" data-hours-form class="space-y-5">
            @csrf
            @method('PUT')

            <div class="divide-y divide-white/10">
                @foreach ($days as $day => $name)
                    @php
                        $row = $saved->get($day);
                        // old() holds what was typed if the save failed; otherwise show what is saved
                        $closed = (bool) old("hours.{$day}.is_closed", $row?->is_closed);
                        $opens = old("hours.{$day}.opens_at", $time($row?->opens_at));
                        $closes = old("hours.{$day}.closes_at", $time($row?->closes_at));
                        $error = $errors->first("hours.{$day}") ?: $errors->first("hours.{$day}.opens_at") ?: $errors->first("hours.{$day}.closes_at");
                    @endphp

                    <div data-hours-row class="grid grid-cols-2 items-end gap-3 py-3 sm:grid-cols-[7rem_6rem_1fr_1fr] sm:items-center">
                        <p class="col-span-2 font-medium sm:col-span-1">{{ $name }}</p>

                        {{-- The hidden 0 is sent when "Closed" is unticked, so a failed save can show it unticked --}}
                        <input type="hidden" name="hours[{{ $day }}][is_closed]" value="0">
                        <div class="col-span-2 sm:col-span-1">
                            <x-checkbox name="hours[{{ $day }}][is_closed]" value="1" label="Closed" :checked="$closed" data-field="is_closed" />
                        </div>

                        <div>
                            <label for="opens-{{ $day }}" class="mb-1 block text-xs text-cream/50 sm:sr-only">{{ $name }} opens</label>
                            <input type="time" id="opens-{{ $day }}" name="hours[{{ $day }}][opens_at]" value="{{ $opens }}" data-field="opens_at"
                                   class="{{ $fieldClass }} {{ $error ? 'border-red-500' : 'border-white/20' }}">
                        </div>
                        <div>
                            <label for="closes-{{ $day }}" class="mb-1 block text-xs text-cream/50 sm:sr-only">{{ $name }} closes</label>
                            <input type="time" id="closes-{{ $day }}" name="hours[{{ $day }}][closes_at]" value="{{ $closes }}" data-field="closes_at"
                                   class="{{ $fieldClass }} {{ $error ? 'border-red-500' : 'border-white/20' }}">
                        </div>

                        @if ($error)
                            <p class="col-span-2 text-sm text-red-400 sm:col-span-full">{{ $error }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-3">
                <x-button type="submit">Save opening hours</x-button>
                <x-button type="button" variant="outline" data-copy-hours>Copy Monday to all days</x-button>
            </div>
        </form>
    </x-card>
</section>
