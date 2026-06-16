<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalResource\Pages;
use App\Models\Withdrawal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class WithdrawalResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationLabel(): string
    {
        return __('panel.resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resource.plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('panel.column.received'))
                    ->dateTime('d.m.Y H:i', 'Europe/Berlin')
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('panel.column.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order_number')
                    ->label(__('panel.column.order_number'))
                    ->searchable()
                    ->placeholder('—'),

                IconColumn::make('spam')
                    ->label(__('panel.column.no_spam'))
                    ->boolean()
                    ->getStateUsing(fn (Withdrawal $record): bool => ! $record->spam)
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                IconColumn::make('handled_at')
                    ->label(__('panel.column.handled'))
                    ->boolean()
                    ->getStateUsing(fn (Withdrawal $record): bool => $record->isHandled())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('handled_at')
                    ->label(__('panel.filter.handled_status'))
                    ->nullable()
                    ->trueLabel(__('panel.filter.handled_only'))
                    ->falseLabel(__('panel.filter.unhandled_only')),

                TernaryFilter::make('spam')
                    ->label(__('panel.filter.spam_status'))
                    ->trueLabel(__('panel.filter.spam_only'))
                    ->falseLabel(__('panel.filter.not_spam_only')),

                Filter::make('date_range')
                    ->label(__('panel.filter.date_range'))
                    ->form([
                        DatePicker::make('from')->label(__('panel.filter.date_from')),
                        DatePicker::make('until')->label(__('panel.filter.date_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var string|null $from */
                        $from = $data['from'] ?? null;
                        /** @var string|null $until */
                        $until = $data['until'] ?? null;

                        return $query
                            ->when($from, fn (Builder $q): Builder => $q->whereDate('created_at', '>=', $from))
                            ->when($until, fn (Builder $q): Builder => $q->whereDate('created_at', '<=', $until));
                    }),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('toggle_handled')
                    ->label(fn (Withdrawal $record): string => $record->isHandled()
                        ? __('panel.action.unmark_handled')
                        : __('panel.action.mark_handled'))
                    ->icon(fn (Withdrawal $record): string => $record->isHandled() ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Withdrawal $record): string => $record->isHandled() ? 'gray' : 'success')
                    ->action(function (Withdrawal $record): void {
                        $record->handled_at = $record->isHandled() ? null : Carbon::now();
                        $record->save();
                    }),
            ])
            ->bulkActions([])
            ->recordAction(null)
            ->recordUrl(
                fn (Withdrawal $record): string => Pages\ViewWithdrawal::getUrl(['record' => $record]),
            );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.infolist.section.submitter'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('panel.infolist.field.name')),
                        TextEntry::make('email')->label(__('panel.infolist.field.email')),
                        TextEntry::make('order_number')->label(__('panel.infolist.field.order_number'))->placeholder('—'),
                        TextEntry::make('locale')->label(__('panel.infolist.field.locale')),
                    ]),

                Section::make(__('panel.infolist.section.statement'))
                    ->schema([
                        TextEntry::make('subject')->label(__('panel.infolist.field.subject'))->columnSpanFull(),
                    ]),

                Section::make(__('panel.infolist.section.triage'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('spam')
                            ->label(__('panel.infolist.field.spam_signal'))
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state
                                ? __('panel.infolist.spam.yes')
                                : __('panel.infolist.spam.no'))
                            ->color(fn (bool $state): string => $state ? 'danger' : 'success'),
                        TextEntry::make('spam_reason')->label(__('panel.infolist.field.spam_reason'))->placeholder('—'),
                    ]),

                Section::make(__('panel.infolist.section.status'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('handled_at')
                            ->label(__('panel.infolist.field.handled_at'))
                            ->dateTime('d.m.Y H:i', 'Europe/Berlin')
                            ->placeholder(__('panel.infolist.not_handled')),
                        TextEntry::make('created_at')
                            ->label(__('panel.infolist.field.received_at'))
                            ->dateTime('d.m.Y H:i', 'Europe/Berlin'),
                        TextEntry::make('updated_at')
                            ->label(__('panel.infolist.field.last_updated'))
                            ->dateTime('d.m.Y H:i', 'Europe/Berlin'),
                    ]),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawals::route('/'),
            'view' => Pages\ViewWithdrawal::route('/{record}'),
        ];
    }
}
