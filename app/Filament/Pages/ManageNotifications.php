<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Mail\WithdrawalNotification;
use App\Models\Withdrawal;
use App\Settings\NotificationSettings;
use App\Support\NotificationRecipient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Operator settings page for delivery notifications. Holds the recipient address
 * for the withdrawal notification e-mail (App\Settings\NotificationSettings),
 * decoupled from the mail from-address, plus a "send test e-mail" action so the
 * operator can prove delivery. SMTP transport itself stays in MAIL_* env — this
 * page never touches credentials.
 */
final class ManageNotifications extends SettingsPage
{
    protected static string $settings = NotificationSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    public static function getNavigationLabel(): string
    {
        return __('panel.settings.notification.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.settings.navigation_group');
    }

    public function getTitle(): string
    {
        return __('panel.settings.notification.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                // Show the currently effective recipient (post last save) so the
                // operator sees exactly where alerts go — including when a fallback
                // (env / imprint) is in effect, or nothing is configured.
                ->description(function (): string {
                    $recipient = NotificationRecipient::resolve();

                    return $recipient === null
                        ? __('panel.settings.notification.effective_none')
                        : __('panel.settings.notification.effective', ['email' => $recipient]);
                })
                ->components([
                    TextInput::make('notification_email')
                        ->label(__('panel.settings.notification.email.label'))
                        ->helperText(__('panel.settings.notification.email.help'))
                        ->email()
                        ->maxLength(512),
                ]),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label(__('panel.settings.notification.test.button'))
                ->icon('heroicon-o-paper-airplane')
                ->action(fn () => $this->sendTest()),
        ];
    }

    /**
     * Send a representative notification to the resolved recipient, synchronously,
     * so the operator gets immediate delivery feedback. Any transport error is
     * surfaced as a Filament notification rather than thrown.
     */
    private function sendTest(): void
    {
        $recipient = NotificationRecipient::resolve();

        if ($recipient === null) {
            Notification::make()
                ->title(__('panel.settings.notification.test.none'))
                ->danger()
                ->send();

            return;
        }

        $locale = Config::string('app.default_locale');

        $sample = new Withdrawal([
            'name' => 'Max Mustermann',
            'email' => 'kunde@example.com',
            'order_number' => 'TEST-0001',
            'subject' => __('panel.settings.notification.test.sample_subject'),
            'locale' => $locale,
            'spam' => false,
            'spam_reason' => null,
        ]);
        $sample->created_at = now();

        try {
            Mail::to($recipient)->sendNow(
                (new WithdrawalNotification($sample))->locale($locale),
            );

            Notification::make()
                ->title(__('panel.settings.notification.test.sent', ['email' => $recipient]))
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('panel.settings.notification.test.failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
