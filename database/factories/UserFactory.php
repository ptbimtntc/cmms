<?php

namespace Database\Factories;

use App\Models\Area;
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
            'remember_token' => Str::random(10),
        ];
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

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static {}

    /**
     * Role/Area states — the single place tests should build a
     * role+area user from, replacing the old inline
     * ['role' => User::ROLE_KOORDINATOR_WWD] construction (that combined
     * role no longer exists). ADMIN/GUEST have no area; koordinator()/pic()
     * leave area_id unset until chained with forArea().
     */
    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN, 'area_id' => null]);
    }

    public function koordinator(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_KOORDINATOR]);
    }

    public function pic(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_PIC]);
    }

    public function supervisor(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_SUPERVISOR, 'area_id' => null]);
    }

    public function guest(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_GUEST, 'area_id' => null]);
    }

    /**
     * Attaches (finding-or-creating by name) the given Area. Chain after
     * koordinator()/pic(), e.g. User::factory()->koordinator()->forArea('WWD')->create().
     */
    public function forArea(Area|string $area): static
    {
        return $this->state(function () use ($area) {
            $area = $area instanceof Area
                ? $area
                : Area::firstOrCreate(['name' => $area], ['slug' => Str::slug($area), 'is_active' => true]);

            return ['area_id' => $area->id];
        });
    }
}
