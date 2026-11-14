<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'disk' => 'local',
            'path' => 'chat/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1024, 5_000_000),
            'kind' => Attachment::KIND_IMAGE,
        ];
    }

    /**
     * Indicate that the attachment is an image.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => 'chat/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => Attachment::KIND_IMAGE,
        ]);
    }

    /**
     * Indicate that the attachment is an audio file.
     */
    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => 'chat/'.fake()->uuid().'.mp3',
            'original_name' => fake()->word().'.mp3',
            'mime_type' => 'audio/mpeg',
            'kind' => Attachment::KIND_AUDIO,
        ]);
    }

    /**
     * Indicate that the attachment is a video file.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => 'chat/'.fake()->uuid().'.mp4',
            'original_name' => fake()->word().'.mp4',
            'mime_type' => 'video/mp4',
            'kind' => Attachment::KIND_VIDEO,
        ]);
    }

    /**
     * Indicate that the attachment is a document.
     */
    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'path' => 'chat/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'kind' => Attachment::KIND_FILE,
        ]);
    }
}
