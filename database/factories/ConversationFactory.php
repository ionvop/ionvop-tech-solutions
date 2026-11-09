<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => Conversation::generateReference(),
            'stage' => Conversation::INITIAL_STAGE,
            'currency' => 'PHP',
        ];
    }

    /**
     * Indicate that the client has introduced themselves.
     */
    public function withClient(string $name = 'Jomar', ?string $email = null): static
    {
        return $this->state(fn (array $attributes) => [
            'client_name' => $name,
            'client_email' => $email,
            'label' => $name.' — hardware store',
        ]);
    }

    /**
     * Indicate that the project has a payment arrangement.
     */
    public function withPayment(int $paid = 500, int $due = 1500): static
    {
        return $this->state(fn (array $attributes) => [
            'first_month_amount' => 2000,
            'monthly_amount' => 1000,
            'amount_paid' => $paid,
            'amount_due' => $due,
        ]);
    }

    /**
     * Indicate that the project has an active contract.
     */
    public function withContract(): static
    {
        return $this->state(fn (array $attributes) => [
            'contract_status' => 'Active',
            'contract_started_at' => now()->toDateString(),
            'contract_renews_at' => now()->addMonth()->toDateString(),
        ]);
    }
}
