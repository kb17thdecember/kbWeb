<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(40),
            'visitor_name' => fake()->name(),
            'visitor_email' => fake()->safeEmail(),
            'status' => 'open',
            'last_message_at' => now(),
        ];
    }
}
