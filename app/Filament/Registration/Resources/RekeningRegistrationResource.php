<?php

namespace App\Filament\Registration\Resources;

use App\Filament\Registration\Resources\RekeningRegistrationResource\Pages;
use App\Models\Rekening;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\Component;
use Illuminate\Support\HtmlString;

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

        $requiredFields = ['no_kk', 'nik', 'tanggal_lahir', 'pendidikan'];

        foreach ($filtered as $component) {
            static::applyRequiredFields($component, $requiredFields);
        }

        $filtered[] = Section::make('Peringatan Penyerahan Berkas untuk Pembukaan Rekening')
            ->schema([
                Placeholder::make('rekening_completion_info')
                    ->label('Anda sudah bisa menggunakan akun setelah mengisi formulir dan klik "Buat", tetapi untuk kelengkapan berkas silahkan ikuti langkah berikut:')
                    ->content(new HtmlString(
                        <<<'HTML'
                        <ul>
                            <li>- Harap klik tautan berikut untuk konsultasi prosedur penyerahan berkas: <a href="https://wa.link/a7rr4b" target="_blank" rel="noopener noreferrer" style="text-decoration: underline; color: #001affff;">https://wa.link/a7rr4b</a> (Ibu Roro)</li>
                            <li>- Jika tautan di atas eror, dapat langsung menghubungi nomor berikut: <a href="tel:081513214364" style="text-decoration: underline;">0815-1321-4364</a> (Ibu Roro)</li>
                            <li>- Atau Anda bisa langsung mengunjungi kami pada alamat berikut: <a href="https://maps.app.goo.gl/WewuGajt3mDcHXBY8?g_st=ipc" target="_blank" rel="noopener noreferrer" style="text-decoration: underline; color: #001affff;">https://maps.app.goo.gl/WewuGajt3mDcHXBY8?g_st=ipc</a> (Limbah Pustaka)</li>
                        </ul>
                        HTML
                    )),
            ]);

        return $form->schema($filtered);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\CreateRekeningRegistration::route('/'),
        ];
    }

    protected static function applyRequiredFields(Component $component, array $requiredFields): void
    {
        if (method_exists($component, 'getName') && in_array($component->getName(), $requiredFields, true) && method_exists($component, 'required')) {
            $component->required();
        }

        if (method_exists($component, 'getChildComponents')) {
            foreach ($component->getChildComponents() as $child) {
                if ($child instanceof Component) {
                    static::applyRequiredFields($child, $requiredFields);
                }
            }
        }
    }
}
