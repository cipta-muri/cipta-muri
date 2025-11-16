<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PermintaanSetorSampah;
use App\Models\PermintaanTarikSaldo;
use App\Models\Rekening;
use App\Models\Sampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PermintaanController extends Controller
{
    public function createSetorSampah(Request $request)
    {
        /** @var Rekening $rekening */
        $rekening = $request->user();

        $validated = $request->validate([
            'tanggal_setor' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sampah_id' => ['required', 'exists:sampah,id'],
            'items.*.berat' => ['required', 'numeric', 'min:0.01'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $items = collect($validated['items'])->map(function (array $item) {
            /** @var Sampah $sampah */
            $sampah = Sampah::findOrFail($item['sampah_id']);

            return [
                'sampah_id' => $sampah->id,
                'berat' => (float) $item['berat'],
                'description' => $item['description'] ?? 'Setoran Sampah',
                'type' => 'masuk',
                'harga_saldo' => $sampah->saldo_per_kg,
                'poin_per_kg' => $sampah->poin_per_kg ?? 0,
            ];
        });

        $totalBerat = $items->sum('berat');
        $totalSaldo = $items->sum(fn ($item) => $item['berat'] * ($item['harga_saldo'] ?? 0));
        $totalPoin = (int) round($items->sum(fn ($item) => $item['berat'] * ($item['poin_per_kg'] ?? 0)));

        $permintaan = DB::transaction(function () use ($rekening, $validated, $items, $totalBerat, $totalSaldo, $totalPoin, $request) {
            return PermintaanSetorSampah::create([
                'rekening_id' => $rekening->id,
                'requested_by_rekening_id' => $rekening->id,
                'requested_by_user_id' => $rekening->user_id,
                'jenis_setoran' => 'rekening',
                'tanggal_setor' => $validated['tanggal_setor'] ?? now()->toDateString(),
                'total_berat' => $totalBerat,
                'total_saldo_dihasilkan' => $totalSaldo,
                'total_poin_dihasilkan' => $totalPoin,
                'calculation_performed' => true,
                'detail_items' => $items->toArray(),
                'source' => 'mobile_banking',
                'meta' => [
                    'device_id' => $request->header('X-Device-Id'),
                    'app_version' => $request->header('X-App-Version'),
                ],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Permintaan setor sampah berhasil dikirim.',
            'data' => [
                'permintaan' => $permintaan,
                'qr_url' => $permintaan->qrSignedUrl($permintaan->qrRouteType()),
            ],
        ], 201);
    }

    public function createTarikSaldo(Request $request)
    {
        /** @var Rekening $rekening */
        $rekening = $request->user();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
            'jenis' => ['nullable', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = (float) $validated['amount'];

        if (! $rekening->hasSufficientBalance($amount)) {
            throw ValidationException::withMessages([
                'amount' => 'Saldo tidak mencukupi untuk membuat permintaan penarikan.',
            ]);
        }

        $permintaan = PermintaanTarikSaldo::create([
            'rekening_id' => $rekening->id,
            'requested_by_rekening_id' => $rekening->id,
            'requested_by_user_id' => $rekening->user_id,
            'amount' => $amount,
            'jenis' => $validated['jenis'] ?? 'tunai',
            'catatan' => $validated['catatan'] ?? null,
            'source' => 'mobile_banking',
            'meta' => [
                'device_id' => $request->header('X-Device-Id'),
                'app_version' => $request->header('X-App-Version'),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan tarik saldo berhasil dikirim.',
            'data' => [
                'permintaan' => $permintaan,
                'qr_url' => $permintaan->qrSignedUrl($permintaan->qrRouteType()),
            ],
        ], 201);
    }
}
