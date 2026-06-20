<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Customer->value,
            'remember_token' => Str::random(10),
        ];
    }

    public function shopOwner(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::ShopOwner->value,
        ]);
    }

    public function shopKeeper(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::ShopKeeper->value,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Customer->value,
        ]);
    }

    /**
     * Indicate that the user's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
