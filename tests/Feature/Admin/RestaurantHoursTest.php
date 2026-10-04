<?php

namespace Tests\Feature\Admin;

use App\Models\OpeningHour;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantHoursTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // pages render without needing compiled CSS
        $this->restaurant = Restaurant::factory()->create(['name' => 'Hours Place']);
    }

    private function url(?Restaurant $restaurant = null): string
    {
        return route('admin.restaurants.hours.update', $restaurant ?? $this->restaurant);
    }

    /** One day as the form sends it */
    private function day(?string $opens = null, ?string $closes = null, bool $closed = false): array
    {
        return ['is_closed' => $closed ? '1' : '0', 'opens_at' => $opens, 'closes_at' => $closes];
    }

    /** A full valid week: Mon-Fri 11:00-22:00, Sat 11:00-23:30, Sun closed */
    private function week(array $overrides = []): array
    {
        $week = [];
        foreach (range(1, 5) as $d) {
            $week[$d] = $this->day('11:00', '22:00');
        }
        $week[6] = $this->day('11:00', '23:30');
        $week[7] = $this->day(closed: true);

        return ['hours' => $overrides + $week];
    }

    /** MySQL returns 11:00:00 and SQLite (used by tests) returns 11:00; compare only HH:MM */
    private function hhmm(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    private function row(int $day, ?Restaurant $restaurant = null): ?OpeningHour
    {
        return OpeningHour::where('restaurant_id', ($restaurant ?? $this->restaurant)->id)->where('day_of_week', $day)->first();
    }

    // ---------- Saving ----------

    public function test_saving_a_full_week_creates_seven_rows(): void
    {
        $this->put($this->url(), $this->week())
            ->assertRedirect(route('admin.restaurants.edit', $this->restaurant).'#hours')
            ->assertSessionHas('status', 'Opening hours saved.')
            ->assertSessionHasNoErrors();

        $this->assertSame(7, OpeningHour::where('restaurant_id', $this->restaurant->id)->count());

        $monday = $this->row(1);
        $this->assertSame('11:00', $this->hhmm($monday->opens_at));
        $this->assertSame('22:00', $this->hhmm($monday->closes_at));
        $this->assertFalse($monday->is_closed);
        $this->assertSame('23:30', $this->hhmm($this->row(6)->closes_at));
    }

    public function test_a_closed_day_keeps_no_times_even_if_times_were_sent(): void
    {
        $this->put($this->url(), $this->week([3 => $this->day('09:00', '17:00', closed: true)]))
            ->assertSessionHasNoErrors();

        $wednesday = $this->row(3);
        $this->assertTrue($wednesday->is_closed);
        $this->assertNull($wednesday->opens_at);
        $this->assertNull($wednesday->closes_at);
    }

    public function test_an_empty_day_is_not_listed_and_removes_its_old_row(): void
    {
        OpeningHour::factory()->create(['restaurant_id' => $this->restaurant->id, 'day_of_week' => 2]);

        $this->put($this->url(), $this->week([2 => $this->day(), 4 => $this->day()]))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->row(2)); // existing row removed
        $this->assertNull($this->row(4)); // never created
        $this->assertSame(5, OpeningHour::where('restaurant_id', $this->restaurant->id)->count());
    }

    public function test_saving_again_updates_the_same_rows_instead_of_adding_new_ones(): void
    {
        $this->put($this->url(), $this->week());
        $firstId = $this->row(1)->id;

        $this->put($this->url(), $this->week([1 => $this->day('08:00', '16:00'), 7 => $this->day('12:00', '18:00')]));

        $this->assertSame(7, OpeningHour::where('restaurant_id', $this->restaurant->id)->count());
        $this->assertSame($firstId, $this->row(1)->id);
        $this->assertSame('08:00', $this->hhmm($this->row(1)->opens_at));
        // Sunday changes from closed to open: the old "closed" flag must be cleared
        $this->assertFalse($this->row(7)->is_closed);
        $this->assertSame('12:00', $this->hhmm($this->row(7)->opens_at));
    }

    public function test_closing_after_midnight_is_allowed(): void
    {
        $this->put($this->url(), $this->week([5 => $this->day('18:00', '01:00')]))->assertSessionHasNoErrors();

        $this->assertSame('18:00', $this->hhmm($this->row(5)->opens_at));
        $this->assertSame('01:00', $this->hhmm($this->row(5)->closes_at));
    }

    public function test_other_restaurants_hours_are_untouched(): void
    {
        $other = Restaurant::factory()->create();
        OpeningHour::factory()->create(['restaurant_id' => $other->id, 'day_of_week' => 1, 'opens_at' => '07:00', 'closes_at' => '09:00']);

        $this->put($this->url(), $this->week());

        $this->assertSame('07:00', $this->hhmm($this->row(1, $other)->opens_at));
        $this->assertSame(1, OpeningHour::where('restaurant_id', $other->id)->count());
    }

    // ---------- Validation ----------

    public function test_only_one_time_filled_is_an_error(): void
    {
        $this->put($this->url(), $this->week([2 => $this->day('10:00', null)]))->assertSessionHasErrors('hours.2');
        $this->put($this->url(), $this->week([2 => $this->day(null, '22:00')]))->assertSessionHasErrors('hours.2');
    }

    public function test_equal_opening_and_closing_time_is_an_error(): void
    {
        $this->put($this->url(), $this->week([3 => $this->day('10:00', '10:00')]))->assertSessionHasErrors('hours.3');
    }

    public function test_badly_formed_times_are_rejected(): void
    {
        foreach (['25:00', '9am', '9:5', 'noon', '10:00:00'] as $bad) {
            $this->flushSession();
            $this->put($this->url(), $this->week([1 => $this->day($bad, '22:00')]))
                ->assertSessionHasErrors('hours.1.opens_at');
        }
    }

    public function test_one_bad_day_saves_nothing_at_all(): void
    {
        $this->put($this->url(), $this->week([6 => $this->day('11:00', null)]))->assertSessionHasErrors('hours.6');

        $this->assertSame(0, OpeningHour::where('restaurant_id', $this->restaurant->id)->count());
    }

    public function test_missing_hours_are_rejected(): void
    {
        $this->put($this->url(), [])->assertSessionHasErrors('hours');
    }

    public function test_day_numbers_outside_monday_to_sunday_are_ignored(): void
    {
        $data = $this->week();
        $data['hours'][8] = $this->day('10:00', '11:00');
        $data['hours'][0] = $this->day('10:00', '11:00');

        $this->put($this->url(), $data)->assertSessionHasNoErrors();

        $this->assertNull($this->row(8));
        $this->assertNull($this->row(0));
        $this->assertSame(7, OpeningHour::where('restaurant_id', $this->restaurant->id)->count());
    }

    // ---------- What the admin sees ----------

    public function test_edit_page_shows_seven_days_with_saved_values_and_closed_ticks(): void
    {
        $this->restaurant->saveOpeningHours($this->week()['hours']);

        $html = $this->get(route('admin.restaurants.edit', $this->restaurant))->assertOk()->getContent();

        $this->assertStringContainsString('id="hours"', $html);
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
        $this->assertSame(7, substr_count($html, 'data-hours-row'));
        $this->assertStringContainsString('name="hours[1][opens_at]" value="11:00"', $html); // 11:00, not 11:00:00
        $this->assertStringContainsString('name="hours[6][closes_at]" value="23:30"', $html);
        $this->assertMatchesRegularExpression('/name="hours\[7\]\[is_closed\]" value="1"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="hours\[1\]\[is_closed\]" value="1"\s+checked/', $html);
        $this->assertStringContainsString('Copy Monday to all days', $html);
    }

    public function test_edit_page_for_a_restaurant_without_hours_shows_empty_fields(): void
    {
        $html = $this->get(route('admin.restaurants.edit', $this->restaurant))->assertOk()->getContent();

        $this->assertSame(7, substr_count($html, 'data-hours-row'));
        $this->assertStringContainsString('name="hours[1][opens_at]" value=""', $html);
    }

    public function test_a_failed_save_shows_the_error_and_keeps_what_was_typed(): void
    {
        $this->restaurant->saveOpeningHours($this->week()['hours']);

        $response = $this->from(route('admin.restaurants.edit', $this->restaurant))
            ->followingRedirects()
            ->put($this->url(), $this->week([2 => $this->day('09:15', null), 7 => $this->day('13:00', '15:00')]));

        $html = $response->getContent();
        $this->assertStringContainsString('Enter both the opening and closing time, or leave both empty.', $html);
        $this->assertStringContainsString('name="hours[2][opens_at]" value="09:15"', $html); // typed value, not saved 11:00
        $this->assertStringContainsString('name="hours[7][opens_at]" value="13:00"', $html); // Sunday: unticked closed + new times
        $this->assertDoesNotMatchRegularExpression('/name="hours\[7\]\[is_closed\]" value="1"\s+checked/', $html);
        // Nothing was saved: Sunday is still closed in the database
        $this->assertTrue($this->row(7)->is_closed);
    }

    public function test_a_failed_hours_save_does_not_untick_the_restaurants_categories(): void
    {
        $cuisine = \App\Models\Cuisine::factory()->create();
        $this->restaurant->cuisines()->attach($cuisine);

        $html = $this->from(route('admin.restaurants.edit', $this->restaurant))
            ->followingRedirects()
            ->put($this->url(), ['hours' => [1 => $this->day('10:00', null)]])
            ->getContent();

        $this->assertMatchesRegularExpression('/name="cuisines\[\]" value="'.$cuisine->id.'"\s+checked/', $html);
    }
}
