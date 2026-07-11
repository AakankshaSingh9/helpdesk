<?php

namespace App\Services\Ai;

use App\Enums\MessageDirection;
use App\Models\Ticket;

/**
 * Condenses a whole ticket thread into a short, agent-friendly summary via the
 * configured LLM, so an agent can grasp a long conversation at a glance.
 *
 * Returns null when AI is unavailable or the call fails, so the caller keeps
 * the UI degrading to the full thread — the summary is a convenience, never a
 * gate on reading the ticket.
 */
class TicketSummarizer
{
    public function __construct(private OpenAiClient $client) {}

    public function summarize(Ticket $ticket): ?string
    {
        $transcript = $this->transcript($ticket);
        if ($transcript === '') {
            return null;
        }

        $system = <<<'PROMPT'
        You are an assistant that summarizes a customer-support ticket for a
        support agent who needs to get up to speed quickly. Summarize ONLY what
        the conversation actually says — never invent details, resolutions, or
        next steps that aren't there.

        Return a concise, plain-text summary in exactly this shape:

        Issue: one sentence describing what the customer needs.
        Details:
        - a short bullet for each important fact, request, or question raised
        - keep every concrete detail (names, numbers, dates, error messages, links)
        Status: one sentence on where things stand and what's outstanding.

        Keep it brief and skimmable. Use plain sentences, no marketing tone, no
        preamble, and no closing remarks — return ONLY the summary text.
        PROMPT;

        $user = "Ticket subject: {$ticket->subject}\n\nConversation:\n{$transcript}";

        $summary = $this->client->complete($system, $user, maxTokens: 600);

        return $summary !== null && trim($summary) !== '' ? trim($summary) : null;
    }

    /**
     * Flatten the ticket's messages into a readable transcript, labelling each
     * turn by who sent it. Assumes messages are already loaded (oldest first).
     */
    private function transcript(Ticket $ticket): string
    {
        return $ticket->messages
            ->map(function ($message) {
                $who = $message->direction === MessageDirection::Outbound
                    ? 'Support'
                    : ($message->from_name ?? $message->from_email ?? 'Customer');
                $body = trim((string) $message->body_text);

                return $body === '' ? null : "{$who}: {$body}";
            })
            ->filter()
            ->implode("\n\n");
    }
}
