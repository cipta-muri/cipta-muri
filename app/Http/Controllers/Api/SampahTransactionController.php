<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rekening;
use App\Models\SampahTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SampahTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Rekening $rekening */
        $rekening = $request->user();

        $type = $request->query('type');
        $sampahId = $request->query('sampah_id');
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min($limit, 100));

        $query = SampahTransactions::query()
            ->with([
                'sampah:id,jenis_sampah,kode_sampah,kategori,harga_per_kg',
            ])
            ->where('rekening_id', $rekening->id)
            ->latest();

        if (in_array($type, ['masuk', 'keluar'], true)) {
            $query->where('type', $type);
        }

        if ($sampahId) {
            $query->where('sampah_id', $sampahId);
        }

        $transactions = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'meta' => [
                'limit' => $limit,
                'type' => $type,
                'sampah_id' => $sampahId,
            ],
        ]);
    }
}
