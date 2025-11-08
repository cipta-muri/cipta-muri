<?php

namespace App\Filament\Widgets;

use App\Models\Rekening;
use App\Models\SampahTransactions;
use App\Models\SetorSampah;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopBeratSetoranWidget extends BaseWidget
{
    protected static ?string $heading = 'Peringkat Berat Setoran';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Base: all-time aggregation
        $baseSub = SampahTransactions::query()
            ->selectRaw('rekening_id, SUM(sampah_transactions.berat) as total_berat')
            ->whereNull('sampah_transactions.deleted_at')
            ->where('sampah_transactions.type', 'masuk')
            ->where('sampah_transactions.transactable_type', SetorSampah::class)
            ->join('sampah', 'sampah_transactions.sampah_id', '=', 'sampah.id')
            ->where('sampah.simpan_berat', true)
            ->groupBy('rekening_id');

        $baseQuery = Rekening::query()
            ->select('rekening.*')
            ->selectRaw('COALESCE(t.total_berat, 0) as total_berat')
            ->leftJoinSub($baseSub, 't', 't.rekening_id', '=', 'rekening.id')
            ->where('rekening.no_rekening', '!=', '00000000')
            ->orderByDesc('total_berat');

        return $table
            ->query($baseQuery)
            ->columns([
                Tables\Columns\TextColumn::make('rank')->label('#')->rowIndex(),
                Tables\Columns\TextColumn::make('nama')->label('Rekening')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('no_rekening')->label('No Rekening')->sortable(),
                Tables\Columns\TextColumn::make('total_berat')
                    ->label('Total Berat (Kg)')
                    ->numeric(decimalPlaces: 4, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable(),
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
                            return 'Tanggal: ' . ($from ?: '...') . ' – ' . ($until ?: '...');
                        }
                        return null;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;
                        if (!$from && !$until) {
                            return $query;
                        }

                        $filteredSub = SampahTransactions::query()
                            ->selectRaw('rekening_id, SUM(sampah_transactions.berat) as total_berat')
                            ->whereNull('sampah_transactions.deleted_at')
                            ->where('sampah_transactions.type', 'masuk')
                            ->where('sampah_transactions.transactable_type', SetorSampah::class)
                            ->join('sampah', 'sampah_transactions.sampah_id', '=', 'sampah.id')
                            ->where('sampah.simpan_berat', true)
                            ->join('setor_sampah', function ($join) {
                                $join->on('sampah_transactions.transactable_id', '=', 'setor_sampah.id')
                                    ->where('sampah_transactions.transactable_type', SetorSampah::class);
                            })
                            ->when($from, fn ($q) => $q->whereDate('setor_sampah.tanggal', '>=', $from))
                            ->when($until, fn ($q) => $q->whereDate('setor_sampah.tanggal', '<=', $until))
                            ->groupBy('rekening_id');

                        return $query
                            ->leftJoinSub($filteredSub, 'tf', 'tf.rekening_id', '=', 'rekening.id')
                            ->select('rekening.*')
                            ->selectRaw('COALESCE(tf.total_berat, 0) as total_berat')
                            ->orderByDesc('total_berat');
                    }),
            ])
            ->paginated(true);
    }
}
