<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalResource;

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Actions\MarkWithdrawalAsSpam;
use App\Models\Withdrawal;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Illuminate\Support\Collection;

/**
 * The operator's spam verdict actions, built once and shared by the list rows,
 * the bulk toolbar and the view-page header. All logic lives in the two Actions.
 */
final class SpamActions
{
    /** "Mark as spam" — available until the verdict is already "spam". */
    public static function markAsSpam(): Action
    {
        return Action::make('mark_spam')
            ->label(__('panel.action.mark_spam'))
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('panel.action.mark_spam_heading'))
            ->modalDescription(__('panel.action.mark_spam_description', ['days' => Withdrawal::SPAM_RETENTION_DAYS]))
            ->visible(fn (Withdrawal $record): bool => $record->canBeMarkedSpam())
            ->action(fn (Withdrawal $record): bool => app(MarkWithdrawalAsSpam::class)->handle($record));
    }

    /** "Not spam" — for confirmed spam, or an unjudged yellow or red row. */
    public static function markAsNotSpam(): Action
    {
        return Action::make('not_spam')
            ->label(__('panel.action.not_spam'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('panel.action.not_spam_heading'))
            ->modalDescription(__('panel.action.not_spam_description'))
            ->visible(fn (Withdrawal $record): bool => $record->canBeMarkedNotSpam())
            ->action(fn (Withdrawal $record): bool => app(MarkWithdrawalAsNotSpam::class)->handle($record));
    }

    /** Bulk "Mark as spam"; rows already confirmed as spam are skipped by the Action. */
    public static function bulkMarkAsSpam(): BulkAction
    {
        return BulkAction::make('mark_spam')
            ->label(__('panel.action.mark_spam'))
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('panel.action.mark_spam_heading'))
            ->modalDescription(__('panel.action.mark_spam_description', ['days' => Withdrawal::SPAM_RETENTION_DAYS]))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                /** @var Collection<int, Withdrawal> $records */
                $action = app(MarkWithdrawalAsSpam::class);

                foreach ($records as $record) {
                    if ($record->canBeMarkedSpam()) {
                        $action->handle($record);
                    }
                }
            });
    }

    /** Bulk "Not spam"; rows already corrected to "not spam" are skipped by the Action. */
    public static function bulkMarkAsNotSpam(): BulkAction
    {
        return BulkAction::make('not_spam')
            ->label(__('panel.action.not_spam'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('panel.action.not_spam_heading'))
            ->modalDescription(__('panel.action.not_spam_description'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                /** @var Collection<int, Withdrawal> $records */
                $action = app(MarkWithdrawalAsNotSpam::class);

                foreach ($records as $record) {
                    if ($record->canBeMarkedNotSpam()) {
                        $action->handle($record);
                    }
                }
            });
    }
}
