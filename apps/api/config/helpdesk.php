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

];
