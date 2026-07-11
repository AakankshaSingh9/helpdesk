<?php

namespace App\Services\Mail;

/**
 * Provider-agnostic value object for a parsed inbound email. Produced by
 * RawEmailParser and consumed by InboundEmailService, so the ingestion logic
 * never depends on the concrete MIME library (or, later, on IMAP).
 */
readonly class ParsedEmail
{
    /**
     * @param  array<int, string>  $references  Message-IDs from the References header.
     * @param  array<string, string>  $headers  Lower-cased header name => raw value.
     */
    public function __construct(
        public string $messageId,
        public string $fromEmail,
        public ?string $fromName,
        public string $subject,
        public string $textBody,
        public ?string $inReplyTo = null,
        public array $references = [],
        public array $headers = [],
    ) {}

    /** Case-insensitive header lookup; null when absent. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
