<?php

namespace App\Filament\Resources\SampahResource\Pages;

use App\Filament\Resources\SampahResource;
use App\Filament\Resources\SampahResource\Widgets;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSampah extends EditRecord
{
    protected static string $resource = SampahResource::class;

    protected static string $view = 'filament.resources.sampah-resource.pages.edit-record';

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('sampah.update');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->visible(fn () => hexa()->can('sampah.delete')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getFormWidgets(): array
    {
        return [
            Widgets\SampahRecordStatsOverview::class,
            Widgets\SampahRecordSetoranHarianTable::class,
        ];
    }

    protected function getFormWidgetsColumns(): int|string|array
    {
        return [
            'lg' => 2,
        ];
    }
}
