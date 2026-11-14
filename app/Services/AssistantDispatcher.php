<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;

class AssistantDispatcher
{
    /**
     * The cache key that marks the assistant as cooling down.
     */
    private const COOLDOWN_KEY = 'chat:ai:cooldown';

    /**
     * The cache key that serialises dispatches across concurrent requests.
     */
    private const LOCK_KEY = 'chat:ai:dispatch';

    /**
     * The number of seconds a dispatch may hold the lock.
     *
     * The model client retries once, so a dispatch can take twice the configured
     * timeout. The extra margin keeps the lock from expiring mid-call.
     */
    private const LOCK_MARGIN_SECONDS = 30;

    public function __construct(private readonly AssistantService $assistant) {}

    /**
     * Answer the conversation that has been waiting on the assistant the longest.
     *
     * Returns the conversation that was answered and the message that was stored,
     * or nulls when the assistant is cooling down, another request is already
     * dispatching, or nobody is waiting.
     *
     * @return array{conversation: Conversation|null, message: Message|null}
     */
    public function dispatch(): array
    {
        if ($this->isCoolingDown()) {
            return $this->idle();
        }

        $lock = Cache::lock(self::LOCK_KEY, $this->lockSeconds());

        if (! $lock->get()) {
            return $this->idle();
        }

        try {
            // Another request may have answered a conversation while this one
            // waited for the lock, so the cooldown is checked again.
            if ($this->isCoolingDown()) {
                return $this->idle();
            }

            $conversation = Conversation::query()->pendingForAssistant()->first();

            if ($conversation === null) {
                return $this->idle();
            }

            $answeredSince = $conversation->ai_pending_since;

            $message = $this->assistant->respond($conversation);

            $conversation->markProcessedForAssistant($answeredSince);
            $this->startCooldown();

            return ['conversation' => $conversation, 'message' => $message];
        } finally {
            $lock->release();
        }
    }

    /**
     * Determine whether the assistant is still inside its cooldown window.
     */
    private function isCoolingDown(): bool
    {
        return Cache::has(self::COOLDOWN_KEY);
    }

    /**
     * Start the cooldown that follows a completed turn.
     */
    private function startCooldown(): void
    {
        $seconds = max(0, (int) config('chat.ai_cooldown_seconds'));

        if ($seconds === 0) {
            return;
        }

        Cache::put(self::COOLDOWN_KEY, true, $seconds);
    }

    /**
     * Get the number of seconds a dispatch may hold the lock.
     */
    private function lockSeconds(): int
    {
        $timeout = (int) config('services.openrouter.timeout');

        return ($timeout * 2) + self::LOCK_MARGIN_SECONDS;
    }

    /**
     * Build the result used when no turn was taken.
     *
     * @return array{conversation: Conversation|null, message: Message|null}
     */
    private function idle(): array
    {
        return ['conversation' => null, 'message' => null];
    }
}
