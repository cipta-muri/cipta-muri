<?php

namespace App\Filament\Resources\SampahResource\Widgets;

use App\Models\SetorSampah;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Carbon;

class SampahSetoranChart extends LineChartWidget
{
    protected static ?string $heading = 'Statistik Penyetoran Sampah (12 Bulan Terakhir)';

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = [
        'lg' => 2,
    ];

    protected function getData(): array
    {
        $end = now()->endOfMonth();
        $start = $end->copy()->subMonths(11)->startOfMonth();

        $rawData = SetorSampah::query()
            ->select(['tanggal', 'berat'])
            ->whereNull('deleted_at')
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn ($item) => Carbon::parse($item->tanggal)->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('berat'))
            ->all();

        $labels = [];
        $data = [];

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->translatedFormat('M Y');
            $data[] = round($rawData[$key] ?? 0, 4);

            $cursor->addMonth();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Penyetoran (Kg)',
                    'data' => $data,
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.2)',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
