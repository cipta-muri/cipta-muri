<?php

namespace App\Filament\Resources;

use App\Enums\PermintaanStatus;
use App\Filament\Resources\PermintaanSetorSampahResource\Pages;
use App\Models\PermintaanSetorSampah;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Hexters\HexaLite\HasHexaLite;

class PermintaanSetorSampahResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = PermintaanSetorSampah::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Operasional Bank Sampah';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Permintaan Setor Sampah';

    protected static ?int $navigationSort = 4;

    public $hexaSort = 7;

    public function defineGates()
    {
        return [
            'permintaan_setor_sampah.index' => __('Lihat Permintaan Setor Sampah'),
            'permintaan_setor_sampah.update' => __('Konfirmasi / Tolak Permintaan Setor Sampah'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('permintaan_setor_sampah.index');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->disabled()
            ->schema([
                Section::make('Informasi Nasabah')
                    ->schema([
                        Placeholder::make('rekening')
                            ->label('Nasabah')
                            ->content(fn (?PermintaanSetorSampah $record) => $record?->rekening ? "{$record->rekening->nama} ({$record->rekening->no_rekening})" : '-'),
                        Placeholder::make('jenis_setoran')
                            ->label('Jenis Setoran')
                            ->content(fn (?PermintaanSetorSampah $record) => ucfirst($record->jenis_setoran ?? '-')),
                        Placeholder::make('tanggal_setor')
                            ->label('Tanggal Setor')
                            ->content(fn (?PermintaanSetorSampah $record) => optional($record?->tanggal_setor)->translatedFormat('d M Y') ?? '-'),
                    ])->columns(3),
                Section::make('Ringkasan Perhitungan')
                    ->schema([
                        Placeholder::make('total_berat')
                            ->label('Total Berat')
                            ->content(fn (?PermintaanSetorSampah $record) => $record ? number_format($record->total_berat, 2) . ' Kg' : '-'),
                        Placeholder::make('total_saldo_dihasilkan')
                            ->label('Total Saldo')
                            ->content(fn (?PermintaanSetorSampah $record) => $record ? 'Rp ' . number_format($record->total_saldo_dihasilkan, 0, ',', '.') : '-'),
                        Placeholder::make('total_poin_dihasilkan')
                            ->label('Total Poin')
                            ->content(fn (?PermintaanSetorSampah $record) => $record ? number_format($record->total_poin_dihasilkan) : '-'),
                        Placeholder::make('status')
                            ->label('Status')
                            ->content(fn (?PermintaanSetorSampah $record) => $record?->status?->label() ?? '-'),
                        Placeholder::make('requested_at')
                            ->label('Diajukan Pada')
                            ->content(fn (?PermintaanSetorSampah $record) => optional($record?->requested_at)->translatedFormat('d M Y H:i') ?? '-'),
                        Placeholder::make('source')
                            ->label('Sumber')
                            ->content(fn (?PermintaanSetorSampah $record) => $record?->source ?? '-'),
                    ])->columns(3),
                Section::make('Detail Item Sampah')
                    ->schema([
                        ViewField::make('detail_items')
                            ->view('filament.components.permintaan-setor-items')
                            ->viewData(fn (?PermintaanSetorSampah $record) => [
                                'items' => $record?->detailItemsResolved() ?? collect(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('rekening.nama')
                    ->label('Nasabah')
                    ->description(fn (PermintaanSetorSampah $record) => $record->rekening?->no_rekening)
                    ->searchable(['rekening.nama', 'rekening.no_rekening'])
                    ->sortable(),
                TextColumn::make('total_berat')
                    ->label('Total Berat (Kg)')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('total_saldo_dihasilkan')
                    ->label('Total Saldo')
                    ->money('IDR')
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => ($state instanceof PermintaanStatus ? $state : PermintaanStatus::tryFrom($state))?->label() ?? '-')
                    ->color(function ($state) {
                        $enum = $state instanceof PermintaanStatus ? $state : PermintaanStatus::tryFrom($state);

                        return match ($enum) {
                            PermintaanStatus::Draft => 'gray',
                            PermintaanStatus::MenungguKonfirmasi => 'warning',
                            PermintaanStatus::Disetujui => 'success',
                            PermintaanStatus::Ditolak => 'danger',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('source')
                    ->label('Kanal')
                    ->badge()
                    ->colors(['primary']),
                TextColumn::make('requested_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PermintaanStatus::options()),
                Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('requested_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($query, $date) => $query->whereDate('requested_at', '<=', $date));
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('confirm')
                        ->label('Konfirmasi')
                        ->color('success')
                        ->icon('heroicon-o-check')
                        ->visible(fn (PermintaanSetorSampah $record) => $record->isWaitingConfirmation() && hexa()->can('permintaan_setor_sampah.update'))
                        ->form([
                            Forms\Components\Textarea::make('note')
                                ->label('Catatan Admin')
                                ->rows(3),
                        ])
                        ->action(function (PermintaanSetorSampah $record, array $data) {
                            try {
                                $record->confirm(auth()->user(), 'table', $data['note'] ?? null);

                                Notification::make()
                                    ->title('Setoran dikonfirmasi')
                                    ->success()
                                    ->send();
                            } catch (ValidationException $exception) {
                                Notification::make()->title('Gagal memproses')->body($exception->getMessage())->danger()->send();
                            } catch (\Throwable $exception) {
                                report($exception);
                                Notification::make()->title('Terjadi kesalahan')->danger()->send();
                            }
                        }),
                    Action::make('reject')
                        ->label('Tolak')
                        ->color('danger')
                        ->icon('heroicon-o-x-mark')
                        ->visible(fn (PermintaanSetorSampah $record) => $record->isWaitingConfirmation() && hexa()->can('permintaan_setor_sampah.update'))
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (PermintaanSetorSampah $record, array $data) {
                            try {
                                $record->reject(auth()->user(), $data['reason'], 'table');

                                Notification::make()->title('Permintaan ditolak')->success()->send();
                            } catch (ValidationException $exception) {
                                Notification::make()->title('Gagal memproses')->body($exception->getMessage())->danger()->send();
                            } catch (\Throwable $exception) {
                                report($exception);
                                Notification::make()->title('Terjadi kesalahan')->danger()->send();
                            }
                        }),
                    Action::make('refresh-token')
                        ->label('Refresh Token')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn () => hexa()->can('permintaan_setor_sampah.update'))
                        ->action(function (PermintaanSetorSampah $record) {
                            $record->regeneratePermintaanToken();
                            Notification::make()->title('Token diperbarui')->success()->send();
                        }),
                ]),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->label('Detail'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermintaanSetorSampahs::route('/'),
            'edit' => Pages\EditPermintaanSetorSampah::route('/{record}'),
        ];
    }
}
