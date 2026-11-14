<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Rules\AllowedChatUpload;
use App\Services\AssistantDispatcher;
use App\Services\ChatUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ChatController extends Controller
{
    /**
     * The number of days the conversation cookie stays valid.
     */
    private const COOKIE_LIFETIME_DAYS = 365;

    /**
     * Show the chat page for the visitor's conversation.
     */
    public function index(Request $request): View
    {
        $conversation = $this->resolveConversation($request);

        return view('chat', [
            'conversation' => $this->conversationPayload($conversation),
            'emptyConversation' => $this->conversationPayload(null),
            'messages' => $this->transcript($conversation),
        ]);
    }

    /**
     * Store a client message and let the assistant answer whoever is waiting.
     *
     * A message may carry one uploaded file instead of, or alongside, its text.
     * The assistant answers at most one conversation per cooldown window, so this
     * request may return without a reply. The client picks it up on its next poll.
     */
    public function store(Request $request, AssistantDispatcher $dispatcher, ChatUploads $uploads): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000', 'required_without:file'],
            'file' => ['nullable', 'file', new AllowedChatUpload($uploads)],
        ]);

        $file = $request->file('file');
        $kind = $file === null ? null : $uploads->kindFor($file);
        $body = trim((string) ($validated['message'] ?? ''));

        $conversation = $this->resolveConversation($request);
        $created = [];

        if ($conversation === null) {
            $conversation = Conversation::start();
            $created[] = $conversation->messages()->orderBy('id')->firstOrFail();
        }

        $clientMessage = $conversation->messages()->create([
            'role' => Message::ROLE_CLIENT,
            'type' => $kind ?? Message::TYPE_TEXT,
            'body' => $body === '' ? null : $body,
        ]);

        if ($file !== null && $kind !== null) {
            $uploads->store($clientMessage, $file, $kind);
        }

        $conversation->forceFill([
            'last_message_at' => $clientMessage->created_at,
            'ai_pending_since' => $clientMessage->created_at,
        ])->save();

        $created[] = $clientMessage;

        $reply = $this->dispatch($dispatcher, $conversation);

        if ($reply !== null) {
            $created[] = $reply;
        }

        Cookie::queue(
            Conversation::COOKIE,
            $conversation->reference,
            self::COOKIE_LIFETIME_DAYS * 24 * 60,
        );

        return response()->json([
            'conversation' => $this->conversationPayload($conversation->refresh()),
            'messages' => array_map($this->messagePayload(...), $created),
        ]);
    }

    /**
     * Return the visitor's conversation and transcript for polling.
     *
     * Polling is the only heartbeat the application has, so each poll also gives
     * the assistant a chance to answer the conversation that has waited longest.
     * Passing `after_id` returns only the messages the browser has not rendered
     * yet, so the client can append instead of replacing the whole transcript.
     */
    public function messages(Request $request, AssistantDispatcher $dispatcher): JsonResponse
    {
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $conversation = $this->resolveConversation($request);

        if ($conversation === null) {
            return response()->json([
                'conversation' => null,
                'messages' => [],
            ]);
        }

        $this->dispatch($dispatcher, $conversation);

        return response()->json([
            'conversation' => $this->conversationPayload($conversation->refresh()),
            'messages' => $this->transcript($conversation, $validated['after_id'] ?? null),
        ]);
    }

    /**
     * Forget the visitor's conversation so they start a new one.
     *
     * Only the browser cookie is cleared. The conversation and its messages stay
     * in the database for ionvop, but the client can no longer reach them.
     */
    public function forget(): JsonResponse
    {
        Cookie::queue(Cookie::forget(Conversation::COOKIE));

        return response()->json([
            'conversation' => $this->conversationPayload(null),
            'messages' => [],
        ]);
    }

    /**
     * Find the conversation referenced by the visitor's cookie.
     */
    private function resolveConversation(Request $request): ?Conversation
    {
        $reference = $request->cookie(Conversation::COOKIE);

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        return Conversation::query()->where('reference', $reference)->first();
    }

    /**
     * Give the assistant a chance to answer, returning a reply for this conversation.
     *
     * A failure is logged and swallowed: the conversation stays pending, no cooldown
     * starts, and the next request tries again. The client is never shown an error
     * for a turn it did not ask for.
     */
    private function dispatch(AssistantDispatcher $dispatcher, Conversation $conversation): ?Message
    {
        try {
            $result = $dispatcher->dispatch();
        } catch (Throwable $exception) {
            Log::error('The chat assistant failed to respond.', [
                'conversation' => $conversation->reference,
                'exception' => $exception,
            ]);

            return null;
        }

        if ($result['conversation']?->isNot($conversation)) {
            return null;
        }

        return $result['message'];
    }

    /**
     * Shape a conversation for the chat page.
     *
     * A null conversation produces the empty shell a first-time visitor sees.
     *
     * @return array<string, mixed>
     */
    private function conversationPayload(?Conversation $conversation): array
    {
        return [
            'reference' => $conversation?->reference,
            'label' => $conversation?->displayLabel() ?? 'Project conversation',
            'client_name' => $conversation?->client_name,
            'client_email' => $conversation?->client_email,
            'stage' => $conversation?->stage ?? Conversation::INITIAL_STAGE,
            'stage_index' => $conversation?->stageIndex() ?? 0,
            'currency' => $conversation?->currency ?? 'PHP',
            'first_month_amount' => $conversation?->first_month_amount,
            'monthly_amount' => $conversation?->monthly_amount,
            'amount_paid' => $conversation?->amount_paid,
            'amount_due' => $conversation?->amount_due,
            'contract_status' => $conversation?->contract_status,
            'contract_started_at' => $conversation?->contract_started_at?->format('M j, Y'),
            'contract_renews_at' => $conversation?->contract_renews_at?->format('M j, Y'),
            'ai_enabled' => $conversation?->ai_enabled ?? true,
        ];
    }

    /**
     * Shape the transcript for a conversation.
     *
     * When a cursor is given, only messages newer than it are returned.
     *
     * @return list<array<string, mixed>>
     */
    private function transcript(?Conversation $conversation, ?int $afterId = null): array
    {
        if ($conversation === null) {
            return [];
        }

        $query = $conversation->messages()->with('attachment')->orderBy('id');

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        return $query->get()->map($this->messagePayload(...))->values()->all();
    }

    /**
     * Shape a message for the chat page.
     *
     * @return array<string, mixed>
     */
    private function messagePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'type' => $message->type,
            'body' => $message->body,
            'time' => $message->created_at->format('g:i A'),
            'attachment' => $this->attachmentPayload($message->attachment),
        ];
    }

    /**
     * Shape an uploaded file for the chat page.
     *
     * @return array<string, mixed>|null
     */
    private function attachmentPayload(?Attachment $attachment): ?array
    {
        if ($attachment === null) {
            return null;
        }

        return [
            'id' => $attachment->id,
            'kind' => $attachment->kind,
            'name' => $attachment->original_name,
            'mime' => $attachment->mime_type,
            'size' => $attachment->size,
            'size_human' => $attachment->humanSize(),
            'url' => route('chat.attachments.show', $attachment),
        ];
    }
}
