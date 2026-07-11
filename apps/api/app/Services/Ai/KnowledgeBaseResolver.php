<?php

namespace App\Services\Ai;

use App\Models\Ticket;

/**
 * Attempts to fully answer a ticket from a single knowledge-base file via
 * gpt-5-nano. Returns a grounded reply string when the KB confidently covers the
 * question, or null otherwise (KB missing/empty, AI off, call failed, or the
 * model judged the KB insufficient) — in which case the ticket stays open for a
 * human. Never guesses beyond the KB.
 */
class KnowledgeBaseResolver
{
    /** Sentinel the model returns when the KB can't answer the question. */
    private const NO_ANSWER = 'NO_ANSWER';

    public function __construct(private OpenAiClient $client) {}

    public function resolve(Ticket $ticket): ?string
    {
        $kb = $this->knowledgeBase();
        if ($kb === null) {
            return null;
        }

        $firstInbound = $ticket->messages()
            ->where('direction', 'inbound')
            ->oldest('id')
            ->value('body_text');

        if ($firstInbound === null || trim($firstInbound) === '') {
            return null;
        }

        $noAnswer = self::NO_ANSWER;
        $system = <<<PROMPT
        You are a support agent. Answer the customer's question USING ONLY the
        knowledge base below. If the knowledge base does not clearly and fully
        answer it, reply with exactly {$noAnswer} and nothing else — never
        guess or use outside knowledge.
        When you can answer, write a complete, friendly reply to the customer with
        no preamble.

        --- KNOWLEDGE BASE ---
        {$kb}
        --- END KNOWLEDGE BASE ---
        PROMPT;

        $user = "Subject: {$ticket->subject}\n\nCustomer message:\n{$firstInbound}";

        $answer = $this->client->complete($system, $user, maxTokens: 700);
        if ($answer === null) {
            return null;
        }

        $answer = trim($answer);
        if ($answer === '' || str_contains(strtoupper($answer), self::NO_ANSWER)) {
            return null;
        }

        return $answer;
    }

    /** Read the configured KB file; null when absent or empty. */
    private function knowledgeBase(): ?string
    {
        $path = (string) config('ai.kb_path');
        if ($path === '') {
            return null;
        }

        // Relative paths resolve from the API app root (base_path()).
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            return null;
        }

        $contents = trim((string) file_get_contents($path));

        return $contents !== '' ? $contents : null;
    }
}
