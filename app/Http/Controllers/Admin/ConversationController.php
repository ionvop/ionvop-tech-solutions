<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Rules\AllowedChatUpload;
use App\Services\AssistantService;
use App\Services\ChatUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class ConversationController extends Controller
{
    /**
     * List conversations, optionally filtered by a search term or stage.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'stage' => ['nullable', 'string', Rule::in(array_keys(Conversation::stageOptions()))],
        ]);

        $query = Conversation::query()
            ->withLastMessage()
            ->with('lastMessage.attachment')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';

            $query->where(function ($query) use ($term): void {
                $query->where('label', 'like', $term)
                    ->orWhere('client_name', 'like', $term)
                    ->orWhere('client_email', 'like', $term)
                    ->orWhere('reference', 'like', $term);
            });
        }

        if (filled($validated['stage'] ?? null)) {
            $query->where('stage', $validated['stage']);
        }

        return response()->json([
            'conversations' => $query->get()->map(ConversationPayload::summary(...))->values(),
        ]);
    }

    /**
     * Show a single conversation with its transcript.
     *
     * Passing `after_id` returns only the messages the dashboard has not
     * rendered yet, so polling can append instead of replacing the transcript.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $afterId = $validated['after_id'] ?? null;

        // The guard keeps this from writing on every poll: it only fires when
        // something arrived since the dashboard last looked.
        if ($conversation->hasUnread()) {
            $conversation->forceFill(['admin_read_at' => now()])->save();
        }

        $query = $conversation->messages()->with('attachment')->orderBy('id');

        if ($afterId !== null) {
            $query->where('id', '>', (int) $afterId);
        }

        return response()->json([
            'conversation' => ConversationPayload::detail($conversation),
            'messages' => $query->get()->map(ConversationPayload::message(...))->values(),
        ]);
    }

    /**
     * Update the project, payment, and contract details of a conversation.
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'stage' => ['required', 'string', Rule::in(array_keys(Conversation::stageOptions()))],
            'currency' => ['required', 'string', Rule::in(['PHP', 'USD'])],
            'first_month_amount' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'monthly_amount' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'amount_paid' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'amount_due' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'contract_status' => ['nullable', 'string', Rule::in(Conversation::CONTRACT_STATUSES)],
            'contract_started_at' => ['nullable', 'date'],
            'contract_renews_at' => [
                'nullable',
                'date',
                Rule::when(
                    $request->filled('contract_started_at'),
                    ['after_or_equal:contract_started_at'],
                ),
            ],
            'ai_enabled' => ['required', 'boolean'],
        ]);

        $conversation->forceFill($validated)->save();

        return response()->json([
            'conversation' => ConversationPayload::detail($conversation->refresh()),
        ]);
    }

    /**
     * Post a reply from ionvop into the conversation.
     *
     * A reply may carry one uploaded file instead of, or alongside, its text.
     */
    public function storeMessage(Request $request, Conversation $conversation, ChatUploads $uploads): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000', 'required_without:file'],
            'file' => ['nullable', 'file', new AllowedChatUpload($uploads)],
        ]);

        $file = $request->file('file');
        $kind = $file === null ? null : $uploads->kindFor($file);
        $body = trim((string) ($validated['message'] ?? ''));

        $message = $conversation->messages()->create([
            'role' => Message::ROLE_ADMIN,
            'type' => $kind ?? Message::TYPE_TEXT,
            'body' => $body === '' ? null : $body,
        ]);

        if ($file !== null && $kind !== null) {
            $uploads->store($message, $file, $kind);
        }

        $conversation->forceFill([
            'last_message_at' => $message->created_at,
            'admin_read_at' => now(),
            'ai_pending_since' => null,
        ])->save();

        return response()->json([
            'message' => ConversationPayload::message($message->refresh()),
            'conversation' => ConversationPayload::detail($conversation->refresh()),
        ], 201);
    }

    /**
     * Generate and store an administrative summary of the conversation.
     */
    public function summarize(Conversation $conversation, AssistantService $assistant): JsonResponse
    {
        try {
            $summary = $assistant->summarize($conversation);
        } catch (Throwable $exception) {
            Log::error('The assistant failed to summarize a conversation.', [
                'conversation' => $conversation->reference,
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'The summary could not be generated right now. Please try again in a moment.',
            ], 502);
        }

        $conversation->forceFill([
            'summary' => $summary,
            'summary_generated_at' => now(),
        ])->save();

        return response()->json([
            'conversation' => ConversationPayload::detail($conversation->refresh()),
        ]);
    }

    /**
     * Delete a conversation and its messages.
     */
    public function destroy(Conversation $conversation): JsonResponse
    {
        $conversation->delete();

        return response()->json(['deleted' => true]);
    }
}
