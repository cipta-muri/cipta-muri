<?php

namespace App\Filament\Resources\SampahResource\Widgets;

use App\Models\Sampah;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SampahStatsOverview extends BaseWidget
{
    protected static bool $isLazy = true;

    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $totalBerat = (float) Sampah::query()->sum('total_berat_terkumpul');
        $formattedBerat = number_format($totalBerat, 2, ',', '.');

        return [
            Stat::make('Berat Total Sampah', "{$formattedBerat} Kg")
                ->description('Total berat dari semua jenis sampah yang tersimpan')
                ->descriptionIcon('heroicon-m-scale')
                ->color('success'),
        ];
    }
}
