<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'emailVerified' => true,
            'role' => 'agent',
        ];
    }

    /**
     * Create the matching `credential` account (password hash) for the user,
     * mirroring how registration stores credentials.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->accounts()->create([
                'accountId' => $user->id,
                'providerId' => 'credential',
                'password' => static::$password ??= Hash::make('password'),
            ]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'emailVerified' => false,
        ]);
    }
}
