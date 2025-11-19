<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rekening;
use App\Models\SampahTransactions;
use App\Models\SetorSampah;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    public function index(Request $request)
    {
        $limit = (int) ($request->query('limit', 10));
        if ($limit < 1) {
            $limit = 10;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $includeDonasi = filter_var($request->query('include_donasi', 'false'), FILTER_VALIDATE_BOOLEAN);

        // Top berat setoran (Kg) — hanya yang menyimpan berat
        $trxBerat = SampahTransactions::query()
            ->selectRaw('sampah_transactions.rekening_id, SUM(sampah_transactions.berat) as total_berat')
            ->whereNull('sampah_transactions.deleted_at')
            ->where('sampah_transactions.type', 'masuk')
            ->where('sampah_transactions.transactable_type', SetorSampah::class)
            ->join('sampah', 'sampah_transactions.sampah_id', '=', 'sampah.id')
            ->where('sampah.simpan_berat', true);

        if ($startDate || $endDate) {
            $trxBerat->join('setor_sampah', function ($join) {
                $join->on('sampah_transactions.transactable_id', '=', 'setor_sampah.id')
                    ->where('sampah_transactions.transactable_type', SetorSampah::class);
            });
            if ($startDate) {
                $trxBerat->whereDate('setor_sampah.tanggal', '>=', $startDate);
            }
            if ($endDate) {
                $trxBerat->whereDate('setor_sampah.tanggal', '<=', $endDate);
            }
        }

        $trxBerat->groupBy('sampah_transactions.rekening_id');

        $topBerat = Rekening::query()
            ->select('rekening.id', 'rekening.nama', 'rekening.no_rekening')
            ->selectRaw('COALESCE(t.total_berat, 0) as total_berat')
            ->leftJoinSub($trxBerat, 't', 't.rekening_id', '=', 'rekening.id')
            ->when(! $includeDonasi, fn ($q) => $q->where('rekening.no_rekening', '!=', '00000000'))
            ->whereRaw('COALESCE(t.total_berat, 0) > 0')
            ->orderByDesc('total_berat')
            ->limit($limit)
            ->get();

        // Top jumlah setoran — hitung dari tabel setor_sampah
        $subSetor = SetorSampah::query()
            ->selectRaw('rekening_id, COUNT(*) as total_setor')
            ->whereNull('deleted_at');
        if ($startDate) {
            $subSetor->whereDate('tanggal', '>=', $startDate);
        }
        if ($endDate) {
            $subSetor->whereDate('tanggal', '<=', $endDate);
        }
        $subSetor->groupBy('rekening_id');

        $topSetor = Rekening::query()
            ->select('rekening.id', 'rekening.nama', 'rekening.no_rekening')
            ->selectRaw('COALESCE(t.total_setor, 0) as total_setor')
            ->leftJoinSub($subSetor, 't', 't.rekening_id', '=', 'rekening.id')
            ->when(! $includeDonasi, fn ($q) => $q->where('rekening.no_rekening', '!=', '00000000'))
            ->whereRaw('COALESCE(t.total_setor, 0) > 0')
            ->orderByDesc('total_setor')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'top_berat' => $topBerat,
                'top_setor' => $topSetor,
            ],
        ]);
    }
}
