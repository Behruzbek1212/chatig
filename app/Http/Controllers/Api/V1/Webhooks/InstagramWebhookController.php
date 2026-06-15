<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingInstagramMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InstagramWebhookController extends Controller
{
    /**
     * Webhook verification handshake (Meta GET request).
     */
    public function verify(Request $request): Response
    {
        if ($request->query('hub_mode') === 'subscribe'
            && $request->query('hub_verify_token') === config('chatig.instagram.verify_token')) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Receive events. Persist nothing heavy here — dispatch a job and return
     * 200 immediately (Meta retries on slow/failed responses).
     */
    public function handle(Request $request): SymfonyResponse
    {
        foreach ($request->input('entry', []) as $entry) {
            $recipientId = $entry['id'] ?? null;

            foreach ($entry['messaging'] ?? [] as $event) {
                $text = $event['message']['text'] ?? null;
                $isEcho = $event['message']['is_echo'] ?? false;
                $senderId = $event['sender']['id'] ?? null;
                $mid = $event['message']['mid'] ?? null;

                if (! $text || $isEcho || ! $senderId || ! $recipientId) {
                    continue;
                }

                ProcessIncomingInstagramMessage::dispatch(
                    instagramAccountId: (string) $recipientId,
                    senderId: (string) $senderId,
                    text: (string) $text,
                    messageId: $mid,
                );
            }
        }

        return response()->json(['received' => true]);
    }
}
