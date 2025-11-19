<?php

namespace App\Filament\Resources\SampahResource\Pages;

use App\Filament\Resources\SampahResource;
use App\Filament\Resources\SampahResource\Widgets;
use Filament\Resources\Pages\ViewRecord;

class ViewSampah extends ViewRecord
{
    protected static string $resource = SampahResource::class;

    protected static string $view = 'filament.resources.sampah-resource.pages.view-record';

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('sampah.index');
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
