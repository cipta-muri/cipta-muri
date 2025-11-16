<?php

namespace App\Filament\Resources\WithdrawRequestResource\Widgets;

use App\Enums\PermintaanStatus;
use App\Filament\Resources\PermintaanTarikSaldoResource;
use App\Models\PermintaanTarikSaldo;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;

class PermintaanTarikSaldoTable extends TableWidget
{
    protected static ?string $heading = 'Permintaan Tarik Saldo';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return hexa()->can('permintaan_tarik_saldo.index');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PermintaanTarikSaldo::query()->latest('requested_at'))
            ->columns([
                Tables\Columns\TextColumn::make('rekening.nama')
                    ->label('Nasabah')
                    ->description(fn (PermintaanTarikSaldo $record) => $record->rekening?->no_rekening)
                    ->searchable(['rekening.nama', 'rekening.no_rekening'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('jenis')
                    ->label('Metode')
                    ->formatStateUsing(fn ($state) => ucfirst((string) $state)),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => ($state instanceof PermintaanStatus ? $state : PermintaanStatus::tryFrom($state))?->label() ?? '-')
                    ->color(function ($state) {
                        $enum = $state instanceof PermintaanStatus ? $state : PermintaanStatus::tryFrom($state);

                        return match ($enum) {
                            PermintaanStatus::Draft => 'gray',
                            PermintaanStatus::MenungguKonfirmasi => 'warning',
                            PermintaanStatus::Disetujui => 'success',
                            PermintaanStatus::Ditolak => 'danger',
                            default => 'gray',
                        };
                    }),
                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(PermintaanStatus::options()),
                Tables\Filters\Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('requested_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($query, $date) => $query->whereDate('requested_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('confirm')
                        ->label('Konfirmasi')
                        ->color('success')
                        ->icon('heroicon-o-check')
                        ->visible(fn (PermintaanTarikSaldo $record) => $record->isWaitingConfirmation() && hexa()->can('permintaan_tarik_saldo.update'))
                        ->form([
                            Forms\Components\Textarea::make('note')
                                ->label('Catatan Admin')
                                ->rows(3),
                        ])
                        ->action(function (PermintaanTarikSaldo $record, array $data) {
                            try {
                                $record->confirm(
                                    auth()->user(),
                                    'table',
                                    $data['note'] ?? null,
                                    null
                                );

                                Notification::make()
                                    ->title('Permintaan disetujui')
                                    ->body('Data telah dipindahkan ke daftar penarikan saldo.')
                                    ->success()
                                    ->send();
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title('Gagal memproses')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            } catch (\Throwable $exception) {
                                report($exception);
                                Notification::make()
                                    ->title('Terjadi kesalahan')
                                    ->body('Tidak dapat memproses permintaan saat ini.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Tables\Actions\Action::make('reject')
                        ->label('Tolak')
                        ->color('danger')
                        ->icon('heroicon-o-x-mark')
                        ->visible(fn (PermintaanTarikSaldo $record) => $record->isWaitingConfirmation() && hexa()->can('permintaan_tarik_saldo.update'))
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (PermintaanTarikSaldo $record, array $data) {
                            try {
                                $record->reject(auth()->user(), $data['reason'], 'table');

                                Notification::make()
                                    ->title('Permintaan ditolak')
                                    ->success()
                                    ->send();
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title('Gagal memproses')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            } catch (\Throwable $exception) {
                                report($exception);
                                Notification::make()
                                    ->title('Terjadi kesalahan')
                                    ->body('Tidak dapat memproses permintaan saat ini.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Tables\Actions\Action::make('refresh-token')
                        ->label('Refresh Token')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn () => hexa()->can('permintaan_tarik_saldo.update'))
                        ->action(function (PermintaanTarikSaldo $record) {
                            $record->regeneratePermintaanToken();
                            Notification::make()->title('Token diperbarui')->success()->send();
                        }),
                ]),
                Tables\Actions\ViewAction::make()
                    ->url(fn (PermintaanTarikSaldo $record) => PermintaanTarikSaldoResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }
}
