<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\Ai\TicketClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Classifies a freshly-opened ticket into a category, off the request path.
 *
 * Dispatched (queued) when an inbound email opens a new ticket, so intake stays
 * fast/non-blocking. A null classification (AI off/failed) simply leaves the
 * ticket uncategorised.
 */
class ClassifyTicket implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function handle(TicketClassifier $classifier): void
    {
        $category = $classifier->classify($this->ticket);

        if ($category !== null) {
            $this->ticket->update(['category' => $category]);
        }
    }
}
