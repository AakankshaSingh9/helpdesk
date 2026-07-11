<?php

namespace App\Enums;

/**
 * Single category per ticket (project-scope.md). Assigned by AI classification in
 * Phase 3; until then a ticket's category is null (uncategorised).
 */
enum TicketCategory: string
{
    case General = 'general';
    case Technical = 'technical';
    case Refund = 'refund';
}
