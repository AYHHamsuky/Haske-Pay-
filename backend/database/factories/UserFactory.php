<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '080'.fake()->unique()->numerify('########'),
            'role' => 'customer',
            'status' => 'active',
        ];
    }

    public function agent(string $tier = 'silver'): static
    {
        return $this->state(['role' => 'agent'])->afterCreating(function (User $u) use ($tier) {
            $u->agentProfile()->create(['business_name' => $u->name.' POS', 'commission_tier' => $tier]);
        });
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function withPin(string $pin = '2580'): static
    {
        return $this->afterCreating(fn (User $u) => $u->setPin($pin));
    }
}
