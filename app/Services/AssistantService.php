<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class AssistantService
{
    /**
     * The number of previous messages sent to the model as context.
     */
    private const HISTORY_LIMIT = 40;

    /**
     * The actions the assistant is allowed to apply to a conversation.
     *
     * @var list<string>
     */
    private const ACTIONS = ['set_client_name', 'set_client_email', 'set_conversation_label'];

    public function __construct(private readonly OpenRouterClient $client) {}

    /**
     * Ask the assistant for the next turn and persist the result.
     *
     * @throws RuntimeException
     */
    public function respond(Conversation $conversation): Message
    {
        $payload = $this->client->complete(
            $this->buildMessages($conversation),
            $this->responseFormat(),
        );

        $reply = $payload['reply'] ?? null;
        $actions = $payload['actions'] ?? [];

        if (! is_string($reply) || trim($reply) === '') {
            throw new RuntimeException('The assistant returned no reply.');
        }

        if (! is_array($actions)) {
            throw new RuntimeException('The assistant returned malformed actions.');
        }

        return DB::transaction(function () use ($conversation, $reply, $actions): Message {
            $this->applyActions($conversation, $actions);

            $message = $conversation->messages()->create([
                'role' => Message::ROLE_ASSISTANT,
                'type' => 'text',
                'body' => trim($reply),
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });
    }

    /**
     * Ask the assistant for an administrative summary of the conversation.
     *
     * @throws RuntimeException
     */
    public function summarize(Conversation $conversation): string
    {
        $payload = $this->client->complete(
            $this->buildSummaryMessages($conversation),
            $this->summaryFormat(),
        );

        $summary = $payload['summary'] ?? null;

        if (! is_string($summary) || trim($summary) === '') {
            throw new RuntimeException('The assistant returned no summary.');
        }

        return trim($summary);
    }

    /**
     * Build the message list used to summarize a conversation.
     *
     * @return list<array{role: string, content: string}>
     */
    private function buildSummaryMessages(Conversation $conversation): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->readResource('ai/summary-prompt.md')],
            ['role' => 'system', 'content' => $this->stateSummary($conversation)],
        ];

        $history = $conversation->messages()->orderBy('id')->get();

        foreach ($history as $message) {
            if ($message->type !== 'text' || blank($message->body)) {
                continue;
            }

            $messages[] = $this->historyEntry($message);
        }

        return $messages;
    }

    /**
     * Read the summary output definition from disk.
     *
     * @return array<string, mixed>
     */
    private function summaryFormat(): array
    {
        return $this->readJsonResource('ai/summary-format.json');
    }

    /**
     * Build the message list sent to the model.
     *
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(Conversation $conversation): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'system', 'content' => $this->stateSummary($conversation)],
        ];

        $history = $conversation->messages()
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse();

        foreach ($history as $message) {
            if ($message->type !== 'text' || blank($message->body)) {
                continue;
            }

            $messages[] = $this->historyEntry($message);
        }

        return $messages;
    }

    /**
     * Convert a stored message into a model-facing history entry.
     *
     * Both the client and ionvop are human participants, so they are sent as
     * `user` turns and labelled with a prefix. Only the assistant speaks as
     * the assistant.
     *
     * @return array{role: string, content: string}
     */
    private function historyEntry(Message $message): array
    {
        if ($message->isFromClient()) {
            return ['role' => 'user', 'content' => '[CLIENT]: '.$message->body];
        }

        if ($message->isFromAdmin()) {
            return ['role' => 'user', 'content' => '[IONVOP]: '.$message->body];
        }

        return ['role' => 'assistant', 'content' => $message->body];
    }

    /**
     * Describe what the assistant already knows about the conversation.
     */
    private function stateSummary(Conversation $conversation): string
    {
        return implode("\n", [
            'Current conversation state:',
            '- Client name: '.($conversation->client_name ?? 'unknown'),
            '- Client contact: '.($conversation->client_email ?? 'unknown'),
            '- Conversation label: '.($conversation->label ?? 'not set'),
            '- Project stage: '.$conversation->stage,
        ]);
    }

    /**
     * Apply the actions returned by the assistant.
     *
     * @param  array<int, mixed>  $actions
     */
    private function applyActions(Conversation $conversation, array $actions): void
    {
        $changes = [];

        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $type = $action['type'] ?? null;
            $value = $action['value'] ?? null;

            if (! is_string($type) || ! in_array($type, self::ACTIONS, true)) {
                continue;
            }

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $value = Str::limit(trim($value), 255, '');

            $changes[match ($type) {
                'set_client_name' => 'client_name',
                'set_client_email' => 'client_email',
                'set_conversation_label' => 'label',
            }] = $value;
        }

        if ($changes !== []) {
            $conversation->forceFill($changes)->save();
        }
    }

    /**
     * Read the system prompt from disk.
     */
    private function systemPrompt(): string
    {
        return $this->readResource('ai/system-prompt.md');
    }

    /**
     * Read the structured output definition from disk.
     *
     * @return array<string, mixed>
     */
    private function responseFormat(): array
    {
        return $this->readJsonResource('ai/response-format.json');
    }

    /**
     * Read a text resource from the `resources` directory.
     */
    private function readResource(string $relativePath): string
    {
        $path = resource_path($relativePath);

        if (! File::exists($path)) {
            throw new RuntimeException('Missing assistant resource at '.$path);
        }

        return File::get($path);
    }

    /**
     * Read and decode a JSON resource from the `resources` directory.
     *
     * @return array<string, mixed>
     */
    private function readJsonResource(string $relativePath): array
    {
        $decoded = json_decode($this->readResource($relativePath), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The resource at '.$relativePath.' is not valid JSON.');
        }

        return $decoded;
    }
}
