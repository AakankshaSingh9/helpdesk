<?php

namespace App\Services\Mail;

use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Message;

/**
 * Turns a raw RFC822 message into a ParsedEmail. The only place that touches the
 * concrete MIME library (zbateson/mail-mime-parser), so it can be swapped without
 * changing the ingestion logic.
 */
class RawEmailParser
{
    public function parse(string $raw): ParsedEmail
    {
        $message = Message::from($raw, true);

        $from = $message->getHeader('From');
        $fromEmail = $from instanceof AddressHeader ? (string) $from->getEmail() : '';
        $fromName = $from instanceof AddressHeader ? $from->getPersonName() : null;

        // Prefer the plain-text part; fall back to stripping the HTML part.
        $text = $message->getTextContent();
        if ($text === null || trim($text) === '') {
            $html = $message->getHtmlContent();
            $text = $html !== null ? trim(html_entity_decode(strip_tags($html))) : '';
        }

        return new ParsedEmail(
            messageId: $this->normalizeId((string) $message->getHeaderValue('Message-ID')),
            fromEmail: strtolower(trim($fromEmail)),
            fromName: $fromName !== null && trim($fromName) !== '' ? trim($fromName) : null,
            subject: (string) $message->getSubject(),
            textBody: (string) $text,
            inReplyTo: $this->normalizeId($message->getHeaderValue('In-Reply-To')),
            references: $this->parseReferences($message->getHeaderValue('References')),
            headers: $this->collectHeaders($message),
        );
    }

    /** Collapse angle-bracket ids to a bare, comparable form: `<x@y>` -> `x@y`. */
    private function normalizeId(?string $id): ?string
    {
        if ($id === null || trim($id) === '') {
            return null;
        }

        return trim($id, " \t\r\n<>");
    }

    /**
     * The References header is a whitespace-separated list of Message-IDs.
     *
     * @return array<int, string>
     */
    private function parseReferences(?string $references): array
    {
        if ($references === null || trim($references) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $id) => $this->normalizeId($id),
            preg_split('/\s+/', trim($references)) ?: [],
        )));
    }

    /**
     * Flatten all headers to a lower-cased name => value map for cheap lookups
     * (loop-prevention checks read Auto-Submitted / Precedence from here).
     *
     * @return array<string, string>
     */
    private function collectHeaders(Message $message): array
    {
        $headers = [];
        foreach ($message->getAllHeaders() as $header) {
            $headers[strtolower($header->getName())] = $header->getValue();
        }

        return $headers;
    }
}
