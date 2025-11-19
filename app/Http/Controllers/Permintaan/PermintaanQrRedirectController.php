<?php

namespace App\Http\Controllers\Permintaan;

use App\Filament\Resources\PermintaanSetorSampahResource;
use App\Filament\Resources\PermintaanTarikSaldoResource;
use App\Http\Controllers\Controller;
use App\Models\PermintaanSetorSampah;
use App\Models\PermintaanTarikSaldo;
use Illuminate\Http\Request;

class PermintaanQrRedirectController extends Controller
{
    public function __invoke(Request $request, string $type, string $record)
    {
        $modelClass = match ($type) {
            'tarik-saldo' => PermintaanTarikSaldo::class,
            'setor-sampah' => PermintaanSetorSampah::class,
            default => abort(404),
        };

        /** @var \App\Models\PermintaanTarikSaldo|\App\Models\PermintaanSetorSampah $permintaan */
        $permintaan = $modelClass::findOrFail($record);

        if (! $permintaan->verifyPermintaanToken($request->query('token'))) {
            abort(403, 'Token tidak valid atau sudah kedaluwarsa.');
        }

        $redirectUrl = $permintaan instanceof PermintaanTarikSaldo
            ? PermintaanTarikSaldoResource::getUrl('edit', ['record' => $permintaan])
            : PermintaanSetorSampahResource::getUrl('edit', ['record' => $permintaan]);

        return redirect()->to($redirectUrl);
    }
}
