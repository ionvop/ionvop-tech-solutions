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

            $messages[] = [
                'role' => $message->isFromClient() ? 'user' : 'assistant',
                'content' => $message->body,
            ];
        }

        return $messages;
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
        $path = resource_path('ai/system-prompt.md');

        if (! File::exists($path)) {
            throw new RuntimeException('Missing assistant system prompt at '.$path);
        }

        return File::get($path);
    }

    /**
     * Read the structured output definition from disk.
     *
     * @return array<string, mixed>
     */
    private function responseFormat(): array
    {
        $path = resource_path('ai/response-format.json');

        if (! File::exists($path)) {
            throw new RuntimeException('Missing response format definition at '.$path);
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The response format definition is not valid JSON.');
        }

        return $decoded;
    }
}
