<?php

namespace App\Filament\Registration\Resources;

use App\Filament\Registration\Resources\RekeningRegistrationResource\Pages;
use App\Filament\Resources\RekeningResource as BaseRekeningResource;
use App\Models\Rekening;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class RekeningRegistrationResource extends Resource
{
    protected static ?string $model = Rekening::class;

    protected static ?string $slug = 'rekening';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $label = 'Registrasi Rekening';

    protected static ?string $pluralLabel = 'Registrasi Rekening';

    public static function canAccess(): bool
    {
        return true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function canEdit($model): bool
    {
        return false;
    }

    public static function canDelete($model): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return BaseRekeningResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'create' => Pages\CreateRekeningRegistration::route('/create'),
        ];
    }
}
