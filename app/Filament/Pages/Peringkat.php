<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Peringkat extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static string $view = 'filament.pages.peringkat';

    protected static ?string $title = 'Peringkat';

    protected static ?string $navigationLabel = 'Peringkat';

    protected static ?int $navigationSort = 2;
}

