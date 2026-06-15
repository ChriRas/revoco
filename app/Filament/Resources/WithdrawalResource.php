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

    /** @var string|BackedEnum|null */
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Withdrawals';

    protected static ?string $modelLabel = 'Withdrawal';

    protected static ?string $pluralModelLabel = 'Withdrawals';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('d.m.Y H:i', 'Europe/Berlin')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(60),

                IconColumn::make('spam')
                    ->label('Spam')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                IconColumn::make('handled_at')
                    ->label('Handled')
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
                    ->label('Handled status')
                    ->nullable()
                    ->trueLabel('Handled only')
                    ->falseLabel('Unhandled only'),

                TernaryFilter::make('spam')
                    ->label('Spam status')
                    ->trueLabel('Spam only')
                    ->falseLabel('Not spam only'),

                Filter::make('date_range')
                    ->label('Date range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
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
                    ->label(fn (Withdrawal $record): string => $record->isHandled() ? 'Unmark handled' : 'Mark handled')
                    ->icon(fn (Withdrawal $record): string => $record->isHandled() ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Withdrawal $record): string => $record->isHandled() ? 'gray' : 'success')
                    ->action(function (Withdrawal $record): void {
                        $record->handled_at = $record->isHandled() ? null : Carbon::now();
                        $record->save();
                    })
                    ->requiresConfirmation(false),
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
                Section::make('Submitter details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Name'),
                        TextEntry::make('email')->label('Email'),
                        TextEntry::make('order_number')->label('Order number')->placeholder('—'),
                        TextEntry::make('locale')->label('Locale'),
                    ]),

                Section::make('Withdrawal statement')
                    ->schema([
                        TextEntry::make('subject')->label('Subject')->columnSpanFull(),
                    ]),

                Section::make('Triage')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('spam')
                            ->label('Spam signal')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Spam' : 'Not spam')
                            ->color(fn (bool $state): string => $state ? 'danger' : 'success'),
                        TextEntry::make('spam_reason')->label('Spam reason')->placeholder('—'),
                    ]),

                Section::make('Status & timestamps')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('handled_at')
                            ->label('Handled at')
                            ->dateTime('d.m.Y H:i', 'Europe/Berlin')
                            ->placeholder('Not handled'),
                        TextEntry::make('created_at')
                            ->label('Received at')
                            ->dateTime('d.m.Y H:i', 'Europe/Berlin'),
                        TextEntry::make('updated_at')
                            ->label('Last updated')
                            ->dateTime('d.m.Y H:i', 'Europe/Berlin'),
                    ]),
            ]);
    }

    /** @return array<string, \Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawals::route('/'),
            'view' => Pages\ViewWithdrawal::route('/{record}'),
        ];
    }
}
