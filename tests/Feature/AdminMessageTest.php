<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMessageTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function message(bool $read = false, array $extra = []): ContactMessage
    {
        return ContactMessage::factory()->create($extra + ['is_read' => $read]);
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)));
    }

    public function test_opens_on_unread_with_tab_counts(): void
    {
        $this->message(false, ['subject' => 'Unread one']);
        $this->message(false);
        $this->message(true, ['subject' => 'Read one']);

        $text = $this->text($this->get('/admin/messages')->assertOk()->getContent());

        $this->assertStringContainsString('Unread one', $text);
        $this->assertStringNotContainsString('Read one', $text);
        $this->assertStringContainsString('Unread 2 Read 1 All 3', $text);
    }

    public function test_tabs_filter_and_unknown_tabs_fall_back_to_unread(): void
    {
        $this->message(false, ['subject' => 'Unread one']);
        $this->message(true, ['subject' => 'Read one']);

        $this->get('/admin/messages?show=read')->assertSee('Read one')->assertDontSee('Unread one');
        $this->get('/admin/messages?show=all')->assertSee('Read one')->assertSee('Unread one');
        $this->get('/admin/messages?show=banana')->assertOk()->assertSee('Unread one')->assertDontSee('Read one');
        $this->get('/admin/messages?show[]=x')->assertOk();
    }

    public function test_empty_states(): void
    {
        $this->get('/admin/messages')->assertOk()->assertSee('You are all caught up');
        $this->get('/admin/messages?show=all')->assertOk()->assertSee('Nobody has written to you yet');
    }

    public function test_only_unread_messages_get_the_gold_bar(): void
    {
        $this->message(false);
        $this->assertSame(1, substr_count($this->get('/admin/messages')->getContent(), 'shadow-[inset_4px_0_0_0_var(--color-gold-500)]'));

        ContactMessage::query()->update(['is_read' => true]);
        $this->assertSame(0, substr_count($this->get('/admin/messages?show=all')->getContent(), 'shadow-[inset_4px_0_0_0_var(--color-gold-500)]'));
    }

    public function test_the_newest_message_comes_first(): void
    {
        $this->message(false, ['subject' => 'Older', 'created_at' => now()->subDay()]);
        $this->message(false, ['subject' => 'Newer']);

        $html = $this->get('/admin/messages')->getContent();

        $this->assertTrue(strpos($html, 'Newer') < strpos($html, 'Older'));
    }

    public function test_the_full_message_and_a_reply_link_are_shown_and_text_is_escaped(): void
    {
        $this->message(false, [
            'name' => '<b>Mallory</b>', 'email' => 'mal@example.test', 'subject' => 'Opening hours',
            'message' => "<script>alert(1)</script>\nSecond line",
        ]);

        $html = $this->get('/admin/messages')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>Mallory</b>', $html);
        $this->assertStringContainsString('Second line', $html);
        $this->assertStringContainsString('href="mailto:mal@example.test?subject=Re%3A%20Opening%20hours"', $html);
    }

    public function test_a_message_without_a_subject_still_shows_and_replies_sensibly(): void
    {
        $this->message(false, ['subject' => null, 'email' => 'a@example.test']);

        $html = $this->get('/admin/messages')->getContent();

        $this->assertStringContainsString('(no subject)', $html);
        $this->assertStringContainsString('subject=Re%3A%20your%20message', $html);
    }

    public function test_buttons_match_the_read_state(): void
    {
        $this->message(false);
        $this->assertStringContainsString('>Mark as read<', $this->get('/admin/messages')->getContent());

        $this->message(true);
        $html = $this->get('/admin/messages?show=read')->getContent();
        $this->assertStringContainsString('>Mark as unread<', $html);
        $this->assertStringNotContainsString('>Mark as read<', $html);
    }

    public function test_mark_as_read_and_unread(): void
    {
        $message = $this->message(false);

        $this->patch(route('admin.messages.update', $message), ['is_read' => 1])->assertRedirect()->assertSessionHas('status');
        $this->assertTrue($message->fresh()->is_read);

        $this->patch(route('admin.messages.update', $message), ['is_read' => 0])->assertRedirect();
        $this->assertFalse($message->fresh()->is_read);
    }

    public function test_only_a_yes_or_no_is_accepted_and_nothing_else_changes(): void
    {
        $message = $this->message(false, ['name' => 'Original', 'message' => 'Original text here']);

        foreach (['', 'maybe', 'yes', ['1']] as $bad) {
            $this->from('/admin/messages')->patch(route('admin.messages.update', $message), ['is_read' => $bad])->assertSessionHasErrors('is_read');
        }
        $this->patch(route('admin.messages.update', $message), ['is_read' => 1, 'name' => 'Hacked', 'message' => 'Hacked']);

        $fresh = $message->fresh();
        $this->assertSame('Original', $fresh->name);
        $this->assertSame('Original text here', $fresh->message);
    }

    public function test_delete_and_unknown_ids(): void
    {
        $message = $this->message();

        $this->delete(route('admin.messages.destroy', $message))->assertRedirect()->assertSessionHas('status', 'Message deleted.');
        $this->assertModelMissing($message);
        $this->patch('/admin/messages/9999', ['is_read' => 1])->assertNotFound();
        $this->delete('/admin/messages/9999')->assertNotFound();
    }

    public function test_viewing_the_list_does_not_mark_anything_as_read(): void
    {
        $message = $this->message(false);

        $this->get('/admin/messages');

        $this->assertFalse($message->fresh()->is_read);
    }

    public function test_pagination_keeps_the_tab_and_steps_back_when_a_page_empties(): void
    {
        ContactMessage::factory()->count(16)->create();

        $this->assertStringContainsString('show=unread', $this->get('/admin/messages')->getContent());
        $this->get('/admin/messages?show=unread&page=2')->assertOk();

        ContactMessage::latest()->latest('id')->skip(15)->first()->update(['is_read' => true]);
        $this->get('/admin/messages?show=unread&page=2')->assertRedirect();
    }

    public function test_page_runs_a_fixed_number_of_queries(): void
    {
        $count = function () {
            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });
            $this->get('/admin/messages?show=all')->assertOk();

            return $queries;
        };

        ContactMessage::factory()->count(2)->create();
        $few = $count();
        ContactMessage::factory()->count(10)->create();

        $this->assertSame($few, $count());
    }

    public function test_sidebar_link_is_live_and_the_dashboard_counts_unread(): void
    {
        $this->message(false);
        $this->message(false);
        $this->message(true);

        $html = $this->get('/admin')->getContent();

        $this->assertStringContainsString('href="'.route('admin.messages.index').'"', $html);
        $this->assertMatchesRegularExpression('/Unread messages\s*<\/p>\s*<p[^>]*>\s*2\s*<\/p>/', $html);
    }
}
