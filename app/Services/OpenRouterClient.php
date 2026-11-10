<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenRouterClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    /**
     * Build a client from the application configuration.
     */
    public static function fromConfig(): self
    {
        return new self(
            baseUrl: (string) config('services.openrouter.base_url'),
            apiKey: (string) config('services.openrouter.api_key'),
            model: (string) config('services.openrouter.model'),
            timeout: (int) config('services.openrouter.timeout'),
        );
    }

    /**
     * Send a chat completion request and return the decoded JSON payload.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $responseFormat
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function complete(array $messages, array $responseFormat): array
    {
        $response = $this->request()
            ->post('/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'response_format' => $responseFormat,
                'reasoning' => [
                    'effort' => 'none',
                ],
                'provider' => [
                    'require_parameters' => true,
                ],
            ])
            ->throw();

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenRouter returned an empty completion.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenRouter returned a completion that is not valid JSON.');
        }

        return $decoded;
    }

    /**
     * Build the configured HTTP request.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->withHeaders([
                'HTTP-Referer' => (string) config('app.url'),
                'X-Title' => (string) config('app.name'),
            ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout($this->timeout)
            ->retry(
                times: 2,
                sleepMilliseconds: 250,
                when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->status() === 429)),
            );
    }
}
