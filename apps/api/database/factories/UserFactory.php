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
        // `role` is not in the model's $fillable (privilege boundary), so it
        // can't be set here via mass assignment — it's applied in configure()
        // and overridable via the admin() state.
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'emailVerified' => true,
        ];
    }

    /**
     * Create the matching `credential` account (password hash) for the user,
     * mirroring how registration stores credentials.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            // Default role, set outside mass assignment since `role` is guarded.
            // A state (e.g. admin()) runs later and can override it.
            if (empty($user->role)) {
                $user->role = 'agent';
            }
        })->afterCreating(function (User $user) {
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

    /**
     * Indicate that the user is an admin. Sets the guarded `role` attribute
     * directly rather than through mass assignment.
     */
    public function admin(): static
    {
        return $this->afterMaking(fn (User $user) => $user->role = 'admin');
    }
}
