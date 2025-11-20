<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rekening;
use App\Models\SampahTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SampahStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Rekening $rekening */
        $rekening = $request->user();

        $data = SampahTransactions::query()
            ->select([
                'sampah.id as sampah_id',
                'sampah.jenis_sampah',
                'sampah.kode_sampah',
                DB::raw('COALESCE(SUM(CASE WHEN sampah_transactions.type = "keluar" THEN -berat ELSE berat END), 0) as total_berat'),
            ])
            ->join('sampah', 'sampah.id', '=', 'sampah_transactions.sampah_id')
            ->where('sampah_transactions.rekening_id', $rekening->id)
            ->whereNull('sampah_transactions.deleted_at')
            ->groupBy('sampah.id', 'sampah.jenis_sampah', 'sampah.kode_sampah')
            ->orderBy('sampah.jenis_sampah')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
