<?php

namespace App\Filament\Resources\SampahResource\Widgets;

use App\Models\SetorSampah;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SampahSetoranHarianTable extends BaseWidget
{
    protected static ?string $heading = 'Rekap Penyetoran Harian (Semua Waktu)';

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $query = SetorSampah::query()
            ->selectRaw('tanggal, SUM(berat) as total_berat')
            ->whereNull('deleted_at')
            ->groupBy('tanggal')
            ->orderByDesc('tanggal');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->translatedFormat('d M Y'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_berat')
                    ->label('Total Berat (Kg)')
                    ->numeric(decimalPlaces: 4, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25, 50, 'all']);
    }

    public function getTableRecordKey(Model $record): string
    {
        return 'harian-' . $record->getAttribute('tanggal');
    }
}
