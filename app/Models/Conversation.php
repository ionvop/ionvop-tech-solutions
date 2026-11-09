<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * The cookie that stores the reference of the visitor's conversation.
     */
    public const COOKIE = 'chat_conversation';

    /**
     * The first message the assistant sends in a new conversation.
     */
    public const GREETING = "Hi! I'm the assistant that takes first inquiries while ionvop is away. What should I call you, and what does your business do?";

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
        ];
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
}
