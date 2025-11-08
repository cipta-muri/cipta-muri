<?php

namespace App\Filament\Resources\SampahResource\Pages;

use App\Filament\Resources\SampahResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSampah extends ViewRecord
{
    protected static string $resource = SampahResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('sampah.index');
    }
}

