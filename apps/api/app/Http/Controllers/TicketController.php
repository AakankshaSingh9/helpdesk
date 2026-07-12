<?php

namespace App\Http\Controllers;

use App\Enums\MessageDirection;
use App\Http\Resources\MessageResource;
use App\Http\Resources\TicketResource;
use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use App\Services\Ai\OpenAiClient;
use App\Services\Ai\ReplyPolisher;
use App\Services\Ai\TicketSummarizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Read-only ticket views for the agent SPA.
 *
 * Authenticated (auth:sanctum) — both admins and agents currently see every
 * ticket. Assignment-based scoping (agents see only their own) is a later phase;
 * see project-scope.md.
 */
class TicketController extends Controller
{
    /**
     * Sortable columns exposed to the client, mapped to concrete (qualified) DB
     * columns. The allowlist is the whole security story here: only these keys
     * can reach `orderBy`, so no user input is ever interpolated into SQL.
     */
    private const SORTABLE = [
        'reference' => 'tickets.reference',
        'subject' => 'tickets.subject',
        'status' => 'tickets.status',
        'contact' => 'contacts.name',
        'createdAt' => 'tickets.created_at',
        'updatedAt' => 'tickets.updated_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'sort' => ['sometimes', Rule::in(array_keys(self::SORTABLE))],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $sort = $validated['sort'] ?? 'updatedAt';
        $direction = $validated['direction'] ?? 'desc';

        $tickets = Ticket::query()
            ->select('tickets.*')
            // Join contacts so we can sort/search by contact; harmless otherwise
            // (contact_id is non-null, so this never drops rows).
            ->join('contacts', 'contacts.id', '=', 'tickets.contact_id')
            ->when(
                $validated['search'] ?? null,
                fn ($query, string $term) => $query->where(function ($q) use ($term) {
                    $like = '%'.strtolower($term).'%';
                    $q->whereRaw('LOWER(tickets.subject) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(tickets.reference) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(contacts.name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(contacts.email) LIKE ?', [$like]);
                }),
            )
            ->with('contact')
            ->withCount('messages')
            ->orderBy(self::SORTABLE[$sort], $direction)
            ->orderBy('tickets.id', 'desc') // stable tiebreaker for equal values
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        return TicketResource::collection($tickets);
    }

    public function show(Ticket $ticket): TicketResource
    {
        $ticket->load(['contact', 'assignee', 'messages' => fn ($q) => $q->oldest('id')]);

        return new TicketResource($ticket);
    }

    /**
     * Update mutable ticket fields. Currently just assignment: `assigned_to` is
     * the id of the agent to hand the ticket to, or null to unassign.
     *
     * Both roles may assign for now (matching the read endpoints); tighter rules
     * — e.g. agents may only claim tickets for themselves — are a later phase.
     */
    public function update(Request $request, Ticket $ticket): TicketResource
    {
        $validated = $request->validate([
            // `assigned_to` must be present in the payload (so an accidental empty
            // request doesn't silently unassign), but may be null to unassign.
            // Any non-deleted user is assignable.
            'assigned_to' => [
                'present',
                'nullable',
                Rule::exists('user', 'id')->whereNull('deletedAt'),
            ],
        ]);

        $ticket->update(['assigned_to' => $validated['assigned_to']]);

        $ticket->load(['contact', 'assignee']);

        return new TicketResource($ticket);
    }

    /**
     * Improve an agent's draft reply with AI (gpt-5-nano) before they send it.
     *
     * `aiApplied` is false when AI is disabled or the call failed — in which case
     * `polished` is just the original draft echoed back, so the UI degrades to a
     * plain reply box.
     */
    public function polish(Request $request, Ticket $ticket, ReplyPolisher $polisher, OpenAiClient $ai): JsonResponse
    {
        $validated = $request->validate([
            'draft' => ['required', 'string', 'max:5000'],
        ]);

        $polished = $polisher->polish($validated['draft'], $ticket);

        // Distinguish "AI is switched off" from "AI is on but the call failed"
        // (network/HTTP error, exhausted quota, …) so the UI can advise the agent
        // accurately instead of telling them to enable already-enabled settings.
        $reason = $polished !== null ? null : ($ai->enabled() ? 'failed' : 'disabled');

        return response()->json([
            'data' => [
                'polished' => $polished ?? $validated['draft'],
                'aiApplied' => $polished !== null,
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Send an agent's reply to the ticket's contact and record it on the thread.
     *
     * The message is only persisted if the email actually goes out — a transport
     * failure (bad SMTP config, provider down) returns 502 and records nothing,
     * so the thread never shows a "sent" reply the customer never received.
     */
    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $ticket->loadMissing('contact');
        $to = $ticket->contact?->email;
        if ($to === null || $to === '') {
            return response()->json([
                'message' => 'This ticket has no contact email to reply to.',
            ], 422);
        }

        $agentName = (string) ($request->user()?->name ?? config('helpdesk.agent_name', 'Support'));

        // Thread onto the customer's latest inbound message so the reply lands in
        // the same conversation in their inbox.
        $lastInbound = $ticket->messages()
            ->where('direction', MessageDirection::Inbound)
            ->latest()
            ->first();

        $messageId = '<reply-'.Str::uuid().'@helpdesk>';

        try {
            Mail::to($to)->send(new TicketReplyMail(
                $ticket,
                $validated['body'],
                $agentName,
                $messageId,
                $lastInbound?->message_id,
            ));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'The reply could not be sent — check the mail configuration.',
            ], 502);
        }

        $message = $ticket->messages()->create([
            'direction' => MessageDirection::Outbound,
            'from_email' => (string) config('mail.from.address'),
            'from_name' => $agentName,
            'body_text' => $validated['body'],
            'message_id' => $messageId,
            'in_reply_to' => $lastInbound?->message_id,
        ]);

        return response()->json([
            'data' => new MessageResource($message),
        ], 201);
    }

    /**
     * Summarise the whole ticket thread so an agent can get up to speed on a
     * long conversation at a glance.
     *
     * `aiApplied` is false when AI is disabled or the call failed — `summary`
     * is then null, and the UI keeps showing the full thread. `reason`
     * distinguishes "AI is off" from "AI is on but the call failed" so the UI
     * can advise the agent accurately.
     */
    public function summarize(Ticket $ticket, TicketSummarizer $summarizer, OpenAiClient $ai): JsonResponse
    {
        $ticket->load(['contact', 'messages' => fn ($q) => $q->oldest('id')]);

        $summary = $summarizer->summarize($ticket);

        $reason = $summary !== null ? null : ($ai->enabled() ? 'failed' : 'disabled');

        return response()->json([
            'data' => [
                'summary' => $summary,
                'aiApplied' => $summary !== null,
                'reason' => $reason,
            ],
        ]);
    }
}
