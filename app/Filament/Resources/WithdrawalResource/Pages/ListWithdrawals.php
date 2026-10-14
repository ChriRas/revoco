<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalResource\Pages;

use App\Filament\Resources\WithdrawalResource;
use App\Filament\Resources\WithdrawalResource\WithdrawalPreview;
use App\Models\Withdrawal;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
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

    /** The default list layout with the live preview on top, above the tabs and the table. */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('panels.withdrawal-preview')
                    ->viewData(fn (): array => [
                        'default' => ($newest = WithdrawalPreview::newestIn($this->activeTab)) === null
                            ? null
                            : WithdrawalPreview::data($newest),
                        'rows' => WithdrawalPreview::dataFor($this->getTableRecords()),
                    ]),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }
}
