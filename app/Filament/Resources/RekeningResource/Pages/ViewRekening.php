<?php

namespace App\Filament\Resources\RekeningResource\Pages;

use App\Filament\Resources\RekeningResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRekening extends ViewRecord
{
    protected static string $resource = RekeningResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('rekening.index');
    }
}
