<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SpamLevel;
use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;
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
            'spam_score' => null,
            'spam_level' => SpamLevel::Green,
            'spam_reason' => null,
            'country' => null,
            'asn' => null,
            'asn_org' => null,
        ];
    }

    /** Classify this withdrawal as red (the system suspects spam). */
    public function spam(): static
    {
        return $this->state(fn (array $attributes) => [
            'spam_score' => 100,
            'spam_level' => SpamLevel::Red,
            'spam_reason' => 'honeypot',
        ]);
    }

    /** Classify this withdrawal as yellow (suspicious). */
    public function yellow(): static
    {
        return $this->state(fn (array $attributes) => [
            'spam_score' => 30,
            'spam_level' => SpamLevel::Yellow,
            'spam_reason' => 'throttle',
        ]);
    }

    /** The network the submit came from, as derived locally from the client IP. */
    public function fromNetwork(string $country, int $asn, ?string $asnOrg = null): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => $country,
            'asn' => $asn,
            'asn_org' => $asnOrg,
        ]);
    }

    /** The operator confirmed this withdrawal as spam (red level, verdict spam). */
    public function confirmedSpam(?Carbon $at = null): static
    {
        return $this->spam()->state(fn (array $attributes) => [
            'spam_verdict' => SpamVerdict::Spam,
            'spam_verdict_at' => $at ?? Carbon::now(),
            'spam_verdict_source' => SpamVerdictSource::Operator,
        ]);
    }

    /** The auto-sorting rule sorted this withdrawal out on store (honeypot + ancient browser). */
    public function autoSorted(?Carbon $at = null): static
    {
        return $this->state(fn (array $attributes) => [
            'spam_score' => 125,
            'spam_level' => SpamLevel::Red,
            'spam_reason' => 'honeypot,user_agent_ancient',
            'spam_verdict' => SpamVerdict::Spam,
            'spam_verdict_at' => $at ?? Carbon::now(),
            'spam_verdict_source' => SpamVerdictSource::Auto,
        ]);
    }

    /** The operator corrected this withdrawal to "not spam". */
    public function notSpam(?Carbon $at = null): static
    {
        return $this->state(fn (array $attributes) => [
            'spam_verdict' => SpamVerdict::NotSpam,
            'spam_verdict_at' => $at ?? Carbon::now(),
            'spam_verdict_source' => SpamVerdictSource::Operator,
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
