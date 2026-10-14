<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalResource;

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Actions\MarkWithdrawalAsSpam;
use App\Enums\SpamVerdict;
use App\Models\Withdrawal;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The operator's actions on withdrawals, built once and shared by the list rows, the bulk
 * toolbar and the view-page header: the spam verdicts (all logic lives in the two Actions)
 * and the list's icon row (view · spam · not spam · handled).
 */
final class WithdrawalActions
{
    /**
     * The list row's icon buttons, in display order.
     *
     * @return list<Action>
     */
    public static function row(): array
    {
        return [
            ViewAction::make()
                ->iconButton()
                ->color('gray')
                ->tooltip(__('filament-actions::view.single.label')),
            self::rowMarkAsSpam(),
            self::rowMarkAsNotSpam(),
            self::rowToggleHandled(),
        ];
    }

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

    /**
     * The list row's "Mark as spam": an icon button that is always shown. Red while the row is
     * confirmed spam (then disabled, its tooltip names the verdict), neutral otherwise.
     */
    public static function rowMarkAsSpam(): Action
    {
        return self::markAsSpam()
            ->iconButton()
            ->icon('heroicon-o-no-symbol')
            ->visible(true)
            ->disabled(fn (Withdrawal $record): bool => ! $record->canBeMarkedSpam())
            ->color(fn (Withdrawal $record): string => $record->isConfirmedSpam() ? 'danger' : 'gray')
            ->tooltip(fn (Withdrawal $record): string => $record->isConfirmedSpam()
                ? (string) $record->verdictLabel()
                : (string) __('panel.action.mark_spam'));
    }

    /**
     * The list row's "Not spam": an icon button that is always shown. Green while the row is
     * corrected to "not spam", neutral otherwise; disabled where it would change nothing.
     */
    public static function rowMarkAsNotSpam(): Action
    {
        return self::markAsNotSpam()
            ->iconButton()
            ->icon('heroicon-o-shield-check')
            ->visible(true)
            ->disabled(fn (Withdrawal $record): bool => ! $record->canBeMarkedNotSpam())
            ->color(fn (Withdrawal $record): string => $record->spam_verdict === SpamVerdict::NotSpam ? 'success' : 'gray')
            ->tooltip(fn (Withdrawal $record): string => $record->spam_verdict === SpamVerdict::NotSpam
                ? (string) __('panel.action.is_not_spam')
                : (string) __('panel.action.not_spam'));
    }

    /**
     * "Mark as handled" / "Unmark" — the view-page header button. Hidden for confirmed spam (by the operator or
     * auto-sorted): spam needs no handling.
     */
    public static function toggleHandled(): Action
    {
        return Action::make('toggle_handled')
            ->label(fn (Withdrawal $record): string => $record->isHandled()
                ? __('panel.action.unmark_handled')
                : __('panel.action.mark_handled'))
            ->icon(fn (Withdrawal $record): string => $record->isHandled() ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
            ->color(fn (Withdrawal $record): string => $record->isHandled() ? 'gray' : 'success')
            ->visible(fn (Withdrawal $record): bool => ! $record->isConfirmedSpam())
            ->action(function (Withdrawal $record): void {
                $record->handled_at = $record->isHandled() ? null : Carbon::now();
                $record->save();
            });
    }

    /**
     * The list row's "handled" toggle: an icon button that is always shown, green while the row is handled, neutral
     * otherwise; disabled (not hidden) for confirmed spam, so the icon row stays aligned.
     */
    public static function rowToggleHandled(): Action
    {
        return self::toggleHandled()
            ->iconButton()
            ->icon('heroicon-o-check-circle')
            ->visible(true)
            ->disabled(fn (Withdrawal $record): bool => $record->isConfirmedSpam())
            ->color(fn (Withdrawal $record): string => $record->isHandled() && ! $record->isConfirmedSpam() ? 'success' : 'gray')
            ->tooltip(fn (Withdrawal $record): string => match (true) {
                $record->isConfirmedSpam() => __('panel.action.handled_not_needed'),
                $record->isHandled() => __('panel.action.unmark_handled'),
                default => __('panel.action.mark_handled'),
            });
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
