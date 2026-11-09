<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => Message::ROLE_CLIENT,
            'type' => 'text',
            'body' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the message was written by the assistant.
     */
    public function fromAssistant(?string $body = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Message::ROLE_ASSISTANT,
            'body' => $body ?? fake()->sentence(),
        ]);
    }
}
