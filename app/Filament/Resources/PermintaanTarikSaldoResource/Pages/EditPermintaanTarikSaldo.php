<?php

namespace App\Filament\Resources\PermintaanTarikSaldoResource\Pages;

use App\Filament\Resources\PermintaanTarikSaldoResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPermintaanTarikSaldo extends EditRecord
{
    protected static string $resource = PermintaanTarikSaldoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirm')
                ->label('Konfirmasi')
                ->color('success')
                ->icon('heroicon-o-check')
                ->visible(fn () => $this->getRecord()->isWaitingConfirmation() && hexa()->can('permintaan_tarik_saldo.update'))
                ->form([
                    Forms\Components\Textarea::make('note')
                        ->label('Catatan Admin')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    try {
                        $this->getRecord()->confirm(auth()->user(), 'via_qr', $data['note'] ?? null);
                        Notification::make()->title('Permintaan disetujui')->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->title('Gagal memproses')->body($exception->getMessage())->danger()->send();
                    } catch (\Throwable $exception) {
                        report($exception);
                        Notification::make()->title('Terjadi kesalahan')->danger()->send();
                    }
                }),
            Actions\Action::make('reject')
                ->label('Tolak')
                ->color('danger')
                ->icon('heroicon-o-x-mark')
                ->visible(fn () => $this->getRecord()->isWaitingConfirmation() && hexa()->can('permintaan_tarik_saldo.update'))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    try {
                        $this->getRecord()->reject(auth()->user(), $data['reason'], 'via_qr');
                        Notification::make()->title('Permintaan ditolak')->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->title('Gagal memproses')->body($exception->getMessage())->danger()->send();
                    } catch (\Throwable $exception) {
                        report($exception);
                        Notification::make()->title('Terjadi kesalahan')->danger()->send();
                    }
                }),
            Actions\Action::make('refresh-token')
                ->label('Refresh Token')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => hexa()->can('permintaan_tarik_saldo.update'))
                ->action(function () {
                    $this->getRecord()->regeneratePermintaanToken();
                    Notification::make()->title('Token diperbarui')->success()->send();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
