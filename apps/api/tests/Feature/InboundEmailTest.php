<?php

namespace Tests\Feature;

use App\Enums\MessageDirection;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundEmailTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-inbound-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'helpdesk.inbound_secret' => self::SECRET,
            'helpdesk.support_address' => 'support@helpdesk.test',
        ]);
    }

    /** POST a raw fixture email to the inbound webhook with the shared secret. */
    private function postEmail(string $fixture, ?string $secret = self::SECRET)
    {
        $raw = file_get_contents(__DIR__."/../Fixtures/emails/{$fixture}");

        // Build the server array directly: raw body + the secret header (Symfony
        // exposes request headers as HTTP_*; Content-Type as CONTENT_TYPE).
        $server = ['CONTENT_TYPE' => 'message/rfc822'];
        if ($secret !== null) {
            $server['HTTP_X_INBOUND_SECRET'] = $secret;
        }

        return $this->call('POST', '/api/mail/inbound', [], [], [], $server, $raw);
    }

    public function test_new_email_opens_a_ticket_with_contact_and_inbound_message(): void
    {
        $response = $this->postEmail('new.eml');

        $response->assertAccepted()->assertJson(['outcome' => 'created']);

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('contacts', 1);

        $contact = Contact::sole();
        $this->assertSame('jane@example.com', $contact->email);
        $this->assertSame('Jane Customer', $contact->name);

        $ticket = Ticket::sole();
        $this->assertSame('Cannot log in to my account', $ticket->subject);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNull($ticket->category);
        $this->assertStringStartsWith('TKT-', $ticket->reference);

        $message = Message::sole();
        $this->assertSame(MessageDirection::Inbound, $message->direction);
        $this->assertSame('fixture-new-0001@example.com', $message->message_id);
        $this->assertStringContainsString('unable to log in', $message->body_text);
    }

    public function test_reply_threads_onto_the_existing_ticket(): void
    {
        $this->postEmail('new.eml')->assertAccepted();
        $response = $this->postEmail('reply.eml');

        $response->assertAccepted()->assertJson(['outcome' => 'threaded']);

        // Same single ticket, now with two messages.
        $this->assertDatabaseCount('tickets', 1);
        $this->assertSame(2, Ticket::sole()->messages()->count());
    }

    public function test_duplicate_message_id_is_ignored(): void
    {
        $this->postEmail('new.eml')->assertAccepted();
        $response = $this->postEmail('new.eml');

        $response->assertAccepted()->assertJson(['outcome' => 'duplicate']);

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_auto_submitted_email_is_dropped(): void
    {
        $response = $this->postEmail('auto_submitted.eml');

        $response->assertAccepted()->assertJson(['outcome' => 'ignored']);

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_email_from_our_own_support_address_is_dropped_as_a_loop(): void
    {
        $response = $this->postEmail('loop.eml');

        $response->assertAccepted()->assertJson(['outcome' => 'ignored']);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_missing_or_invalid_secret_is_rejected(): void
    {
        $this->postEmail('new.eml', secret: null)->assertUnauthorized();
        $this->postEmail('new.eml', secret: 'wrong')->assertUnauthorized();

        $this->assertDatabaseCount('tickets', 0);
    }
}
