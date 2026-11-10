<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AssistantService;
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
            'conversation' => $conversation,
            'messages' => $conversation?->messages()->orderBy('id')->get() ?? collect(),
        ]);
    }

    /**
     * Store a client message and reply with the assistant's response.
     */
    public function store(Request $request, AssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = $this->resolveConversation($request);
        $created = [];

        if ($conversation === null) {
            $conversation = Conversation::start();
            $created[] = $conversation->messages()->orderBy('id')->firstOrFail();
        }

        $clientMessage = $conversation->messages()->create([
            'role' => Message::ROLE_CLIENT,
            'type' => 'text',
            'body' => trim($validated['message']),
        ]);

        $conversation->forceFill(['last_message_at' => $clientMessage->created_at])->save();

        $created[] = $clientMessage;
        $error = null;

        if ($conversation->ai_enabled) {
            try {
                $created[] = $assistant->respond($conversation);
            } catch (Throwable $exception) {
                Log::error('The chat assistant failed to respond.', [
                    'conversation' => $conversation->reference,
                    'exception' => $exception,
                ]);

                $error = 'The assistant could not reply right now. Your message was saved — please try again in a moment.';
            }
        }

        Cookie::queue(
            Conversation::COOKIE,
            $conversation->reference,
            self::COOKIE_LIFETIME_DAYS * 24 * 60,
        );

        return response()->json([
            'conversation' => $this->conversationPayload($conversation->refresh()),
            'messages' => array_map($this->messagePayload(...), $created),
            'error' => $error,
        ], $error === null ? 200 : 502);
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
     * Shape a conversation for the chat page.
     *
     * @return array<string, mixed>
     */
    private function conversationPayload(Conversation $conversation): array
    {
        return [
            'reference' => $conversation->reference,
            'label' => $conversation->displayLabel(),
            'client_name' => $conversation->client_name,
            'client_email' => $conversation->client_email,
            'stage' => $conversation->stage,
            'stage_index' => $conversation->stageIndex(),
            'currency' => $conversation->currency,
            'first_month_amount' => $conversation->first_month_amount,
            'monthly_amount' => $conversation->monthly_amount,
            'amount_paid' => $conversation->amount_paid,
            'amount_due' => $conversation->amount_due,
            'contract_status' => $conversation->contract_status,
            'contract_started_at' => $conversation->contract_started_at?->format('M j, Y'),
            'contract_renews_at' => $conversation->contract_renews_at?->format('M j, Y'),
            'ai_enabled' => $conversation->ai_enabled,
        ];
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
        ];
    }
}
