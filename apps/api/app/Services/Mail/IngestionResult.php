<?php

namespace App\Services\Mail;

use App\Models\Ticket;

/**
 * Outcome of ingesting one inbound email. `ticket` is null only when the email
 * was ignored (loop/auto-responder) — duplicates still carry the existing ticket.
 */
readonly class IngestionResult
{
    private function __construct(
        public string $outcome,
        public ?Ticket $ticket,
    ) {}

    public const CREATED = 'created';    // opened a new ticket

    public const THREADED = 'threaded';  // appended to an existing ticket

    public const DUPLICATE = 'duplicate'; // Message-ID already ingested (no-op)

    public const IGNORED = 'ignored';     // auto-responder / loop — dropped

    public static function created(Ticket $ticket): self
    {
        return new self(self::CREATED, $ticket);
    }

    public static function threaded(Ticket $ticket): self
    {
        return new self(self::THREADED, $ticket);
    }

    public static function duplicate(Ticket $ticket): self
    {
        return new self(self::DUPLICATE, $ticket);
    }

    public static function ignored(): self
    {
        return new self(self::IGNORED, null);
    }

    /** True when a new ticket or message was persisted. */
    public function isAccepted(): bool
    {
        return in_array($this->outcome, [self::CREATED, self::THREADED], true);
    }
}
