<?php

namespace App\Filament\Resources\SetorSampahResource\Widgets;

use App\Enums\PermintaanStatus;
use App\Filament\Resources\PermintaanSetorSampahResource;
use App\Models\PermintaanSetorSampah;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Validation\ValidationException;

class PermintaanSetorSampahTable extends TableWidget
{
    protected static ?string $heading = 'Permintaan Setor Sampah';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return hexa()->can('permintaan_setor_sampah.index');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PermintaanSetorSampah::query()->latest('requested_at'))
            ->columns([
                Tables\Columns\TextColumn::make('rekening.nama')
                    ->label('Nasabah')
                    ->description(fn (PermintaanSetorSampah $record) => $record->rekening?->no_rekening)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_berat')
                    ->label('Total Berat (Kg)')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_saldo_dihasilkan')
                    ->label('Total Saldo')
                    ->money('IDR')
                    ->sortable(),
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
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('confirm')
                        ->label('Konfirmasi')
                        ->color('success')
                        ->icon('heroicon-o-check')
                        ->visible(fn (PermintaanSetorSampah $record) => $record->isWaitingConfirmation() && hexa()->can('permintaan_setor_sampah.update'))
                        ->form([
                            Forms\Components\Textarea::make('note')
                                ->label('Catatan Admin')
                                ->rows(3),
                        ])
                        ->action(function (PermintaanSetorSampah $record, array $data) {
                            try {
                                $record->confirm(auth()->user(), 'table', $data['note'] ?? null);

                                Notification::make()
                                    ->title('Setoran dikonfirmasi')
                                    ->success()
                                    ->send();
                            } catch (ValidationException $exception) {
                                Notification::make()->title('Gagal memproses')->body($exception->getMessage())->danger()->send();
                            } catch (\Throwable $exception) {
                                report($exception);
                                Notification::make()->title('Terjadi kesalahan')->danger()->send();
                            }
                        }),
                    Tables\Actions\Action::make('reject')
                        ->label('Tolak')
                        ->color('danger')
                        ->icon('heroicon-o-x-mark')
                        ->visible(fn (PermintaanSetorSampah $record) => $record->isWaitingConfirmation() && hexa()->can('permintaan_setor_sampah.update'))
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (PermintaanSetorSampah $record, array $data) {
                            try {
                                $record->reject(auth()->user(), $data['reason'], 'table');

                                Notification::make()->title('Permintaan ditolak')->success()->send();
                            } catch (ValidationException $exception) {
                                Notification::make()->title('Gagal memproses')->body($exception->getMessage())->danger()->send();
                            } catch (\Throwable $exception) {
                                report($exception);
                                Notification::make()->title('Terjadi kesalahan')->danger()->send();
                            }
                        }),
                    Tables\Actions\Action::make('refresh-token')
                        ->label('Refresh Token')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn () => hexa()->can('permintaan_setor_sampah.update'))
                        ->action(function (PermintaanSetorSampah $record) {
                            $record->regeneratePermintaanToken();
                            Notification::make()->title('Token diperbarui')->success()->send();
                        }),
                ]),
                Tables\Actions\ViewAction::make()
                    ->url(fn (PermintaanSetorSampah $record) => PermintaanSetorSampahResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }
}
