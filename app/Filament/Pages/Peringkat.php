<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;

class Peringkat extends Page
{
    use HasHexaLite;

    public function defineGates()
    {
        return [
            'peringkat.index' => __('Lihat Peringkat Nasabah'),
        ];
    }

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static string $view = 'filament.pages.peringkat';

    protected static ?string $title = 'Peringkat';

    protected static ?string $navigationLabel = 'Peringkat';

    protected static ?int $navigationSort = 2;

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('peringkat.index');
    }
}
