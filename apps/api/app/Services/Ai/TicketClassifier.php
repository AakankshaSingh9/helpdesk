<?php

namespace App\Services\Ai;

use App\Enums\TicketCategory;
use App\Models\Ticket;

/**
 * Classifies a ticket into a single TicketCategory via gpt-5-nano.
 *
 * Returns null when AI is off, the call fails, or the model's answer doesn't map
 * to a known category — the caller then simply leaves the ticket uncategorised
 * (the manual-helpdesk fallback).
 */
class TicketClassifier
{
    public function __construct(private OpenAiClient $client) {}

    public function classify(Ticket $ticket): ?TicketCategory
    {
        $firstInbound = $ticket->messages()
            ->where('direction', 'inbound')
            ->oldest('id')
            ->value('body_text');

        $system = <<<'PROMPT'
        You classify inbound customer support tickets into exactly ONE category:
        - general: a general question or request for information
        - technical: a technical problem, bug, error, or how-to
        - refund: a request for a refund, cancellation, or billing dispute
        Respond with ONLY the single lowercase category word — nothing else.
        PROMPT;

        $user = "Subject: {$ticket->subject}\n\nMessage:\n".($firstInbound ?? '');

        $answer = $this->client->complete($system, $user, maxTokens: 8);
        if ($answer === null) {
            return null;
        }

        // Be lenient: take the first category word the model returns.
        $normalized = strtolower(trim($answer));

        return TicketCategory::tryFrom($normalized)
            ?? collect(TicketCategory::cases())->first(
                fn (TicketCategory $c) => str_contains($normalized, $c->value),
            );
    }
}
