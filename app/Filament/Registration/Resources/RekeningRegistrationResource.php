<?php

namespace App\Filament\Registration\Resources;

use App\Filament\Registration\Resources\RekeningRegistrationResource\Pages;
use App\Models\Rekening;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\Component;

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
        $baseForm = \App\Filament\Resources\RekeningResource::form($form);

        $schema = collect($baseForm->getComponents());

        $filtered = $schema
            ->reject(function (Component $component) {
                if ($component instanceof Section) {
                    $heading = $component->getHeading();

                    return in_array($heading, ['Saldo Awal', 'Informasi Tabungan Emas Pegadaian'], true);
                }

                return false;
            })
            ->values()
            ->all();

        return $form->schema($filtered);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\CreateRekeningRegistration::route('/'),
        ];
    }
}
