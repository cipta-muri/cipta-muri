<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SampahResource\Pages;
use App\Models\Sampah;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Resources\Forms\Form;
use Filament\Support\RawJs;
use App\Filament\Resources\SampahResource\RelationManagers;
use Filament\Resources\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Hexters\HexaLite\HasHexaLite;

class SampahResource extends Resource
{
    use HasHexaLite;
    protected static ?string $model = Sampah::class;

    protected static ?string $navigationIcon = 'heroicon-o-trash';

    protected static ?string $label = 'Pendataan Sampah';

    protected static ?string $navigationGroup = 'Operasional Bank Sampah';

    protected static ?int $navigationSort = 3;

    public $hexaSort = 6;

    public function defineGates()
    {
        return [
            'sampah.index' => __('Lihat Pendataan Sampah'),
            'sampah.create' => __('Buat Pendataan Sampah Baru'),
            'sampah.update' => __('Ubah Pendataan Sampah'),
            'sampah.delete' => __('Hapus Pendataan Sampah'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('sampah.index');
    }


    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('jenis_sampah')
                    ->label('Nama Jenis Sampah')
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['dusk' => 'sampah-form-jenis_sampah']),
                Forms\Components\TextInput::make('saldo_per_kg')
                    ->label('Saldo per-Kg')
                    ->prefix('Rp')
                    ->numeric()
                    ->required()
                    ->extraInputAttributes(['dusk' => 'sampah-form-saldo_per_kg']),
                Forms\Components\Toggle::make('simpan_berat')
                    ->label('Simpan Berat Sampah')
                    ->default(true)
                    ->helperText('Nonaktifkan jika sampah ini tidak ingin disimpan beratnya saat setor sampah.')
                    ->extraAttributes(['dusk' => 'sampah-form-simpan_berat']),
                // Forms\Components\TextInput::make('poin_per_kg')
                //     ->label('Poin per-gram')
                //     ->prefix('Poin')
                //     ->mask(RawJs::make('$money($input)'))
                //     ->stripCharacters(['.', ','])
                //     ->numeric()
                //     ->required(),
            ])->columns(1);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('jenis_sampah')->label('Nama Jenis Sampah')->sortable()->searchable()->extraAttributes(['dusk' => 'sampah-table-jenis_sampah']),
                TextColumn::make('saldo_per_kg')->label('Saldo per-Kg')->sortable()->money('IDR')->extraAttributes(['dusk' => 'sampah-table-saldo_per_kg']),
                TextColumn::make('total_berat_terkumpul')
                    ->label('Total Terkumpul (Kg)')
                    ->numeric(
                        decimalPlaces: 4,
                        decimalSeparator: ',',
                        thousandsSeparator: '.'
                    )->sortable()->extraAttributes(['dusk' => 'sampah-tale-total_berat_terkumpul']),
                IconColumn::make('simpan_berat')
                    ->label('Simpan Berat?')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->extraAttributes(['dusk' => 'sampah-table-simpan_berat']),
                // TextColumn::make('poin_per_kg')->label('Poin per-gram')->sortable(),
                TextColumn::make('user.name')->label('Pembuat Data')->sortable()->searchable()->extraAttributes(['dusk' => 'sampah-table-user.name']),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn() => hexa()->can('sampah.index')),
                Tables\Actions\EditAction::make()
                    ->visible(fn() => hexa()->can('sampah.update')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => hexa()->can('sampah.delete'))
                    ->extraAttributes(['dusk' => 'sampah-delete-action']),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn() => hexa()->can('sampah.delete')),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SampahTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSampahs::route('/'),
            'create' => Pages\CreateSampah::route('/create'),
            'view' => Pages\ViewSampah::route('/{record}'),
            'edit' => Pages\EditSampah::route('/{record}/edit'),
        ];
    }
}
