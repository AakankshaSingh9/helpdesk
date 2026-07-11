<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin REST wrapper around the OpenAI Chat Completions API (gpt-5-nano by
 * default). Deliberately fail-soft: every method returns null on any problem —
 * disabled AI, missing key, network/HTTP error, or an unexpected payload — so
 * callers always have a clean "no result" branch to degrade to.
 */
class OpenAiClient
{
    /** AI is usable only when enabled and a key is present. */
    public function enabled(): bool
    {
        return (bool) config('ai.enabled') && filled(config('ai.openai.key'));
    }

    /**
     * Run a single system+user chat completion and return the assistant text,
     * or null if AI is off or anything goes wrong.
     */
    public function complete(string $system, string $user, int $maxTokens = 600): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('ai.openai.key'))
                ->baseUrl((string) config('ai.openai.base_url'))
                ->timeout(30)
                // Retry only transient failures — network errors and 5xx. A 4xx
                // (e.g. 429 insufficient_quota, 401 bad key) won't fix itself on
                // retry, so we fail fast instead of burning extra API calls.
                ->retry(2, 250, function (Throwable $e) {
                    return $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->serverError());
                }, throw: false)
                ->post('/chat/completions', [
                    'model' => config('ai.openai.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    // GPT-5 family uses max_completion_tokens (not max_tokens).
                    'max_completion_tokens' => $maxTokens,
                ]);

            if ($response->failed()) {
                Log::warning('OpenAI request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $text = $response->json('choices.0.message.content');

            return is_string($text) && trim($text) !== '' ? trim($text) : null;
        } catch (Throwable $e) {
            Log::warning('OpenAI request threw', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
