<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSubmissionTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function submission(string $status = 'pending', array $extra = []): Submission
    {
        return Submission::factory()->create($extra + ['status' => $status]);
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)));
    }

    // ---------- The list ----------

    public function test_opens_on_pending_with_tab_counts(): void
    {
        $this->submission('pending', ['restaurant_name' => 'Pending Place']);
        $this->submission('approved', ['restaurant_name' => 'Approved Place']);
        $this->submission('rejected', ['restaurant_name' => 'Rejected Place']);
        $this->submission('pending');

        $text = $this->text($this->get('/admin/submissions')->assertOk()->getContent());

        $this->assertStringContainsString('Pending Place', $text);
        $this->assertStringNotContainsString('Approved Place', $text);
        $this->assertStringNotContainsString('Rejected Place', $text);
        $this->assertStringContainsString('Pending 2 Approved 1 Rejected 1 All 4', $text);
    }

    public function test_tabs_filter_and_unknown_tabs_fall_back_to_pending(): void
    {
        $this->submission('pending', ['restaurant_name' => 'Pending Place']);
        $this->submission('approved', ['restaurant_name' => 'Approved Place']);

        $this->get('/admin/submissions?status=approved')->assertSee('Approved Place')->assertDontSee('Pending Place');
        $this->get('/admin/submissions?status=all')->assertSee('Approved Place')->assertSee('Pending Place');
        $this->get('/admin/submissions?status=banana')->assertOk()->assertSee('Pending Place')->assertDontSee('Approved Place');
        $this->get('/admin/submissions?status[]=x')->assertOk();
    }

    public function test_empty_states(): void
    {
        $this->get('/admin/submissions')->assertOk()->assertSee('No suggestions are waiting');
        $this->get('/admin/submissions?status=all')->assertOk()->assertSee('Nobody has suggested a restaurant yet');
    }

    public function test_every_detail_is_shown_and_text_is_escaped(): void
    {
        $this->submission('pending', [
            'restaurant_name' => '<b>Evil</b> Diner', 'address' => '9 Elm St', 'city' => 'Springfield', 'cuisine' => 'Thai',
            'phone' => '+1 555 0100', 'description' => "<script>alert(1)</script>\nLine two", 'submitter_name' => 'Sam Sender', 'submitter_email' => 'sam@example.test',
            'website' => 'https://evil.example',
        ]);

        $html = $this->get('/admin/submissions')->getContent();
        $text = $this->text($html);

        $this->assertStringNotContainsString('<b>Evil</b>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        foreach (['9 Elm St, Springfield', 'Thai', '+1 555 0100', 'Sam Sender', 'sam@example.test', 'Line two'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertStringContainsString('href="mailto:sam@example.test"', $html);
        $this->assertStringContainsString('href="https://evil.example" target="_blank" rel="nofollow noopener noreferrer"', $html);
    }

    public function test_an_unsafe_website_is_never_made_into_a_link(): void
    {
        $this->submission('pending', ['website' => 'javascript:alert(1)']);

        $this->assertStringNotContainsString('javascript:', $this->get('/admin/submissions')->getContent());
    }

    public function test_buttons_match_the_status(): void
    {
        $pending = $this->submission('pending');
        $html = $this->get('/admin/submissions')->getContent();
        $this->assertStringContainsString('href="'.route('admin.restaurants.create', ['submission' => $pending->id]).'"', $html);
        $this->assertStringContainsString('>Reject<', $html);
        $this->assertStringNotContainsString('Move back to pending', $html);

        $this->submission('rejected');
        $html = $this->get('/admin/submissions?status=rejected')->getContent();
        $this->assertStringContainsString('Move back to pending', $html);
        $this->assertStringNotContainsString('Create restaurant', $html);
        $this->assertStringNotContainsString('>Reject<', $html);
    }

    public function test_query_count_does_not_grow_with_more_submissions(): void
    {
        $count = function () {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get('/admin/submissions?status=all')->assertOk();

            return $queries;
        };

        Submission::factory()->count(2)->create();
        $few = $count();
        Submission::factory()->count(10)->create();

        $this->assertSame($few, $count());
    }

    public function test_pagination_steps_back_when_a_page_empties(): void
    {
        Submission::factory()->count(16)->create();

        $this->get('/admin/submissions?page=2')->assertOk();
        Submission::latest()->latest('id')->skip(15)->first()->update(['status' => 'rejected']);

        $this->get('/admin/submissions?status=pending&page=2')->assertRedirect();
    }

    // ---------- Changing the status ----------

    public function test_reject_and_reopen(): void
    {
        $submission = $this->submission();

        $this->patch(route('admin.submissions.update', $submission), ['status' => 'rejected'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('rejected', $submission->fresh()->status);

        $this->patch(route('admin.submissions.update', $submission), ['status' => 'pending'])->assertRedirect();
        $this->assertSame('pending', $submission->fresh()->status);
    }

    public function test_only_the_known_statuses_are_accepted_and_nothing_else_changes(): void
    {
        $submission = $this->submission('pending', ['restaurant_name' => 'Original']);

        foreach (['', 'published', 'REJECTED', ['x']] as $bad) {
            $this->from('/admin/submissions')->patch(route('admin.submissions.update', $submission), ['status' => $bad])->assertSessionHasErrors('status');
        }
        $this->patch(route('admin.submissions.update', $submission), ['status' => 'approved', 'restaurant_name' => 'Hacked']);

        $this->assertSame('Original', $submission->fresh()->restaurant_name);
    }

    public function test_delete_and_unknown_ids(): void
    {
        $submission = $this->submission();

        $this->delete(route('admin.submissions.destroy', $submission))->assertRedirect()->assertSessionHas('status', 'Suggestion deleted.');
        $this->assertModelMissing($submission);
        $this->patch('/admin/submissions/9999', ['status' => 'approved'])->assertNotFound();
        $this->delete('/admin/submissions/9999')->assertNotFound();
    }

    // ---------- Create restaurant from a suggestion ----------

    public function test_the_create_form_is_prefilled_from_the_suggestion(): void
    {
        $city = City::factory()->create(['name' => 'Springfield']);
        $cuisine = Cuisine::factory()->create(['name' => 'Italian']);
        $submission = $this->submission('pending', [
            'restaurant_name' => 'Luigi Corner', 'address' => '5 Main Street', 'city' => 'springfield', 'cuisine' => 'ITALIAN',
            'phone' => '+1 555 0100', 'website' => 'https://luigi.example', 'description' => 'Wood-fired pizza.', 'submitter_name' => 'Alice', 'submitter_email' => 'alice@example.test',
        ]);

        $html = $this->get(route('admin.restaurants.create', ['submission' => $submission->id]))->assertOk()->getContent();

        $this->assertStringContainsString('value="Luigi Corner"', $html);
        $this->assertStringContainsString('value="5 Main Street"', $html);
        $this->assertStringContainsString('value="+1 555 0100"', $html);
        $this->assertStringContainsString('value="https://luigi.example"', $html);
        $this->assertStringContainsString('Wood-fired pizza.', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$city->id.'"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/name="cuisines\[\]"\s+value="'.$cuisine->id.'"\s+checked/', $html);
        $this->assertStringContainsString('Filled in from a suggestion by Alice', $this->text($html));
        $this->assertStringContainsString('name="from_submission" value="'.$submission->id.'"', $html);
        $this->assertStringNotContainsString('is not in your list', $html);
    }

    public function test_unmatched_city_and_cuisine_are_explained_not_guessed(): void
    {
        $submission = $this->submission('pending', ['city' => 'Atlantis', 'cuisine' => 'Martian']);

        $text = $this->text($this->get(route('admin.restaurants.create', ['submission' => $submission->id]))->getContent());

        $this->assertStringContainsString('The city "Atlantis" is not in your list', $text);
        $this->assertStringContainsString('The cuisine "Martian" is not in your list', $text);
    }

    public function test_unknown_or_missing_submission_gives_the_normal_empty_form(): void
    {
        $html = $this->get(route('admin.restaurants.create', ['submission' => 9999]))->assertOk()->getContent();
        $this->assertStringNotContainsString('Filled in from a suggestion', $html);
        $this->assertStringNotContainsString('from_submission', $html);

        $this->get(route('admin.restaurants.create'))->assertOk();
    }

    public function test_saving_the_restaurant_marks_the_pending_suggestion_approved(): void
    {
        $city = City::factory()->create();
        $submission = $this->submission();

        $this->post(route('admin.restaurants.store'), [
            'name' => 'Luigi Corner', 'address' => '5 Main Street', 'city_id' => $city->id, 'price_range' => 2, 'status' => 'draft',
            'from_submission' => $submission->id,
        ])->assertRedirect(route('admin.restaurants.index'));

        $this->assertSame('draft', Restaurant::sole()->status);   // never public automatically
        $this->assertSame('approved', $submission->fresh()->status);
    }

    public function test_a_failed_save_keeps_the_suggestion_pending_and_remembers_the_link(): void
    {
        $submission = $this->submission();

        $this->from(route('admin.restaurants.create'))->post(route('admin.restaurants.store'), ['name' => '', 'from_submission' => $submission->id])
            ->assertSessionHasErrors('name');
        $this->assertSame('pending', $submission->fresh()->status);

        $html = $this->followingRedirects()->from(route('admin.restaurants.create'))
            ->post(route('admin.restaurants.store'), ['name' => '', 'from_submission' => $submission->id])->getContent();
        $this->assertStringContainsString('name="from_submission" value="'.$submission->id.'"', $html);
    }

    public function test_a_restaurant_saved_without_a_suggestion_touches_no_submission(): void
    {
        $city = City::factory()->create();
        $other = $this->submission();

        $this->post(route('admin.restaurants.store'), ['name' => 'Plain One', 'address' => '1 St', 'city_id' => $city->id, 'price_range' => 2, 'status' => 'draft']);

        $this->assertSame('pending', $other->fresh()->status);
    }

    public function test_an_already_handled_suggestion_is_not_changed_by_a_later_save(): void
    {
        $city = City::factory()->create();
        $rejected = $this->submission('rejected');

        $this->post(route('admin.restaurants.store'), ['name' => 'X Place', 'address' => '1 St', 'city_id' => $city->id, 'price_range' => 2, 'status' => 'draft', 'from_submission' => $rejected->id]);

        $this->assertSame('rejected', $rejected->fresh()->status);
    }

    public function test_sidebar_link_is_live_and_the_dashboard_counts_pending(): void
    {
        $this->submission();
        $this->submission();
        $this->submission('approved');

        $html = $this->get('/admin')->getContent();

        $this->assertStringContainsString('href="'.route('admin.submissions.index').'"', $html);
        $this->assertMatchesRegularExpression('/Pending submissions\s*<\/p>\s*<p[^>]*>\s*2\s*<\/p>/', $html);
    }
}
