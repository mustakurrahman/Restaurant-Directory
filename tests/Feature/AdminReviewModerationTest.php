<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReviewModerationTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function review(string $status, array $extra = []): Review
    {
        return Review::factory()->create($extra + ['status' => $status]);
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)));
    }

    public function test_opens_on_pending_and_shows_tab_counts(): void
    {
        $this->review('pending', ['name' => 'Pending Pat']);
        $this->review('pending');
        $this->review('approved', ['name' => 'Approved Ann']);
        $this->review('rejected', ['name' => 'Rejected Ray']);

        $html = $this->get('/admin/reviews')->assertOk()->getContent();
        $text = $this->text($html);

        $this->assertStringContainsString('Pending Pat', $text);
        $this->assertStringNotContainsString('Approved Ann', $text);
        $this->assertStringNotContainsString('Rejected Ray', $text);
        $this->assertStringContainsString('Pending 2 Approved 1 Rejected 1 All 4', $text);
    }

    public function test_each_tab_filters_and_unknown_tabs_fall_back_to_pending(): void
    {
        $this->review('pending', ['name' => 'Pending Pat']);
        $this->review('approved', ['name' => 'Approved Ann']);
        $this->review('rejected', ['name' => 'Rejected Ray']);

        $this->get('/admin/reviews?status=approved')->assertSee('Approved Ann')->assertDontSee('Pending Pat');
        $this->get('/admin/reviews?status=rejected')->assertSee('Rejected Ray')->assertDontSee('Approved Ann');
        $this->get('/admin/reviews?status=all')->assertSee('Pending Pat')->assertSee('Approved Ann')->assertSee('Rejected Ray');
        $this->get('/admin/reviews?status=banana')->assertOk()->assertSee('Pending Pat')->assertDontSee('Approved Ann');
        $this->get('/admin/reviews?status[]=x')->assertOk();
    }

    public function test_empty_tabs_explain_themselves(): void
    {
        $this->get('/admin/reviews')->assertOk()->assertSee('Nothing is waiting for approval');
        $this->get('/admin/reviews?status=all')->assertOk()->assertSee('No reviews have been written yet');
    }

    public function test_approving_makes_the_review_public_and_updates_the_rating(): void
    {
        $restaurant = Restaurant::factory()->create(['slug' => 'chez-marie']);
        $review = $this->review('pending', ['restaurant_id' => $restaurant->id, 'name' => 'Pending Pat', 'comment' => 'Lovely dinner indeed', 'rating' => 4]);

        $this->get('/restaurant/chez-marie')->assertDontSee('Lovely dinner indeed');

        $this->patch(route('admin.reviews.update', $review), ['status' => 'approved'])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame('approved', $review->fresh()->status);
        $this->get('/restaurant/chez-marie')->assertSee('Lovely dinner indeed')->assertSee('★ 4.0');
    }

    public function test_rejecting_or_unapproving_hides_it_again(): void
    {
        $restaurant = Restaurant::factory()->create(['slug' => 'chez-marie']);
        $review = $this->review('approved', ['restaurant_id' => $restaurant->id, 'comment' => 'Lovely dinner indeed']);

        $this->patch(route('admin.reviews.update', $review), ['status' => 'rejected'])->assertRedirect();

        $this->assertSame('rejected', $review->fresh()->status);
        $this->get('/restaurant/chez-marie')->assertDontSee('Lovely dinner indeed')->assertSee('No reviews yet');
    }

    public function test_only_the_three_known_statuses_can_be_saved(): void
    {
        $review = $this->review('pending');

        foreach (['', 'published', 'APPROVED', ['approved']] as $bad) {
            $this->from('/admin/reviews')->patch(route('admin.reviews.update', $review), ['status' => $bad])->assertSessionHasErrors('status');
        }
        $this->assertSame('pending', $review->fresh()->status);
    }

    public function test_the_moderator_cannot_change_anything_but_the_status(): void
    {
        $review = $this->review('pending', ['name' => 'Original', 'comment' => 'Original comment text']);

        $this->patch(route('admin.reviews.update', $review), ['status' => 'approved', 'name' => 'Hacked', 'comment' => 'Hacked', 'rating' => 1]);

        $fresh = $review->fresh();
        $this->assertSame('Original', $fresh->name);
        $this->assertSame('Original comment text', $fresh->comment);
    }

    public function test_deleting_removes_the_review(): void
    {
        $review = $this->review('approved');

        $this->delete(route('admin.reviews.destroy', $review))->assertRedirect()->assertSessionHas('status', 'Review deleted.');

        $this->assertModelMissing($review);
    }

    public function test_unknown_review_is_404(): void
    {
        $this->patch('/admin/reviews/9999', ['status' => 'approved'])->assertNotFound();
        $this->delete('/admin/reviews/9999')->assertNotFound();
    }

    public function test_review_text_is_escaped_and_the_email_is_visible_to_the_owner_only_here(): void
    {
        $this->review('pending', ['name' => '<b>Mallory</b>', 'comment' => '<script>alert(1)</script>', 'email' => 'mal@example.test']);

        $html = $this->get('/admin/reviews')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>Mallory</b>', $html);
        $this->assertStringContainsString('mal@example.test', $html);
    }

    public function test_buttons_match_the_status_and_drafts_have_no_public_link(): void
    {
        $draft = Restaurant::factory()->create(['status' => 'draft']);
        $this->review('pending', ['restaurant_id' => $draft->id]);

        $html = $this->get('/admin/reviews')->getContent();

        $this->assertStringContainsString('>Approve<', $html);
        $this->assertStringContainsString('>Reject<', $html);
        $this->assertStringNotContainsString('View page', $html); // the restaurant is a draft

        $published = Restaurant::factory()->create(['slug' => 'live-one']);
        $this->review('approved', ['restaurant_id' => $published->id]);
        $html = $this->get('/admin/reviews?status=approved')->getContent();

        $this->assertStringNotContainsString('>Approve<', $html); // already approved
        $this->assertStringContainsString('>Reject<', $html);
        $this->assertStringContainsString('/restaurant/live-one#reviews', $html);
    }

    public function test_pagination_keeps_the_tab_and_steps_back_when_a_page_empties(): void
    {
        Review::factory()->count(16)->create(['status' => 'pending']);

        $page1 = $this->get('/admin/reviews')->getContent();
        $this->assertStringContainsString('status=pending', $page1);

        $last = $this->get('/admin/reviews?status=pending&page=2')->assertOk()->getContent();
        $this->assertSame(1, substr_count($last, '>Approve<'));

        // handle the only review on page 2, then reload page 2: it should land on page 1
        Review::where('status', 'pending')->latest()->latest('id')->skip(15)->first()->update(['status' => 'approved']);
        $this->get('/admin/reviews?status=pending&page=2')->assertRedirect();
    }

    public function test_page_runs_a_fixed_number_of_queries(): void
    {
        $count = function () {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get('/admin/reviews?status=all')->assertOk();

            return $queries;
        };

        Review::factory()->count(2)->create();
        $few = $count();
        Review::factory()->count(10)->create();

        $this->assertSame($few, $count()); // reviews load their restaurant in one query, not one each
    }

    public function test_sidebar_link_is_live_and_the_dashboard_counts_pending(): void
    {
        $this->review('pending');
        $this->review('pending');
        $this->review('approved');

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('admin.reviews.index').'"', $html);
        $this->assertStringContainsString('Pending reviews', $this->text($html));
        $this->assertMatchesRegularExpression('/Pending reviews\s*<\/p>\s*<p[^>]*>\s*2\s*<\/p>/', $html);
    }
}
