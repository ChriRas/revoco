<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalResource;

use App\Models\Withdrawal;
use App\Spam\SpamReasonLabels;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The live preview above the withdrawal list: what one withdrawal shows there, and which
 * row is the default of a tab. The default is the newest row of the tab's scope only —
 * search, filters, sort and pagination never change it.
 *
 * @phpstan-type PreviewData array{id: int, received: string, name: string, email: string, order: string|null, subject: string, level: string, levelColor: string, reasons: string|null, decision: string|null}
 */
final class WithdrawalPreview
{
    /**
     * The display values of one withdrawal (Europe/Berlin receipt time, translated labels).
     *
     * @return PreviewData
     */
    public static function data(Withdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'received' => (string) $withdrawal->created_at?->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'name' => $withdrawal->name,
            'email' => $withdrawal->email,
            'order' => $withdrawal->order_number,
            'subject' => $withdrawal->subject,
            'level' => $withdrawal->spam_level->getLabel(),
            'levelColor' => $withdrawal->spam_level->getColor(),
            'reasons' => SpamReasonLabels::render($withdrawal->spam_reason),
            'decision' => $withdrawal->verdictLabel(),
        ];
    }

    /**
     * The display values of the given table rows, keyed by record id — what the client switches between.
     *
     * @param  Collection<array-key, mixed>|Paginator<array-key, mixed>|CursorPaginator<array-key, mixed>  $records  the table's page records
     * @return array<int, PreviewData>
     */
    public static function dataFor(Collection|Paginator|CursorPaginator $records): array
    {
        $data = [];

        if ($records instanceof Paginator || $records instanceof CursorPaginator) {
            $records = $records->items();
        }

        foreach ($records as $record) {
            if ($record instanceof Withdrawal) {
                $data[$record->id] = self::data($record);
            }
        }

        return $data;
    }

    /** The newest withdrawal of a list tab ("spam" or, for any other value, the inbox); null when the tab is empty. */
    public static function newestIn(?string $tab): ?Withdrawal
    {
        /** @var Builder<Withdrawal> $query */
        $query = Withdrawal::query();

        $tab === 'spam' ? $query->spamFolder() : $query->inbox();

        return $query->latest('created_at')->latest('id')->first();
    }
}
