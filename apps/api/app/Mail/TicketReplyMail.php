<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * An agent's reply to a customer, emailed to the ticket's contact.
 *
 * The From address / name come from the global mail config (MAIL_FROM_*) so the
 * message is authenticated against whatever SMTP account is configured (e.g.
 * Gmail rewrites From to the authenticated user anyway). Message-ID / In-Reply-To
 * headers thread the reply onto the customer's original email in their inbox.
 */
class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public string $bodyText,
        public string $agentName,
        public string $messageId,
        public ?string $inReplyTo = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = trim((string) $this->ticket->subject);
        if ($subject === '') {
            $subject = 'Your support request';
        }
        if (! str_starts_with(strtolower($subject), 're:')) {
            $subject = 'Re: '.$subject;
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.ticket-reply',
            with: [
                'body' => $this->bodyText,
                'agentName' => $this->agentName,
                'reference' => $this->ticket->reference,
            ],
        );
    }

    public function headers(): Headers
    {
        // Laravel wraps the id in <> itself, so pass the bare id.
        $inReplyTo = $this->inReplyTo !== null ? trim($this->inReplyTo, '<>') : null;

        return new Headers(
            messageId: trim($this->messageId, '<>'),
            references: $inReplyTo !== null ? [$inReplyTo] : [],
            text: $inReplyTo !== null ? ['In-Reply-To' => '<'.$inReplyTo.'>'] : [],
        );
    }
}
