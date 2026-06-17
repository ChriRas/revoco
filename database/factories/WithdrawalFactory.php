<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Withdrawal>
 */
final class WithdrawalFactory extends Factory
{
    protected $model = Withdrawal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'order_number' => fake()->optional(0.7)->numerify('ORD-######'),
            'subject' => fake()->sentence(8),
            'locale' => 'de',
            'spam' => false,
            'spam_reason' => null,
        ];
    }

    /** Mark this withdrawal as spam. */
    public function spam(): static
    {
        return $this->state(fn (array $attributes) => [
            'spam' => true,
            'spam_reason' => 'honeypot',
        ]);
    }

    /**
     * Mark this withdrawal as handled.
     *
     * Uses afterCreating to set handled_at directly (bypassing $fillable, which
     * intentionally excludes handled_at — it is set exclusively via the operator
     * toggle action, never mass-assigned).
     */
    public function handled(): static
    {
        return $this->afterCreating(function (Withdrawal $withdrawal): void {
            $withdrawal->handled_at = Carbon::now();
            $withdrawal->save();
        });
    }
}
