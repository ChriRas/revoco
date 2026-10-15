<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SpamLevel;
use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;
use App\Models\Withdrawal;
use App\Settings\LocaleSettings;
use App\Settings\NetworkSignalSettings;
use App\Settings\NotificationSettings;
use App\Settings\WithdrawalScopeSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Deterministic, fictitious demo data for the documentation screenshots (`docs/screenshots/manifest.md`).
 *
 * Fixed data instead of Faker (absent from the `--no-dev` image, and not reproducible), no randomness.
 * Everything is invented: Max Mustermann and friends at example.com / example.org, networks from the
 * documentation ASN range 64496-64511 (RFC 5398) with made-up names. Refuses to run in production and is
 * kept out of the production image (`.dockerignore`).
 *
 * Twelve months of daily-counter history go through the live recording path: the historical rows are
 * created as models with a past `created_at` (the `created` observer counts each on its day) and are then
 * deleted, exactly as `model:prune` would, so the counters survive and the list keeps only the visible set.
 */
final class ScreenshotSeeder extends Seeder
{
    /** Months of counter history, the current one included. */
    public const int HISTORY_MONTHS = 12;

    /**
     * Counter history per month, oldest first: green, yellow, red open, auto-sorted, confirmed by the
     * operator, red corrected to "not spam", green or yellow marked as spam by the operator.
     *
     * @var list<array{int, int, int, int, int, int, int}>
     */
    private const array HISTORY = [
        [5, 2, 1, 2, 1, 0, 0],
        [6, 2, 2, 3, 1, 1, 0],
        [7, 3, 1, 2, 2, 0, 1],
        [6, 2, 2, 4, 1, 1, 0],
        [8, 3, 2, 3, 2, 0, 0],
        [9, 3, 1, 5, 1, 1, 1],
        [8, 4, 2, 4, 2, 0, 0],
        [10, 3, 3, 6, 2, 1, 0],
        [11, 4, 2, 5, 2, 0, 1],
        [12, 4, 3, 7, 3, 1, 0],
        [13, 5, 2, 6, 2, 1, 1],
        [4, 2, 1, 3, 1, 0, 0],
    ];

    /** Fired signals with the score they add up to (weights in `config/revoco.php`), per history category. */
    private const array HISTORY_SIGNALS = [
        'green' => [[null, 0], ['name_without_space', 15], ['country_outside_shipping', 4], [null, 0]],
        'yellow' => [
            ['throttle', 30],
            ['fill_time_too_fast,name_without_space', 55],
            ['abused_address', 30],
            ['user_agent_ancient,name_without_space', 40],
            ['learned_network,drop_network', 40],
        ],
        'red' => [
            ['content_gibberish,render_token_invalid', 85],
            ['honeypot', 100],
            ['fill_time_too_fast,throttle', 70],
        ],
        'auto' => [
            ['honeypot,user_agent_ancient', 125],
            ['honeypot,fill_time_too_fast', 140],
            ['honeypot,render_token_invalid', 140],
            ['honeypot,content_gibberish', 145],
        ],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            // Unset when the seeder runs outside artisan.
            $this->command?->error('ScreenshotSeeder refuses to run in production.'); // @phpstan-ignore nullsafe.neverNull

            return;
        }

        $this->seedSettings();

        $historicalIds = $this->seedHistory();

        // No `deleted` observer and no foreign key on the counters: the counts outlive the rows, as after a purge.
        Withdrawal::query()->whereKey($historicalIds)->delete();

