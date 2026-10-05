<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::query()->value('id') ?? Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Secret#Pass123',
            'role' => 'risk_officer',
            'active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }
}
