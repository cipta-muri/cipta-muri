<?php

namespace App\Filament\Resources\RekeningResource\Pages;

use App\Filament\Resources\RekeningResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRekening extends EditRecord
{
    protected static string $resource = RekeningResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('rekening.update');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => hexa()->can('rekening.delete')),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // This logic is identical to afterCreate()
        if (! $this->record->status_lengkap) {
            Notification::make()
                ->title('Peringatan Data Belum Lengkap')
                ->body('Perubahan berhasil disimpan, namun data nasabah ini belum lengkap.')
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
