<?php

namespace App\Filament\Resources\SampahResource\Widgets;

use App\Models\Sampah;
use App\Models\SampahTransactions;
use App\Models\SetorSampah;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SampahRecordSetoranHarianTable extends BaseWidget
{
    protected static ?string $heading = 'Rekap Penyetoran Harian (Jenis Ini)';

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

    public ?Sampah $record = null;

    public function table(Table $table): Table
    {
        $sampah = $this->record;

        $query = SampahTransactions::query()
            ->selectRaw('setor_sampah.tanggal, SUM(sampah_transactions.berat) as total_berat')
            ->join('setor_sampah', function ($join) {
                $join->on('sampah_transactions.transactable_id', '=', 'setor_sampah.id')
                    ->where('sampah_transactions.transactable_type', SetorSampah::class);
            })
            ->whereNull('sampah_transactions.deleted_at')
            ->whereNull('setor_sampah.deleted_at')
            ->where('sampah_transactions.type', 'masuk')
            ->when(
                $sampah,
                fn ($query) => $query->where('sampah_transactions.sampah_id', $sampah->id),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->groupBy('setor_sampah.tanggal')
            ->orderByDesc('setor_sampah.tanggal');

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
        return 'record-harian-'.$record->getAttribute('tanggal');
    }
}
