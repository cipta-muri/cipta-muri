<?php

namespace App\Enums;

enum PermintaanStatus: string
{
    case Draft = 'draft';
    case MenungguKonfirmasi = 'menunggu_konfirmasi';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::MenungguKonfirmasi => 'Menunggu Konfirmasi',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::MenungguKonfirmasi => 'warning',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
