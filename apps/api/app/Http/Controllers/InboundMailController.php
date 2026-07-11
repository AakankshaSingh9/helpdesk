<?php

namespace App\Http\Controllers;

use App\Services\Mail\InboundEmailService;
use App\Services\Mail\RawEmailParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inbound-email webhook. A mail provider (or a manual curl of a .eml file) POSTs
 * a raw RFC822 message here and it becomes a ticket.
 *
 * Unauthenticated by design — there is no user session behind an SMTP relay — so
 * access is gated by a shared secret in the X-Inbound-Secret header, matched
 * against config('helpdesk.inbound_secret'), plus route throttling.
 */
class InboundMailController extends Controller
{
    public function store(Request $request, RawEmailParser $parser, InboundEmailService $service): JsonResponse
    {
        $this->authorizeRequest($request);

        $raw = $request->getContent();
        if (trim($raw) === '') {
            return response()->json(['message' => 'Empty request body.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $service->ingest($parser->parse($raw));

        // 202 for anything we acted on or deliberately dropped; the caller has
        // nothing to retry. The body reports what happened for observability.
        return response()->json([
            'outcome' => $result->outcome,
            'reference' => $result->ticket?->reference,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Constant-time secret check. A blank configured secret disables the endpoint
     * (fail closed) rather than allowing everything through.
     */
    private function authorizeRequest(Request $request): void
    {
        $expected = (string) config('helpdesk.inbound_secret');
        $provided = (string) $request->header('X-Inbound-Secret', '');

        abort_if(
            $expected === '' || ! hash_equals($expected, $provided),
            Response::HTTP_UNAUTHORIZED,
            'Invalid inbound mail secret.',
        );
    }
}
