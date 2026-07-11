<?php

namespace Tests\Feature;

use App\Enums\MessageDirection;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Jobs\AutoResolveTicket;
use App\Jobs\ClassifyTicket;
use App\Models\Message;
use App\Models\Ticket;
use App\Services\Ai\KnowledgeBaseResolver;
use App\Services\Ai\TicketClassifier;
use App\Services\Mail\InboundEmailService;
use App\Services\Mail\RawEmailParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function rawEmail(string $messageId, string $subject, string $body): string
    {
        return "From: Jane <jane@example.com>\r\n".
            "To: support@helpdesk.test\r\n".
            "Subject: {$subject}\r\n".
            "Message-ID: <{$messageId}@example.com>\r\n".
            "Content-Type: text/plain; charset=UTF-8\r\n\r\n".
            $body."\r\n";
    }

    public function test_a_new_ticket_queues_classification_and_autoresolve_without_blocking(): void
    {
        Queue::fake();

        $raw = $this->rawEmail('ai-1', 'App keeps crashing', 'The app crashes on launch.');
        app(InboundEmailService::class)->ingest(app(RawEmailParser::class)->parse($raw));

        Queue::assertPushed(ClassifyTicket::class, 1);
        Queue::assertPushed(AutoResolveTicket::class, 1);
    }

    public function test_a_threaded_reply_does_not_re_queue_the_pipeline(): void
    {
        Queue::fake();
        $parser = app(RawEmailParser::class);
        $service = app(InboundEmailService::class);

        $service->ingest($parser->parse($this->rawEmail('ai-2', 'Help', 'First message.')));

        // A reply referencing the first message threads onto the same ticket.
        $reply = "From: Jane <jane@example.com>\r\nTo: support@helpdesk.test\r\n".
            "Subject: Re: Help\r\nMessage-ID: <ai-3@example.com>\r\n".
            "In-Reply-To: <ai-2@example.com>\r\nContent-Type: text/plain\r\n\r\nAnother line.\r\n";
        $service->ingest($parser->parse($reply));

        // Pipeline fires once (for the new ticket), not again for the reply.
        Queue::assertPushed(ClassifyTicket::class, 1);
        Queue::assertPushed(AutoResolveTicket::class, 1);
    }

    public function test_classify_job_sets_the_category_from_the_model(): void
    {
        config(['ai.enabled' => true, 'ai.openai.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'technical']]],
        ])]);

        $ticket = Ticket::factory()->create(['category' => null]);
        Message::factory()->create([
            'ticket_id' => $ticket->id,
            'direction' => MessageDirection::Inbound,
            'body_text' => 'The app crashes on launch.',
        ]);

        (new ClassifyTicket($ticket))->handle(app(TicketClassifier::class));

        $this->assertSame(TicketCategory::Technical, $ticket->fresh()->category);
    }

    public function test_classify_leaves_category_null_when_ai_is_off(): void
    {
        config(['ai.enabled' => false]);
        Http::fake();

        $ticket = Ticket::factory()->create(['category' => null]);
        Message::factory()->create(['ticket_id' => $ticket->id, 'direction' => MessageDirection::Inbound]);

        (new ClassifyTicket($ticket))->handle(app(TicketClassifier::class));

        $this->assertNull($ticket->fresh()->category);
        Http::assertNothingSent();
    }

    public function test_autoresolve_replies_and_resolves_when_the_kb_answers(): void
    {
        // Uses the repo's real storage/app/knowledge-base.md (the config default).
        config(['ai.enabled' => true, 'ai.openai.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => "Click 'Forgot password' and we'll email you a reset link."]]],
        ])]);

        $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);
        Message::factory()->create([
            'ticket_id' => $ticket->id,
            'direction' => MessageDirection::Inbound,
            'body_text' => 'How do I reset my password?',
        ]);

        (new AutoResolveTicket($ticket))->handle(app(KnowledgeBaseResolver::class));

        $ticket->refresh();
        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertDatabaseHas('messages', [
            'ticket_id' => $ticket->id,
            'direction' => 'outbound',
        ]);
    }

    public function test_autoresolve_leaves_ticket_open_when_kb_cannot_answer(): void
    {
        config(['ai.enabled' => true, 'ai.openai.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'NO_ANSWER']]],
        ])]);

        $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);
        Message::factory()->create([
            'ticket_id' => $ticket->id,
            'direction' => MessageDirection::Inbound,
            'body_text' => 'Can you integrate with our custom ERP from 1998?',
        ]);

        (new AutoResolveTicket($ticket))->handle(app(KnowledgeBaseResolver::class));

        $ticket->refresh();
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertDatabaseMissing('messages', [
            'ticket_id' => $ticket->id,
            'direction' => 'outbound',
        ]);
    }
}
