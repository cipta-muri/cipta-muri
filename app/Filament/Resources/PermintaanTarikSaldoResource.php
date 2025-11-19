<?php

namespace App\Filament\Resources;

use App\Enums\PermintaanStatus;
use App\Filament\Resources\PermintaanTarikSaldoResource\Pages;
use App\Models\PermintaanTarikSaldo;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Validation\ValidationException;

class PermintaanTarikSaldoResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = PermintaanTarikSaldo::class;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Operasional Bank Sampah';

    protected static ?string $navigationLabel = 'Permintaan Tarik Saldo';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 3;

    public $hexaSort = 6;

    public function defineGates()
    {
        return [
            'permintaan_tarik_saldo.index' => __('Lihat Permintaan Tarik Saldo'),
            'permintaan_tarik_saldo.update' => __('Konfirmasi / Tolak Permintaan Tarik Saldo'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('permintaan_tarik_saldo.index');
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
                            ->content(fn (?PermintaanTarikSaldo $record) => $record && $record->rekening ? "{$record->rekening->nama} ({$record->rekening->no_rekening})" : '-'),
                        Placeholder::make('saldo')
                            ->label('Saldo Saat Ini')
                            ->content(fn (?PermintaanTarikSaldo $record) => $record && $record->rekening ? 'Rp '.number_format($record->rekening->balance, 0, ',', '.') : '-'),
                    ])->columns(2),
                Section::make('Detail Permintaan')
                    ->schema([
                        Placeholder::make('amount')
                            ->label('Nominal')
                            ->content(fn (?PermintaanTarikSaldo $record) => $record ? 'Rp '.number_format($record->amount, 0, ',', '.') : '-'),
                        Placeholder::make('jenis')
                            ->label('Metode')
                            ->content(fn (?PermintaanTarikSaldo $record) => ucfirst($record->jenis ?? '-')),
                        Placeholder::make('catatan')
                            ->label('Catatan Nasabah')
                            ->content(fn (?PermintaanTarikSaldo $record) => $record?->catatan ?? '-'),
                        Placeholder::make('status')
                            ->label('Status')
                            ->content(fn (?PermintaanTarikSaldo $record) => $record?->status?->label() ?? '-'),
                        Placeholder::make('requested_at')
                            ->label('Diajukan Pada')
                            ->content(fn (?PermintaanTarikSaldo $record) => optional($record?->requested_at)->translatedFormat('d M Y H:i') ?? '-'),
                        Placeholder::make('source')
                            ->label('Sumber')
                            ->content(fn (?PermintaanTarikSaldo $record) => $record?->source ?? '-'),
                        Placeholder::make('qr')
                            ->label('Token QR')
                            ->content(fn (?PermintaanTarikSaldo $record) => $record?->qr_token ?? '-'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(PermintaanTarikSaldo::query())
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('rekening.nama')
                    ->label('Nasabah')
                    ->description(fn (PermintaanTarikSaldo $record) => $record->rekening?->no_rekening)
                    ->searchable(['rekening.nama', 'rekening.no_rekening'])
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Nominal')
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
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('qr_token')
                    ->label('Token QR')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PermintaanStatus::options()),
                Tables\Filters\Filter::make('tanggal')
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
                        ->requiresConfirmation()
                        ->visible(fn (PermintaanTarikSaldo $record) => $record->isWaitingConfirmation() && hexa()->can('permintaan_tarik_saldo.update'))
                        ->form([
                            Textarea::make('note')
                                ->label('Catatan Admin')
                                ->rows(3),
                        ])
                        ->action(function (PermintaanTarikSaldo $record, array $data) {
                            try {
                                $withdraw = $record->confirm(
                                    auth()->user(),
                                    'table',
                                    $data['note'] ?? null,
                                    null
                                );

                                Notification::make()
                                    ->title('Permintaan disetujui')
                                    ->body('Data telah dipindahkan ke daftar penarikan saldo.')
                                    ->success()
                                    ->send();
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title('Gagal memproses')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            } catch (\Throwable $exception) {
                                report($exception);
                                Notification::make()
                                    ->title('Terjadi kesalahan')
                                    ->body('Tidak dapat memproses permintaan saat ini.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('reject')
                        ->label('Tolak')
                        ->color('danger')
                        ->icon('heroicon-o-x-mark')
                        ->visible(fn (PermintaanTarikSaldo $record) => $record->isWaitingConfirmation() && hexa()->can('permintaan_tarik_saldo.update'))
                        ->form([
                            Textarea::make('reason')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (PermintaanTarikSaldo $record, array $data) {
                            try {
                                $record->reject(auth()->user(), $data['reason'], 'table');

                                Notification::make()
                                    ->title('Permintaan ditolak')
                                    ->success()
                                    ->send();
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title('Gagal memproses')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            } catch (\Throwable $exception) {
                                report($exception);
                                Notification::make()
                                    ->title('Terjadi kesalahan')
                                    ->body('Tidak dapat memproses permintaan saat ini.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('regenerateToken')
                        ->label('Refresh Token')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn () => hexa()->can('permintaan_tarik_saldo.update'))
                        ->action(function (PermintaanTarikSaldo $record) {
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
            'index' => Pages\ListPermintaanTarikSaldos::route('/'),
            'edit' => Pages\EditPermintaanTarikSaldo::route('/{record}'),
        ];
    }
}
