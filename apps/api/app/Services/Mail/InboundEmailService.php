<?php

namespace App\Services\Mail;

use App\Enums\MessageDirection;
use App\Enums\TicketStatus;
use App\Jobs\AutoResolveTicket;
use App\Jobs\ClassifyTicket;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Converts a parsed inbound email into a ticket + message. This is the reusable
 * heart of email intake: today it's driven by the inbound webhook
 * (InboundMailController); a future IMAP poller calls the same ingest() method.
 *
 * Responsibilities, in order: drop auto-responders/loops, dedupe by Message-ID,
 * thread onto an existing ticket (by subject token or reply headers) or open a
 * new one, then record the inbound message.
 */
class InboundEmailService
{
    public function ingest(ParsedEmail $email): IngestionResult
    {
        if ($this->isAutomated($email)) {
            return IngestionResult::ignored();
        }

        // Idempotency: the same email may be delivered more than once.
        $existing = Message::where('message_id', $email->messageId)->first();
        if ($existing !== null) {
            return IngestionResult::duplicate($existing->ticket);
        }

        $result = DB::transaction(function () use ($email) {
            $ticket = $this->resolveTicket($email);
            $isNew = ! $ticket->exists;

            if ($isNew) {
                $ticket->save();
            }

            $this->recordMessage($ticket, $email);

            // Touch so the list can order by most-recent activity.
            $ticket->touch();

            return $isNew
                ? IngestionResult::created($ticket)
                : IngestionResult::threaded($ticket);
        });

        // A brand-new ticket kicks off the AI pipeline off the request path
        // (queued, so intake stays non-blocking): classify it, and try to
        // auto-resolve it from the knowledge base.
        if ($result->outcome === IngestionResult::CREATED && $result->ticket !== null) {
            ClassifyTicket::dispatch($result->ticket);
            AutoResolveTicket::dispatch($result->ticket);
        }

        return $result;
    }

    /**
     * Skip mail we should never turn into a ticket: auto-replies, bulk/list mail,
     * and anything sent from our own support address (loop guard).
     */
    private function isAutomated(ParsedEmail $email): bool
    {
        $autoSubmitted = strtolower((string) $email->header('Auto-Submitted'));
        if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
            return true;
        }

        $precedence = strtolower((string) $email->header('Precedence'));
        if (in_array($precedence, ['bulk', 'list', 'junk', 'auto_reply'], true)) {
            return true;
        }

        // A message purporting to come from us is almost certainly a loop.
        $support = strtolower((string) config('helpdesk.support_address'));
        if ($support !== '' && $email->fromEmail === $support) {
            return true;
        }

        return false;
    }

    /**
     * Find the ticket this email belongs to, or build (unsaved) a new one.
     *
     * Threading precedence:
     *   1. a [TKT-XXXXXX] token in the subject (our outbound replies carry it);
     *   2. In-Reply-To / References pointing at a message we've already stored;
     *   3. otherwise a brand-new ticket for the (looked-up-or-created) contact.
     */
    private function resolveTicket(ParsedEmail $email): Ticket
    {
        if (($ticket = $this->matchBySubjectToken($email->subject)) !== null) {
            return $ticket;
        }

        if (($ticket = $this->matchByReferences($email)) !== null) {
            return $ticket;
        }

        $contact = Contact::firstOrCreate(
            ['email' => $email->fromEmail],
            ['name' => $email->fromName],
        );

        // Backfill a name we didn't have before.
        if ($contact->name === null && $email->fromName !== null) {
            $contact->update(['name' => $email->fromName]);
        }

        return new Ticket([
            'subject' => $this->normalizeSubject($email->subject),
            'status' => TicketStatus::Open,
            'contact_id' => $contact->id,
        ]);
    }

    private function matchBySubjectToken(string $subject): ?Ticket
    {
        if (preg_match('/\b(TKT-[A-Z0-9]{6})\b/', strtoupper($subject), $m) !== 1) {
            return null;
        }

        return Ticket::where('reference', $m[1])->first();
    }

    private function matchByReferences(ParsedEmail $email): ?Ticket
    {
        $ids = array_filter(array_merge(
            $email->inReplyTo !== null ? [$email->inReplyTo] : [],
            $email->references,
        ));

        if ($ids === []) {
            return null;
        }

        $message = Message::whereIn('message_id', $ids)->latest('id')->first();

        return $message?->ticket;
    }

    private function recordMessage(Ticket $ticket, ParsedEmail $email): Message
    {
        return $ticket->messages()->create([
            'direction' => MessageDirection::Inbound,
            'from_email' => $email->fromEmail,
            'from_name' => $email->fromName,
            'body_text' => $email->textBody,
            'message_id' => $email->messageId,
            'in_reply_to' => $email->inReplyTo,
            'email_references' => $email->references !== [] ? implode(' ', $email->references) : null,
        ]);
    }

    /** Strip leading Re:/Fwd: noise and our own subject token for a clean title. */
    private function normalizeSubject(string $subject): string
    {
        $subject = preg_replace('/\s*\[?TKT-[A-Za-z0-9]{6}\]?\s*/', ' ', $subject) ?? $subject;
        $subject = preg_replace('/^(\s*(re|fwd|fw)\s*:\s*)+/i', '', trim($subject)) ?? $subject;
        $subject = trim($subject);

        return $subject !== '' ? $subject : '(no subject)';
    }
}
