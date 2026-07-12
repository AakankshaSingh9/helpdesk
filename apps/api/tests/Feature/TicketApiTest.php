<?php

namespace Tests\Feature;

use App\Enums\MessageDirection;
use App\Mail\TicketReplyMail;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_endpoints_require_authentication(): void
    {
        $this->getJson('/api/tickets')->assertUnauthorized();

        $ticket = Ticket::factory()->create();
        $this->getJson("/api/tickets/{$ticket->id}")->assertUnauthorized();
    }

    public function test_index_lists_tickets_with_contact_and_message_count(): void
    {
        $ticket = Ticket::factory()->create();
        Message::factory()->count(2)->create(['ticket_id' => $ticket->id]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.reference', $ticket->reference)
            ->assertJsonPath('data.0.messageCount', 2)
            ->assertJsonPath('data.0.contact.email', $ticket->contact->email);
    }

    public function test_index_sorts_by_subject_ascending_on_the_server(): void
    {
        Ticket::factory()->create(['subject' => 'Zebra issue']);
        Ticket::factory()->create(['subject' => 'Apple issue']);
        Ticket::factory()->create(['subject' => 'Mango issue']);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tickets?sort=subject&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Apple issue')
            ->assertJsonPath('data.1.subject', 'Mango issue')
            ->assertJsonPath('data.2.subject', 'Zebra issue');
    }

    public function test_index_defaults_to_most_recently_updated_first(): void
    {
        $older = Ticket::factory()->create(['updated_at' => now()->subDay()]);
        $newer = Ticket::factory()->create(['updated_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.reference', $newer->reference)
            ->assertJsonPath('data.1.reference', $older->reference);
    }

    public function test_index_paginates_ten_per_page_by_default(): void
    {
        Ticket::factory()->count(14)->create();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 14)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_index_returns_the_requested_page(): void
    {
        Ticket::factory()->count(14)->create();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tickets?page=2')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_index_search_matches_subject_reference_and_contact(): void
    {
        $contact = Contact::factory()->create(['name' => 'Zephyr Global']);
        Ticket::factory()->create(['subject' => 'Refund for order 42', 'contact_id' => $contact->id]);
        Ticket::factory()->create(['subject' => 'Password reset help']);

        $agent = User::factory()->create();

        // Match by subject term.
        $this->actingAs($agent)->getJson('/api/tickets?search=refund')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Refund for order 42');

        // Match by contact name.
        $this->actingAs($agent)->getJson('/api/tickets?search=zephyr')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Refund for order 42');
    }

    public function test_index_rejects_an_unknown_sort_column(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/tickets?sort=password&direction=asc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tickets?sort=subject&direction=sideways')
            ->assertStatus(422)
            ->assertJsonValidationErrors('direction');
    }

    public function test_show_returns_the_message_thread(): void
    {
        $ticket = Ticket::factory()->create();
        Message::factory()->create([
            'ticket_id' => $ticket->id,
            'body_text' => 'First message body',
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.reference', $ticket->reference)
            ->assertJsonPath('data.messages.0.body', 'First message body');
    }

    public function test_a_ticket_can_be_assigned_to_an_agent(): void
    {
        $ticket = Ticket::factory()->create(['assigned_to' => null]);
        $agent = User::factory()->create(['name' => 'Dana Agent']);

        $this->actingAs(User::factory()->create())
            ->patchJson("/api/tickets/{$ticket->id}", ['assigned_to' => $agent->id])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $agent->id)
            ->assertJsonPath('data.assignee.name', 'Dana Agent');

        $this->assertSame($agent->id, $ticket->fresh()->assigned_to);
    }

    public function test_a_ticket_can_be_unassigned(): void
    {
        $agent = User::factory()->create();
        $ticket = Ticket::factory()->create(['assigned_to' => $agent->id]);

        $this->actingAs(User::factory()->create())
            ->patchJson("/api/tickets/{$ticket->id}", ['assigned_to' => null])
            ->assertOk()
            ->assertJsonPath('data.assignee', null);

        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_assigning_to_a_nonexistent_user_is_rejected(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patchJson("/api/tickets/{$ticket->id}", ['assigned_to' => 'does-not-exist'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_assignment_requires_authentication(): void
    {
        $ticket = Ticket::factory()->create();

        $this->patchJson("/api/tickets/{$ticket->id}", ['assigned_to' => null])
            ->assertUnauthorized();
    }

    public function test_agents_endpoint_lists_assignable_staff(): void
    {
        User::factory()->create(['name' => 'Bea']);
        User::factory()->create(['name' => 'Ada']);

        $this->actingAs(User::factory()->create(['name' => 'Cid']))
            ->getJson('/api/agents')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Ada'); // ordered by name
    }

    public function test_reply_emails_the_contact_and_records_an_outbound_message(): void
    {
        Mail::fake();

        $contact = Contact::factory()->create(['email' => 'jo@customer.test']);
        $ticket = Ticket::factory()->create(['contact_id' => $contact->id, 'subject' => 'Login broken']);
        // The customer's original inbound message the reply should thread onto.
        $inbound = Message::factory()->create([
            'ticket_id' => $ticket->id,
            'direction' => MessageDirection::Inbound,
            'message_id' => '<orig-123@customer.test>',
        ]);

        $this->actingAs(User::factory()->create(['name' => 'Ada']))
            ->postJson("/api/tickets/{$ticket->id}/reply", ['body' => 'Try resetting your password.'])
            ->assertCreated()
            ->assertJsonPath('data.direction', 'outbound')
            ->assertJsonPath('data.fromName', 'Ada')
            ->assertJsonPath('data.body', 'Try resetting your password.');

        $this->assertDatabaseHas('messages', [
            'ticket_id' => $ticket->id,
            'direction' => MessageDirection::Outbound->value,
            'body_text' => 'Try resetting your password.',
            'in_reply_to' => $inbound->message_id,
        ]);

        Mail::assertSent(TicketReplyMail::class, fn (TicketReplyMail $mail) => $mail->hasTo('jo@customer.test'));
    }

    public function test_reply_requires_a_body(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/tickets/{$ticket->id}/reply", ['body' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    public function test_reply_rejects_a_ticket_with_no_contact_email(): void
    {
        Mail::fake();

        // A contact with no email address — nothing to reply to.
        $contact = Contact::factory()->create(['email' => '']);
        $ticket = Ticket::factory()->create(['contact_id' => $contact->id]);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/tickets/{$ticket->id}/reply", ['body' => 'Hello?'])
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_reply_requires_authentication(): void
    {
        $ticket = Ticket::factory()->create();

        $this->postJson("/api/tickets/{$ticket->id}/reply", ['body' => 'Hi'])
            ->assertUnauthorized();
    }
}
