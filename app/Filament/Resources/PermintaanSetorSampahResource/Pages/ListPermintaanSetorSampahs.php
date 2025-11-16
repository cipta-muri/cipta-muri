<?php

namespace App\Filament\Resources\PermintaanSetorSampahResource\Pages;

use App\Filament\Resources\PermintaanSetorSampahResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPermintaanSetorSampahs extends ListRecords
{
    protected static string $resource = PermintaanSetorSampahResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
