<?php

namespace App\Enums;

/**
 * Which way a message travelled: Inbound from the customer, Outbound from the
 * helpdesk (agent reply or, later, an AI-drafted auto-reply).
 */
enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
