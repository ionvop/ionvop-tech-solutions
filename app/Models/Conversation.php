<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'reference',
    'client_name',
    'client_email',
    'label',
    'stage',
    'currency',
    'first_month_amount',
    'monthly_amount',
    'amount_paid',
    'amount_due',
    'contract_status',
    'contract_started_at',
    'contract_renews_at',
    'last_message_at',
    'summary',
    'summary_generated_at',
    'ai_enabled',
    'admin_read_at',
    'ai_pending_since',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * The project stages, in the order they are reached.
     *
     * @var list<string>
     */
    public const STAGES = ['Inquiry', 'Planning', 'Development', 'Delivery'];

    /**
     * The stage a conversation starts at.
     */
    public const INITIAL_STAGE = 'inquiry';

    /**
     * The contract statuses ionvop can assign to a conversation.
     *
     * @var list<string>
     */
    public const CONTRACT_STATUSES = ['Draft', 'Active', 'Paused', 'Completed', 'Cancelled'];

    /**
     * The cookie that stores the reference of the visitor's conversation.
     */
    public const COOKIE = 'chat_conversation';

    /**
     * The first message the assistant sends in a new conversation.
     */
    public const GREETING = "Hi! I'm the assistant that takes first inquiries while ionvop is away. What should I call you, and what does your business do?";

    /**
     * The message shown when the assistant pauses itself without a reply.
     */
    public const PAUSE_NOTICE = "I'll pause here and let ionvop take over from this point.";

    /**
     * Get the messages in the conversation.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get every file uploaded in the conversation.
     *
     * @return HasManyThrough<Attachment, Message, $this>
     */
    public function attachments(): HasManyThrough
    {
        return $this->hasManyThrough(Attachment::class, Message::class);
    }

    /**
     * Get the most recent message in the conversation.
     *
     * @return HasOne<Message, $this>
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Eager load the last message and the message count for a listing.
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopeWithLastMessage(Builder $query): void
    {
        $query->with('lastMessage')->withCount('messages');
    }

    /**
     * Limit the query to conversations the assistant still owes a turn.
     *
     * The oldest waiting client is served first, so the ordering key is the
     * moment the client's latest message arrived.
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopePendingForAssistant(Builder $query): void
    {
        $query->where('ai_enabled', true)
            ->whereNotNull('ai_pending_since')
            ->orderBy('ai_pending_since')
            ->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_month_amount' => 'integer',
            'monthly_amount' => 'integer',
            'amount_paid' => 'integer',
            'amount_due' => 'integer',
            'contract_started_at' => 'date',
            'contract_renews_at' => 'date',
            'last_message_at' => 'datetime',
            'summary_generated_at' => 'datetime',
            'ai_enabled' => 'boolean',
            'admin_read_at' => 'datetime',
            'ai_pending_since' => 'datetime',
        ];
    }

    /**
     * Remove uploaded files before the database cascade drops their rows.
     *
     * Deleting through the database bypasses model events, so the files have to
     * be removed here while the attachment rows are still readable.
     */
    protected static function booted(): void
    {
        static::deleting(function (Conversation $conversation): void {
            $conversation->attachments()->get()->each->deleteFile();
        });
    }

    /**
     * Generate a reference that is not yet used by another conversation.
     */
    public static function generateReference(): string
    {
        do {
            $reference = Str::lower(Str::random(12));
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Start a conversation and record the assistant's greeting.
     */
    public static function start(): self
    {
        $conversation = static::create([
            'reference' => static::generateReference(),
            'stage' => self::INITIAL_STAGE,
            'ai_enabled' => true,
        ]);

        $greeting = $conversation->messages()->create([
            'role' => Message::ROLE_ASSISTANT,
            'type' => 'text',
            'body' => self::GREETING,
        ]);

        $conversation->forceFill(['last_message_at' => $greeting->created_at])->save();

        return $conversation;
    }

    /**
     * Get the position of the current stage within the stage list.
     */
    public function stageIndex(): int
    {
        $index = array_search(Str::lower($this->stage), array_map(Str::lower(...), self::STAGES), true);

        return $index === false ? 0 : $index;
    }

    /**
     * Get the name the client is shown as.
     */
    public function displayName(): string
    {
        return $this->client_name ?? 'Client';
    }

    /**
     * Get the label shown in the conversation header.
     */
    public function displayLabel(): string
    {
        return $this->label ?? 'Project conversation';
    }

    /**
     * Get the selectable stages keyed by their stored value.
     *
     * @return array<string, string>
     */
    public static function stageOptions(): array
    {
        return array_combine(
            array_map(Str::lower(...), self::STAGES),
            self::STAGES,
        );
    }

    /**
     * Record that the assistant no longer owes this conversation a turn.
     *
     * When the timestamp the assistant was answering is given, the update only
     * applies if the conversation has not been marked pending again since. A
     * client message that arrived while the model was thinking therefore keeps
     * its place in the queue instead of being silently dropped.
     */
    public function markProcessedForAssistant(?DateTimeInterface $answeredSince = null): void
    {
        $query = static::query()->whereKey($this->getKey());

        if ($answeredSince !== null) {
            $query->where('ai_pending_since', $answeredSince);
        }

        if ($query->update(['ai_pending_since' => null]) === 0) {
            return;
        }

        $this->setAttribute('ai_pending_since', null);
        $this->syncOriginalAttribute('ai_pending_since');
    }

    /**
     * Determine whether the conversation has messages ionvop has not read yet.
     */
    public function hasUnread(): bool
    {
        if ($this->admin_read_at === null) {
            return $this->last_message_at !== null;
        }

        return $this->last_message_at !== null
            && $this->last_message_at->greaterThan($this->admin_read_at);
    }
}
