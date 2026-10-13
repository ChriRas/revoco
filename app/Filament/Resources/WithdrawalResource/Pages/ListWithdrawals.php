<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalResource\Pages;

use App\Filament\Resources\WithdrawalResource;
use App\Models\Withdrawal;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ListWithdrawals extends ListRecords
{
    protected static string $resource = WithdrawalResource::class;

    /** No header create-action — resource is read-only. */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * "Inbox" is the default tab; "Spam" holds red and confirmed rows.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'inbox' => Tab::make(__('panel.tab.inbox'))
                ->modifyQueryUsing(function (Builder $query): Builder {
                    /** @var Builder<Withdrawal> $query */
                    return $query->inbox();
                }),
            'spam' => Tab::make(__('panel.tab.spam'))
                ->modifyQueryUsing(function (Builder $query): Builder {
                    /** @var Builder<Withdrawal> $query */
                    return $query->spamFolder();
                }),
        ];
    }
}
