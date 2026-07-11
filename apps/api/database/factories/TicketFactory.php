<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // `reference` is left unset so the model's creating hook generates it.
            'subject' => fake()->sentence(),
            'status' => TicketStatus::Open,
            'category' => null,
            'contact_id' => Contact::factory(),
            'assigned_to' => null,
        ];
    }
}
