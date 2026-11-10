<?php

namespace App\Http\Controllers\Admin;

use App\Models\Conversation;
use App\Models\Message;

/**
 * Shapes conversations and messages for the admin dashboard's JSON endpoints.
 */
class ConversationPayload
{
    /**
     * Shape a conversation for the conversation list.
     *
     * @return array<string, mixed>
     */
    public static function summary(Conversation $conversation): array
    {
        $lastMessage = $conversation->lastMessage;

        return [
            'reference' => $conversation->reference,
            'label' => $conversation->displayLabel(),
            'client_name' => $conversation->client_name,
            'client_email' => $conversation->client_email,
            'stage' => $conversation->stage,
            'stage_label' => Conversation::stageOptions()[$conversation->stage] ?? $conversation->stage,
            'ai_enabled' => $conversation->ai_enabled,
            'unread' => $conversation->hasUnread(),
            'message_count' => $conversation->messages_count ?? $conversation->messages()->count(),
            'preview' => $lastMessage?->body === null
                ? null
                : str($lastMessage->body)->squish()->limit(90)->toString(),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_message_human' => $conversation->last_message_at?->diffForHumans(),
        ];
    }

    /**
     * Shape a conversation for the details pane.
     *
     * @return array<string, mixed>
     */
    public static function detail(Conversation $conversation): array
    {
        return [
            ...self::summary($conversation),
            'currency' => $conversation->currency,
            'first_month_amount' => $conversation->first_month_amount,
            'monthly_amount' => $conversation->monthly_amount,
            'amount_paid' => $conversation->amount_paid,
            'amount_due' => $conversation->amount_due,
            'contract_status' => $conversation->contract_status,
            'contract_started_at' => $conversation->contract_started_at?->toDateString(),
            'contract_renews_at' => $conversation->contract_renews_at?->toDateString(),
            'summary' => $conversation->summary,
            'summary_generated_at' => $conversation->summary_generated_at?->toIso8601String(),
            'summary_generated_human' => $conversation->summary_generated_at?->diffForHumans(),
            'created_at' => $conversation->created_at?->toIso8601String(),
            'created_human' => $conversation->created_at?->diffForHumans(),
        ];
    }

    /**
     * Shape a message for the transcript.
     *
     * @return array<string, mixed>
     */
    public static function message(Message $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'type' => $message->type,
            'body' => $message->body,
            'reason' => $message->meta['reason'] ?? null,
            'time' => $message->created_at->format('g:i A'),
            'date' => $message->created_at->format('M j, Y'),
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
