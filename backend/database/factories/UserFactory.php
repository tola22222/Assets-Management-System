<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A staff-role user needs a Staff record with a location: staff see only
     * their assigned location, and nothing without one (fail closed). So a
     * staff user made without a staff_id gets a Staff record at the PEPY
     * Office (code SR), the way HR sets one up. Pass a staff_id to control it —
     * e.g. a Staff record with no location, to test the fail-closed case.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            if ($user->role !== 'staff' || $user->staff_id !== null) {
                return;
            }

            $user->staff_id = Staff::create([
                'full_name' => $user->name,
                'location_id' => Location::where('code', 'SR')->value('id'),
            ])->id;
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
