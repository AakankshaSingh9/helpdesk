<?php

namespace App\Enums;

/**
 * Lifecycle of a ticket. Mirrors project-scope.md ("Statuses: open, resolved,
 * closed"). New tickets start Open.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
