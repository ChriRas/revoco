<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalResource\Pages;

use App\Filament\Resources\WithdrawalResource;
use Filament\Resources\Pages\ListRecords;

final class ListWithdrawals extends ListRecords
{
    protected static string $resource = WithdrawalResource::class;

    /** No header create-action — resource is read-only. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
