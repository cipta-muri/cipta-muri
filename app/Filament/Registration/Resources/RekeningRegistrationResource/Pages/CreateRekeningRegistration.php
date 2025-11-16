<?php

namespace App\Filament\Registration\Resources\RekeningRegistrationResource\Pages;

use App\Filament\Registration\Resources\RekeningRegistrationResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

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

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('create');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Registrasi berhasil')
            ->success()
            ->body('Terima kasih, data registrasi nasabah telah kami terima.');
    }
}
