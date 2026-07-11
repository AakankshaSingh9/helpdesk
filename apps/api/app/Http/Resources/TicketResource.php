<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ticket shape for the agent SPA. Includes the contact always, and the message
 * thread only when it's been eager-loaded (the show endpoint) — so the list
 * endpoint stays lean.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'category' => $this->category?->value,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ] : null),
            'messageCount' => $this->when(isset($this->messages_count), fn () => $this->messages_count),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
