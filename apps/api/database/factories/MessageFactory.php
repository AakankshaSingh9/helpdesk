<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'direction' => MessageDirection::Inbound,
            'from_email' => fake()->safeEmail(),
            'from_name' => fake()->name(),
            'body_text' => fake()->paragraph(),
            'message_id' => '<'.Str::uuid().'@example.com>',
            'in_reply_to' => null,
            'email_references' => null,
        ];
    }
}
