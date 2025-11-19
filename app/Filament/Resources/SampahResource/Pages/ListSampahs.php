<?php

namespace App\Filament\Resources\SampahResource\Pages;

use App\Filament\Resources\SampahResource;
use App\Filament\Resources\SampahResource\Widgets;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSampahs extends ListRecords
{
    protected static string $resource = SampahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->visible(fn () => hexa()->can('sampah.create')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\SampahSetoranChart::class,
            Widgets\SampahSetoranHarianTable::class,
            Widgets\SampahStatsOverview::class,
        ];
    }
}
