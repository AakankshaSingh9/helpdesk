<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Ai\OpenAiClient;
use App\Services\Ai\ReplyPolisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
}
