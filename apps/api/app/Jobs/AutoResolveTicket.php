<?php

namespace App\Jobs;

use App\Enums\MessageDirection;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Ai\KnowledgeBaseResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Tries to auto-resolve a new ticket from the knowledge-base file, off the
 * request path (queued on arrival).
 *
 * When the KB confidently answers, we record the grounded reply as an outbound
 * message and mark the ticket resolved. Otherwise nothing changes and the ticket
 * waits for a human. (Actually emailing the reply is deferred Phase 2 outbound
 * work — the reply is recorded on the thread.)
 */
class AutoResolveTicket implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function handle(KnowledgeBaseResolver $resolver): void
    {
        // Don't act on a ticket a human/agent has already moved on.
        if ($this->ticket->status !== TicketStatus::Open) {
            return;
        }

        $answer = $resolver->resolve($this->ticket);
        if ($answer === null) {
            return; // KB can't answer — leave it open for an agent.
        }

        $support = (string) config('helpdesk.support_address');

        $this->ticket->messages()->create([
            'direction' => MessageDirection::Outbound,
            'from_email' => $support !== '' ? $support : 'support@localhost',
            'from_name' => 'Support (auto-reply)',
            'body_text' => $answer,
            'message_id' => '<auto-'.Str::uuid().'@helpdesk>',
        ]);

        $this->ticket->update(['status' => TicketStatus::Resolved]);
    }
}
