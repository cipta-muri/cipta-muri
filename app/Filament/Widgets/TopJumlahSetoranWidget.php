<?php

namespace App\Filament\Widgets;

use App\Models\Rekening;
use App\Models\SetorSampah;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopJumlahSetoranWidget extends BaseWidget
{
    protected static ?string $heading = 'Peringkat Jumlah Setoran';

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $baseSub = SetorSampah::query()
            ->selectRaw('rekening_id, COUNT(*) as total_setor')
            ->whereNull('deleted_at')
            ->groupBy('rekening_id');

        $baseQuery = Rekening::query()
            ->select('rekening.*')
            ->selectRaw('COALESCE(t.total_setor, 0) as total_setor')
            ->leftJoinSub($baseSub, 't', 't.rekening_id', '=', 'rekening.id')
            ->where('rekening.no_rekening', '!=', '00000000')
            ->orderByDesc('total_setor');

        return $table
            ->query($baseQuery)
            ->columns([
                Tables\Columns\TextColumn::make('rank')->label('#')->rowIndex(),
                Tables\Columns\TextColumn::make('nama')->label('Rekening')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('no_rekening')->label('No Rekening')->sortable(),
                Tables\Columns\TextColumn::make('total_setor')->label('Jumlah Setoran')->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('tanggal')
                    ->label('Rentang Tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;
                        if ($from || $until) {
                            return 'Tanggal: '.($from ?: '...').' – '.($until ?: '...');
                        }

                        return null;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;
                        if (! $from && ! $until) {
                            return $query;
                        }

                        $filteredSub = SetorSampah::query()
                            ->selectRaw('rekening_id, COUNT(*) as total_setor')
                            ->whereNull('deleted_at')
                            ->when($from, fn ($q) => $q->whereDate('tanggal', '>=', $from))
                            ->when($until, fn ($q) => $q->whereDate('tanggal', '<=', $until))
                            ->groupBy('rekening_id');

                        return $query
                            ->leftJoinSub($filteredSub, 'sf', 'sf.rekening_id', '=', 'rekening.id')
                            ->select('rekening.*')
                            ->selectRaw('COALESCE(sf.total_setor, 0) as total_setor')
                            ->orderByDesc('total_setor');
                    }),
            ])
            ->paginated(true);
    }
}
