<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermintaanController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\SampahStatsController;
use App\Http\Controllers\Api\SampahController;
use App\Http\Controllers\Api\SampahTransactionController;
use App\Models\News;
use App\Models\Rekening;
use App\Models\SaldoTransaction;
use App\Models\Sampah;
use App\Models\SetorSampah;
use App\Models\WithdrawRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Semua endpoint untuk aplikasi Android kamu dibuat di sini.
| File ini otomatis diprefix dengan /api, jadi route /nasabah/login
| akan diakses melalui domain.com/api/nasabah/login
|
*/

// LOGIN NASABAH
Route::post('/nasabah/login', [AuthController::class, 'login']);

// ROUTE TERPROTEKSI
Route::middleware('auth:rekening')->group(function () {
    Route::get('/nasabah/profile', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ]);
    });

    Route::post('/nasabah/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    });

    // Rekening - profil & ringkasan saldo
    Route::get('/rekening', function (Request $request) {
        /** @var Rekening $rekening */
        $rekening = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'rekening' => $rekening,
                'balance' => $rekening->balance,
                'formatted_balance' => $rekening->formatted_balance,
                'points_balance' => $rekening->points_balance,
            ],
        ]);
    });

    // Saldo transactions - daftar transaksi saldo milik rekening login
    Route::get('/rekening/saldo-transactions', function (Request $request) {
        /** @var Rekening $rekening */
        $rekening = $request->user();
        $type = $request->query('type'); // optional: credit / debit

        $query = SaldoTransaction::where('rekening_id', $rekening->id)
            ->latest();
        if (in_array($type, ['credit', 'debit'])) {
            $query->where('type', $type);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    });

    // Setor sampah - daftar setoran milik rekening login
    Route::get('/setor-sampah', function (Request $request) {
        /** @var Rekening $rekening */
        $rekening = $request->user();
        $data = SetorSampah::with(['details.sampah'])
            ->where('rekening_id', $rekening->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    });

    Route::get('/setor-sampah/statistik-jenis', [SampahStatsController::class, 'index']);
    Route::get('/sampah-transactions', [SampahTransactionController::class, 'index']);

    Route::post('/permintaan/setor-sampah', [PermintaanController::class, 'createSetorSampah']);
    Route::post('/permintaan/tarik-saldo', [PermintaanController::class, 'createTarikSaldo']);

    // Tarik saldo - daftar permintaan penarikan saldo milik rekening login
    Route::get('/tarik-saldo', function (Request $request) {
        /** @var Rekening $rekening */
        $rekening = $request->user();
        $data = WithdrawRequest::where('rekening_id', $rekening->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    });
});

// Berita (publik)
Route::get('/berita', function (Request $request) {
    $query = News::published()->latest();

    if ($category = $request->query('category')) {
        $query->byCategory($category);
    }

    if ($q = $request->query('q')) {
        $query->where(function ($sub) use ($q) {
            $sub->where('title', 'like', "%$q%")
                ->orWhere('content', 'like', "%$q%");
        });
    }

    $data = $query->get([
        'id', 'title', 'slug', 'excerpt', 'featured_image', 'published_at', 'category', 'views_count',
    ]);

    // Map to include featured_image_url accessor
    $data->transform(function ($item) {
        $item->featured_image_url = $item->featured_image_url; // ensure appended

        return $item;
    });

    return response()->json([
        'success' => true,
        'data' => $data,
    ]);
});

Route::get('/berita/{slug}', function (string $slug) {
    $news = News::where('slug', $slug)->firstOrFail();
    $news->incrementViews();

    return response()->json([
        'success' => true,
        'data' => $news,
    ]);
});

// Sampah (publik)
Route::get('/sampah', [SampahController::class, 'index']);
Route::get('/sampah/{sampah}', [SampahController::class, 'show']);

Route::get('/tes-api', function () {
    return response()->json(['message' => 'API aktif ✅.']);
});

// Ranking (publik) — hasil gabungan Top Berat & Top Jumlah
Route::get('/ranking', [RankingController::class, 'index']);





