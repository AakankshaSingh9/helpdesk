<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support address
    |--------------------------------------------------------------------------
    |
    | The mailbox customers write to. Used as the loop guard for inbound email
    | (mail appearing to come from this address is dropped rather than turned
    | into a ticket) and, later, as the From address on outbound replies.
    |
    */

    'support_address' => env('HELPDESK_SUPPORT_ADDRESS', ''),

    /*
    |--------------------------------------------------------------------------
    | Inbound webhook secret
    |--------------------------------------------------------------------------
    |
    | Shared secret required on POST /api/mail/inbound. The endpoint is
    | unauthenticated (machine-to-machine) but writes rows, so callers must
    | present this value in the X-Inbound-Secret header. Leave blank to disable
    | the endpoint entirely (every request is rejected).
    |
    */

    'inbound_secret' => env('MAIL_INBOUND_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Agent signature
    |--------------------------------------------------------------------------
    |
    | Name that outbound replies are signed with (auto-resolve replies and the
    | AI-polished agent drafts). Purely presentational — the From address is
    | still `support_address` above.
    |
    */

    'agent_name' => env('HELPDESK_AGENT_NAME', 'Aakanksha'),

];
