<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalResource\Pages;

use App\Filament\Resources\WithdrawalResource;
use App\Filament\Resources\WithdrawalResource\WithdrawalActions;
use Filament\Resources\Pages\ViewRecord;

final class ViewWithdrawal extends ViewRecord
{
    protected static string $resource = WithdrawalResource::class;

    /**
     * The verdict actions and the "handled" toggle (hidden for confirmed spam) in the header.
     * No edit or delete actions — the § 356a record must stay immutable.
     */
    protected function getHeaderActions(): array
    {
        $refresh = fn (ViewWithdrawal $livewire) => $livewire->refreshFormData(['spam_verdict', 'spam_verdict_at', 'handled_at']);

        return [
            WithdrawalActions::markAsSpam()->after($refresh),
            WithdrawalActions::markAsNotSpam()->after($refresh),
            WithdrawalActions::toggleHandled()->after($refresh),
        ];
    }
}
