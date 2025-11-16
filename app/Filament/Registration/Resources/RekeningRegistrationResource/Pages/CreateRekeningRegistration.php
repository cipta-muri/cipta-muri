<?php

namespace App\Filament\Registration\Resources\RekeningRegistrationResource\Pages;

use App\Filament\Registration\Resources\RekeningRegistrationResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateRekeningRegistration extends CreateRecord
{
    protected static string $resource = RekeningRegistrationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = User::query()
            ->where('name', $data['nama'] ?? null)
            ->first();

        if (!$user) {
            $user = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'Super Admin'))
                ->orderBy('created_at')
                ->first()
                ?? User::query()->orderBy('created_at')->first();
        }

        if ($user) {
            $data['user_id'] = $user->id;
        }

        $statusDesa = filter_var($data['status_desa'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $statusDesa = $statusDesa ?? (bool) $data['status_desa'];

        $statusPart = $statusDesa ? '0' : '1';

        $now = Carbon::now();
        $datePart = $now->format('my');

        $lastRekening = \App\Models\Rekening::query()
            ->withTrashed()
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

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Registrasi berhasil')
            ->success()
            ->body('Terima kasih, data registrasi nasabah telah kami terima.');
    }
}
