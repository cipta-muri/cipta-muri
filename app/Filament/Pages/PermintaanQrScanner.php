<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;

class PermintaanQrScanner extends Page
{
    use HasHexaLite;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static string $view = 'filament.pages.permintaan-qr-scanner';

    protected static ?string $navigationLabel = 'Scan QR';

    protected static ?int $navigationSort = 10;

    public $hexaSort = 8;

    public function defineGates()
    {
        return [
            'permintaan.scan' => __('Akses Halaman QR Scanner Permintaan'),
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('permintaan.scan');
    }
}
