<?php

namespace App\Filament\Resources\SampahResource\Widgets;

use App\Models\Sampah;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SampahRecordStatsOverview extends BaseWidget
{
    public ?Sampah $record = null;

    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        $totalBerat = (float) ($this->record?->total_berat_terkumpul ?? 0);
        $formatted = number_format($totalBerat, 4, ',', '.');

        return [
            Stat::make('Total Berat Jenis Ini', "{$formatted} Kg")
                ->description($this->record?->jenis_sampah ?? '-')
                ->descriptionIcon('heroicon-m-beaker')
                ->color('primary'),
        ];
    }
}

