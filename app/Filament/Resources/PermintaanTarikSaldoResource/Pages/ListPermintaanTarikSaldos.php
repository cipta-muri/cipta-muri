<?php

namespace App\Filament\Resources\PermintaanTarikSaldoResource\Pages;

use App\Filament\Resources\PermintaanTarikSaldoResource;
use Filament\Resources\Pages\ListRecords;

class ListPermintaanTarikSaldos extends ListRecords
{
    protected static string $resource = PermintaanTarikSaldoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