        $this->seedVisibleRows();
    }

    private function seedSettings(): void
    {
        $locale = app(LocaleSettings::class);
        $locale->available = ['de', 'en'];
        $locale->default = 'de';
        $locale->save();

        $scope = app(WithdrawalScopeSettings::class);
        $scope->offers_goods = true;
        $scope->offers_services = true;
        $scope->offers_digital = false;
        $scope->save();

        $notification = app(NotificationSettings::class);
        $notification->notification_email = 'orders@example.com';
        $notification->save();

        $network = app(NetworkSignalSettings::class);
        $network->shipping_countries = ['DE', 'AT', 'CH', 'NL'];
        $network->save();
    }

    /**
     * Creates the historical rows (counted on their receipt day by the observer) and returns their ids.
     *
     * @return list<int>
     */
    private function seedHistory(): array
    {
        $ids = [];
        $now = now();

        foreach (self::HISTORY as $index => $mix) {
            $monthsBack = self::HISTORY_MONTHS - 1 - $index;
            $monthStart = $now->copy()->startOfMonth()->subMonths($monthsBack);
            $sequence = 0;

            $kinds = ['green', 'yellow', 'red', 'auto', 'operator', 'false_alarm', 'missed'];

            foreach ($kinds as $kindIndex => $kind) {
                for ($n = 0; $n < $mix[$kindIndex]; $n++) {
                    $receivedAt = $this->historicalDay($monthStart, $monthsBack, $monthsBack * 3 + $sequence * 7, $sequence);
                    $ids[] = $this->createHistorical($kind, $n, $receivedAt);
                    $sequence++;
                }
            }
        }

        return $ids;
    }

    /** A weekday-agnostic, deterministic day inside the month, never later than now. */
    private function historicalDay(Carbon $monthStart, int $monthsBack, int $seed, int $sequence): Carbon
    {
        $offset = $seed % 27;

        if ($monthsBack === 0) {
            $offset = min($offset, max(0, now()->day - 1));
        }

        $receivedAt = $monthStart->copy()->addDays($offset)->setTime(8 + $sequence % 9, 10 + ($sequence * 7) % 50);

        return $receivedAt->greaterThan(now()) ? now()->copy()->subMinutes(5 + $sequence) : $receivedAt;
    }

    private function createHistorical(string $kind, int $n, Carbon $receivedAt): int
    {
        $signals = static fn (string $category): array => self::HISTORY_SIGNALS[$category][$n % count(self::HISTORY_SIGNALS[$category])];

        [$level, $verdict, $source, $reason, $score] = match ($kind) {
            'green' => [SpamLevel::Green, null, null, ...$signals('green')],
            'yellow' => [SpamLevel::Yellow, null, null, ...$signals('yellow')],
            'red' => [SpamLevel::Red, null, null, ...$signals('red')],
            'auto' => [SpamLevel::Red, SpamVerdict::Spam, SpamVerdictSource::Auto, ...$signals('auto')],
            'operator' => [SpamLevel::Red, SpamVerdict::Spam, SpamVerdictSource::Operator, 'honeypot', 100],
            'false_alarm' => [SpamLevel::Red, SpamVerdict::NotSpam, SpamVerdictSource::Operator, 'fill_time_too_fast,throttle', 70],
            default => [SpamLevel::Yellow, SpamVerdict::Spam, SpamVerdictSource::Operator, 'throttle', 30],
        };

        return $this->store([
            'name' => 'Archived Sample',
            'email' => 'archived@example.org',
            'order_number' => null,
            'subject' => 'Archived sample row, counted and then purged.',
            'locale' => 'en',
            'spam_score' => $score,
            'spam_level' => $level,
            'spam_reason' => $reason,
        ], $receivedAt, $verdict, $source, $verdict === null ? null : $receivedAt->copy()->addHour());
    }

    private function seedVisibleRows(): void
    {
        $now = now();
        $ago = static fn (int $minutes): Carbon => $now->copy()->subMinutes($minutes);

        $inbox = [
            // [name, email, order, level, reason, score, minutes ago, locale, country, network, asn, handled, subject]
            ['Max Mustermann', 'kunde@example.com', 'ORD-104218', SpamLevel::Green, null, 0, 12, 'en', 'DE', 'Example Telecom Nord', 64496, false,
                'I hereby withdraw from the contract I concluded for the goods ordered under the order number above.'],
            ['Erika Beispiel', 'erika.beispiel@example.org', 'ORD-104177', SpamLevel::Green, null, 0, 190, 'de', 'AT', 'Alpenlink Beispiel AG', 64497, false,
                'Hiermit widerrufe ich den von mir abgeschlossenen Vertrag über die unten genannte Bestellung.'],
            ['Sabine Musterfrau', 'sabine.musterfrau@example.org', 'ORD-104093', SpamLevel::Yellow, 'fill_time_too_fast,user_agent_ancient', 65, 1500, 'en', 'CH', 'Helvetia Testnetz', 64499, false,
                'I withdraw from my order. Please confirm receipt of this declaration.'],
            ['Peter Probemann', 'peter.probemann@example.com', 'ORD-104060', SpamLevel::Yellow, 'abused_address', 30, 2300, 'de', 'NL', 'Tulpen Beispiel Breitband', 64500, true,
                'Ich widerrufe meine Bestellung vom letzten Wochenende.'],
            ['Karl Musterhausen', 'karl.musterhausen@example.com', 'ORD-104031', SpamLevel::Green, null, 0, 3100, 'de', 'DE', 'Rheinnetz Muster GmbH', 64498, true,
                'Widerruf des Kaufvertrags über die Gartenmöbel aus der genannten Bestellung.'],
            ['Anna Beispielfrau', 'anna.beispielfrau@example.org', 'ORD-103988', SpamLevel::Green, null, 0, 4300, 'en', 'DE', 'Example Telecom Nord', 64496, false,
                'I would like to withdraw from the contract for the two items I ordered.'],
            ['Lisa Mustermann-Weber', 'lisa.mustermann-weber@example.com', 'ORD-103942', SpamLevel::Green, null, 0, 5900, 'de', 'AT', 'Alpenlink Beispiel AG', 64497, true,
                'Ich möchte den Vertrag über die bestellte Ware widerrufen.'],
            ['Jonas Testmann', 'jonas.testmann@example.org', 'ORD-103901', SpamLevel::Green, null, 0, 7200, 'en', 'DE', 'Rheinnetz Muster GmbH', 64498, false,
                'Withdrawal from the contract concluded online.'],
            ['Helga Demonstrant', 'helga.demonstrant@example.com', null, SpamLevel::Green, null, 0, 9800, 'de', 'CH', 'Helvetia Testnetz', 64499, false,
                'Widerruf meiner Bestellung, die Bestellnummer liegt mir gerade nicht vor.'],
            ['Tobias Platzhalter', 'tobias.platzhalter@example.org', 'ORD-103810', SpamLevel::Green, null, 0, 12500, 'en', 'NL', 'Tulpen Beispiel Breitband', 64500, false,
                'I withdraw from the purchase of the item listed in the order above.'],
        ];

        foreach ($inbox as [$name, $email, $order, $level, $reason, $score, $minutes, $locale, $country, $network, $asn, $handled, $subject]) {
            $this->store([
                'name' => $name,
                'email' => $email,
                'order_number' => $order,
                'subject' => $subject,
                'locale' => $locale,
                'spam_score' => $score,
                'spam_level' => $level,
                'spam_reason' => $reason,
                'country' => $country,
                'asn' => $asn,
                'asn_org' => $network,
            ], $ago($minutes), handledAt: $handled ? $ago($minutes)->addHours(5) : null);
        }

        // The released row: red at receipt, corrected to "not spam" by the operator (shows in the Inbox).
        $this->store([
            'name' => 'Heinz Beispielmann',
            'email' => 'heinz.beispielmann@example.org',
            'order_number' => 'ORD-103775',
            'subject' => 'Withdrawal from the contract, sent twice because the page was slow.',
            'locale' => 'en',
            'spam_score' => 70,
            'spam_level' => SpamLevel::Red,
            'spam_reason' => 'fill_time_too_fast,throttle',
            'country' => 'DE',
            'asn' => 64498,
            'asn_org' => 'Rheinnetz Muster GmbH',
        ], $ago(7000), SpamVerdict::NotSpam, SpamVerdictSource::Operator, $ago(6900));

        $spam = [
            // [name, email, score, reason, minutes ago, verdict, source, verdict minutes ago, country, asn, network]
            ['xqzkv wplm', 'xqzkv@example.org', 85, 'content_gibberish,render_token_invalid', 130, null, null, null, 'US', 64501, 'Datacenter Beispiel Hosting'],
            ['Win Prize Now', 'prize@example.com', 100, 'honeypot', 300, null, null, null, 'BR', 64502, 'Taiga Testnetz'],
            ['Bot Account', 'bot@example.org', 125, 'honeypot,user_agent_ancient', 1700, SpamVerdict::Spam, SpamVerdictSource::Auto, 1700, 'US', 64501, 'Datacenter Beispiel Hosting'],
            ['asdf qwer', 'asdf@example.com', 140, 'honeypot,fill_time_too_fast', 2900, SpamVerdict::Spam, SpamVerdictSource::Auto, 2900, 'IN', 64503, 'Monsun Beispielnetz'],
            ['Cheap Offers Ltd', 'offers@example.org', 85, 'content_gibberish,fill_time_too_fast', 4400, SpamVerdict::Spam, SpamVerdictSource::Operator, 3000, 'BR', 64502, 'Taiga Testnetz'],
        ];

        foreach ($spam as [$name, $email, $score, $reason, $minutes, $verdict, $source, $verdictMinutes, $country, $asn, $network]) {
            $this->store([
                'name' => $name,
                'email' => $email,
                'order_number' => null,
                'subject' => 'Spam sample for the screenshots.',
                'locale' => 'en',
                'spam_score' => $score,
                'spam_level' => SpamLevel::Red,
                'spam_reason' => $reason,
                'country' => $country,
                'asn' => $asn,
                'asn_org' => $network,
            ], $ago($minutes), $verdict, $source, $verdictMinutes === null ? null : $ago($verdictMinutes));
        }
    }

    /**
     * Saves one withdrawal with an explicit receipt time (the observer counts it on that day) and returns its id.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function store(
        array $attributes,
        Carbon $receivedAt,
        ?SpamVerdict $verdict = null,
        ?SpamVerdictSource $source = null,
        ?Carbon $verdictAt = null,
        ?Carbon $handledAt = null,
    ): int {
        $withdrawal = new Withdrawal;
        // forceFill: the verdict and handling columns are never mass-assignable (immutable-record invariant).
        $withdrawal->forceFill($attributes + [
            'spam_verdict' => $verdict,
            'spam_verdict_at' => $verdict === null ? null : $verdictAt,
            'spam_verdict_source' => $verdict === null ? null : $source,
            'handled_at' => $handledAt,
            'created_at' => $receivedAt,
            'updated_at' => $receivedAt,
        ]);
        $withdrawal->save();

        return $withdrawal->id;
    }
}
