<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalResource\Pages;

use App\Filament\Resources\WithdrawalResource;
use App\Models\Withdrawal;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

final class ViewWithdrawal extends ViewRecord
{
    protected static string $resource = WithdrawalResource::class;

    /**
     * Header-level "handled" toggle action on the view page.
     * No edit or delete actions — the § 356a record must stay immutable.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggle_handled')
                ->label(fn (): string => $this->record instanceof Withdrawal && $this->record->isHandled()
                    ? __('panel.action.unmark_handled')
                    : __('panel.action.mark_handled'))
                ->icon(fn (): string => $this->record instanceof Withdrawal && $this->record->isHandled()
                    ? 'heroicon-o-x-circle'
                    : 'heroicon-o-check-circle')
                ->color(fn (): string => $this->record instanceof Withdrawal && $this->record->isHandled()
                    ? 'gray'
                    : 'success')
                ->action(function (): void {
                    if (! $this->record instanceof Withdrawal) {
                        return;
                    }
                    $this->record->handled_at = $this->record->isHandled() ? null : Carbon::now();
                    $this->record->save();
                    $this->refreshFormData(['handled_at']);
                }),
        ];
    }
}
