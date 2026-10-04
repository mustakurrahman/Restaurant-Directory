{{-- Style guide: one live example of every reusable admin component. Only registered on a local computer (see routes/web.php). --}}
@php
    // Pretend the field "demo_error" failed validation, so its red error state can be shown.
    // share(): components only see errors that are shared with every view.
    view()->share('errors', (new Illuminate\Support\ViewErrorBag)->put('default', new Illuminate\Support\MessageBag(['demo_error' => 'This is what an error message looks like.'])));

    // A made-up paginator (95 items, 10 per page, we are on page 4) just to draw the page links
    $demoPages = new Illuminate\Pagination\LengthAwarePaginator(range(31, 40), 95, 10, 4, ['path' => url()->current()]);
    $sample = 'rounded-2xl border border-white/10 bg-ink-900 p-6';
@endphp

<x-admin.layout title="Components">
    <p class="mb-8 max-w-3xl text-cream/70">
        Every reusable building block with a copy-ready example. The code shown under each one is exactly what you write in a Blade file.
    </p>

    <div class="space-y-8">

        <section class="{{ $sample }}">
            <h2 class="text-xl font-semibold">Buttons</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-button type="submit">Save changes</x-button>
                <x-button variant="danger">Delete</x-button>
                <x-button variant="outline" href="#">Cancel</x-button>
            </div>
            <pre class="mt-4 overflow-x-auto rounded-lg bg-ink-950 p-4 text-xs text-cream/70">&lt;x-button type="submit"&gt;Save changes&lt;/x-button&gt;
&lt;x-button variant="danger"&gt;Delete&lt;/x-button&gt;
&lt;x-button variant="outline" href="/admin/cities"&gt;Cancel&lt;/x-button&gt;</pre>
        </section>

        <section class="{{ $sample }}">
            <h2 class="text-xl font-semibold">Form fields</h2>
            <p class="mt-1 text-sm text-cream/60">Each one has its label, hint and red error message built in. The last text field shows the error state.</p>

            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                <x-input name="demo_name" label="Text input" hint="A short hint under the field" placeholder="e.g. New York" />
                <x-input name="demo_error" label="Text input with an error" value="x" />

                <x-select name="demo_select" label="Select">
                    <option value="">Choose a city</option>
                    <option value="1">New York</option>
                    <option value="2">London</option>
                </x-select>

                <x-form-field name="demo_time" label="Form field (any control inside)" hint="Wraps a control that has no component of its own">
                    <input type="time" id="demo_time" name="demo_time" value="18:30"
                           class="w-full rounded-lg border border-white/20 bg-ink-950 px-4 py-2.5 text-cream focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500">
                </x-form-field>

                <div class="sm:col-span-2">
                    <x-textarea name="demo_text" label="Textarea" hint="Longer text goes here" :rows="3" />
                </div>

                <div class="space-y-2 sm:col-span-2">
                    <x-checkbox name="demo_check[]" value="wifi" label="Checkbox: Free Wi-Fi" :checked="true" />
                    <x-checkbox name="demo_check[]" value="parking" label="Checkbox: Parking" />
                </div>
            </div>
            <pre class="mt-4 overflow-x-auto rounded-lg bg-ink-950 p-4 text-xs text-cream/70">&lt;x-input name="name" label="City name" hint="Shown to visitors" :value="$city-&gt;name" /&gt;
&lt;x-select name="city_id" label="City"&gt;&lt;option value="1"&gt;New York&lt;/option&gt;&lt;/x-select&gt;
&lt;x-textarea name="description" label="Description" :rows="6" :value="$restaurant-&gt;description" /&gt;
&lt;x-checkbox name="amenities[]" value="wifi" label="Free Wi-Fi" :checked="$ticked" /&gt;
&lt;x-form-field name="opens_at" label="Opens"&gt;&lt;input type="time" id="opens_at" name="opens_at"&gt;&lt;/x-form-field&gt;</pre>
        </section>

        <section class="{{ $sample }}">
            <h2 class="text-xl font-semibold">Status badges</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach (['published', 'approved', 'pending', 'rejected', 'draft'] as $status)
                    <x-status-badge :status="$status" />
                @endforeach
                <x-badge variant="gold">Featured</x-badge>
            </div>
            <pre class="mt-4 overflow-x-auto rounded-lg bg-ink-950 p-4 text-xs text-cream/70">&lt;x-status-badge :status="$review-&gt;status" /&gt;      colour follows the word
&lt;x-badge variant="gold"&gt;Featured&lt;/x-badge&gt;          pick a colour yourself: gold, green, red, gray</pre>
        </section>

        <section>
            <h2 class="mb-4 text-xl font-semibold">Data table</h2>
            <x-admin.table>
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </x-slot:head>
                @foreach ([['Trattoria Bella Luna', 'published'], ['Sakura House', 'draft'], ['Spice Route', 'pending']] as [$name, $status])
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $name }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$status" /></td>
                        <td class="px-4 py-3 text-right">
                            <x-button variant="outline" href="#" class="px-3 py-1.5">Edit</x-button>
                            <x-admin.delete-form action="#" message="Delete this example?" />
                        </td>
                    </tr>
                @endforeach
            </x-admin.table>
            <pre class="mt-4 overflow-x-auto rounded-lg bg-ink-900 p-4 text-xs text-cream/70">&lt;x-admin.table&gt;
    &lt;x-slot:head&gt;&lt;tr&gt;&lt;th class="px-4 py-3 font-medium"&gt;Name&lt;/th&gt;&lt;/tr&gt;&lt;/x-slot:head&gt;
    @@foreach ($items as $item) &lt;tr&gt;&lt;td class="px-4 py-3"&gt;@{{ $item-&gt;name }}&lt;/td&gt;&lt;/tr&gt; @@endforeach
&lt;/x-admin.table&gt;</pre>
        </section>

        <section>
            <h2 class="mb-4 text-xl font-semibold">Pagination</h2>
            {{ $demoPages->links() }}
            <pre class="mt-4 overflow-x-auto rounded-lg bg-ink-900 p-4 text-xs text-cream/70">In the controller:  $items = Model::latest()-&gt;paginate(15);
In the view:        @{{ $items-&gt;links() }}   (gold and black automatically)</pre>
        </section>
    </div>
</x-admin.layout>
