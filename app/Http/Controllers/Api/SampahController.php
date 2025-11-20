<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sampah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SampahController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Sampah::query()->orderBy('jenis_sampah');

        if ($search = $request->query('q')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('jenis_sampah', 'like', "%$search%")
                    ->orWhere('kode_sampah', 'like', "%$search%")
                    ->orWhere('kategori', 'like', "%$search%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->get([
                'id',
                'jenis_sampah',
                'kode_sampah',
                'kategori',
                'harga_per_kg',
                'simpan_berat',
                'total_berat_terkumpul',
            ]),
        ]);
    }

    public function show(Sampah $sampah): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $sampah,
        ]);
    }
}
