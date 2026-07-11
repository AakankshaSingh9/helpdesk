<?php

namespace App\Services\Ai;

use App\Models\Ticket;

/**
 * Improves an agent's draft reply (clarity, tone, grammar) via gpt-5-nano.
 *
 * Returns null when AI is unavailable or the call fails, so the caller keeps the
 * agent's original text — polishing is a convenience, never a gate on replying.
 */
class ReplyPolisher
{
    public function __construct(private OpenAiClient $client) {}

    public function polish(string $draft, ?Ticket $ticket = null): ?string
    {
        $draft = trim($draft);
        if ($draft === '') {
            return null;
        }

        $agent = (string) config('helpdesk.agent_name');
        $greetingName = $ticket?->contact?->firstName() ?? 'there';

        $system = <<<PROMPT
        You are an assistant that refines a support agent's draft reply to a customer.
        Improve clarity, tone, grammar, and professionalism while preserving the
        original meaning and every concrete detail (names, numbers, steps, links).
        Do not invent facts or add a subject line.

        Return the draft as a complete, ready-to-send email that:
        - opens with the greeting "Hi {$greetingName}," on its own line;
        - has a warm, professional, customer-friendly tone;
        - is well formatted — short paragraphs separated by a blank line, and a
          numbered or bulleted list for any sequence of steps;
        - closes with a sign-off on its own lines: "Best regards," then "{$agent}".
        Return ONLY the improved reply text — no preamble, quotes, or explanation.
        PROMPT;

        $context = $ticket ? "Ticket subject: {$ticket->subject}\n\n" : '';
        $user = $context."Agent draft:\n{$draft}";

        $polished = $this->client->complete($system, $user, maxTokens: 800);

        // Guard against an empty or unchanged result.
        return $polished !== null && trim($polished) !== '' ? trim($polished) : null;
    }
}
