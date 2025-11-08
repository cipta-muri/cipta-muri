<?php

namespace App\Filament\Widgets;

use App\Models\Rekening;
use App\Models\SetorSampah;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopJumlahSetoranWidget extends BaseWidget
{
    protected static ?string $heading = 'Peringkat Jumlah Setoran';

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $sub = SetorSampah::query()
            ->selectRaw('rekening_id, COUNT(*) as total_setor')
            ->whereNull('deleted_at')
            ->groupBy('rekening_id');

        return Rekening::query()
            ->select('rekening.*')
            ->selectRaw('COALESCE(t.total_setor, 0) as total_setor')
            ->leftJoinSub($sub, 't', 't.rekening_id', '=', 'rekening.id')
            ->where('rekening.no_rekening', '!=', '00000000')
            ->whereRaw('COALESCE(t.total_setor, 0) > 0')
            ->orderByDesc('total_setor');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('nama')
                ->label('Rekening')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('no_rekening')
                ->label('No Rekening')
                ->sortable(),
            Tables\Columns\TextColumn::make('total_setor')
                ->label('Jumlah Setoran')
                ->sortable(),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }
}

