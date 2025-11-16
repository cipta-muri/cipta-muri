<?php

namespace App\Filament\Resources\RekeningResource\Pages;

use App\Filament\Resources\RekeningResource;
use App\Models\Rekening;
use App\Models\SaldoTransaction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Support\Str;
use Filament\Support\Facades\Dialog;
class CreateRekening extends CreateRecord
{
    protected static string $resource = RekeningResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('rekening.create');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Struktur Nomor Rekening: 1 (status desa) + 2 (tahun) + 2 (bulan) + 3 (urut) = 8 digit

        // Pastikan status_desa dikonversi menjadi boolean terlebih dahulu
        $statusDesa = filter_var($data['status_desa'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $statusDesa = $statusDesa ?? (bool) $data['status_desa'];

        // 1. Bagian Status (1 digit: 1 untuk penduduk desa, 0 untuk penduduk luar desa)
        $statusPart = $statusDesa ? '0' : '1';

        // 2. Bagian Tanggal (2 digit tahun + 2 digit bulan)
        $now = Carbon::now();
        $datePart = $now->format('ym');

        // 3. Nomor urut berdasarkan kombinasi status + tahun/bulan
        $lastRekening = Rekening::query()
            ->where('status_desa', $statusDesa)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->orderByDesc('no_rekening')
            ->first();

        $sequence = 1;
        if ($lastRekening) {
            $sequence = ((int) substr($lastRekening->no_rekening, -3)) + 1;
        }

        $sequencePart = str_pad($sequence, 3, '0', STR_PAD_LEFT);

        $data['no_rekening'] = $statusPart . $datePart . $sequencePart;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Create initial balance transaction if saldo_awal > 0
        if ($this->record->saldo_awal && $this->record->saldo_awal > 0) {
            SaldoTransaction::create([
                'rekening_id' => $this->record->id,
                'type' => 'credit',
                'amount' => $this->record->saldo_awal,
                'description' => 'Saldo awal rekening',
                'transactable_type' => null,
                'transactable_id' => null,
            ]);
        }

        // Check the status_lengkap field of the record that was just created.
        // The value was set by the `saving` event in your Rekening model.
        if (!$this->record->status_lengkap) {
            Notification::make()
                ->title('Peringatan Data Belum Lengkap')
                ->body('Rekening berhasil dibuat, namun datanya belum lengkap. Mohon untuk melengkapinya segera.')
                ->warning() // Gives the notification a yellow/orange color
                ->persistent() // Requires the user to dismiss it
                ->send();
        }
    }
}
