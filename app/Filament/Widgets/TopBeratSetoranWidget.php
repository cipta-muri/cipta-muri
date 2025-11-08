<?php

namespace App\Filament\Widgets;

use App\Models\Rekening;
use App\Models\SampahTransactions;
use App\Models\SetorSampah;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopBeratSetoranWidget extends BaseWidget
{
    protected static ?string $heading = 'Peringkat Berat Setoran';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $sub = SampahTransactions::query()
            ->selectRaw('rekening_id, SUM(sampah_transactions.berat) as total_berat')
            ->whereNull('sampah_transactions.deleted_at')
            ->where('sampah_transactions.type', 'masuk')
            ->where('sampah_transactions.transactable_type', SetorSampah::class)
            ->join('sampah', 'sampah_transactions.sampah_id', '=', 'sampah.id')
            ->where('sampah.simpan_berat', true)
            ->groupBy('rekening_id');

        return Rekening::query()
            ->select('rekening.*')
            ->selectRaw('COALESCE(t.total_berat, 0) as total_berat')
            ->leftJoinSub($sub, 't', 't.rekening_id', '=', 'rekening.id')
            ->where('rekening.no_rekening', '!=', '00000000')
            ->whereRaw('COALESCE(t.total_berat, 0) > 0')
            ->orderByDesc('total_berat');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('rank')
                ->label('#')
                ->rowIndex(),
            Tables\Columns\TextColumn::make('nama')
                ->label('Rekening')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('no_rekening')
                ->label('No Rekening')
                ->sortable(),
            Tables\Columns\TextColumn::make('total_berat')
                ->label('Total Berat (Kg)')
                ->numeric(decimalPlaces: 4, decimalSeparator: ',', thousandsSeparator: '.')
                ->sortable(),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }
}
