<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Frontend\NewsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Sampah;
use App\Models\Rekening;
use App\Models\SetorSampah;
use App\Models\SampahKeluar;
use App\Models\WithdrawRequest;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Inertia\Inertia;

// Public landing page
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();
        
        // Tentukan role berdasarkan NIK atau kriteria lain
        // Misalnya admin jika NIK diawali dengan '1234'
        $isAdmin = str_starts_with($user->nik, '1234');
        
        if ($isAdmin) {
            return Inertia::render('admin-dashboard', [
                'user' => [
                    'name' => $user->name,
                    'nik' => $user->nik,
                    'role' => 'admin'
                ]
            ]);
        } else {
            return Inertia::render('nasabah-dashboard', [
                'user' => [
                    'name' => $user->name,
                    'nik' => $user->nik,
                    'role' => 'nasabah'
                ],
                'saldo' => 'Rp 110.000,00', // Data dummy, nanti ambil dari database
                'points' => '10 MP'
            ]);
        }
    })->name('dashboard');
});

require __DIR__.'/auth.php';

if (app()->environment(['local', 'testing'])) {
    Route::get('/_playwright/login', function (Request $request) {
        $email = $request->query('email');
        $nik = $request->query('nik');
        $user = null;
        if ($email) {
            $user = User::where('email', $email)->first();
        } elseif ($nik) {
            $user = User::where('nik', $nik)->first();
        }
        $user ??= User::first();
        if (! $user) {
            abort(404, 'User not found');
        }
        if (Filament::getPanel('admin')) {
            Filament::setCurrentPanel(Filament::getPanel('admin'));
            Filament::auth()->login($user);
        } else {
            Auth::login($user);
        }
        $request->session()->regenerate();
        return redirect('/admin');
    });

    Route::get('/_playwright/session', function (Request $request) {
        $email = $request->query('email');
        $nik = $request->query('nik');
        $user = null;
        if ($email) {
            $user = User::where('email', $email)->first();
        } elseif ($nik) {
            $user = User::where('nik', $nik)->first();
        }
        $user ??= User::first();
        if (! $user) {
            abort(404, 'User not found');
        }

        $guard = Filament::getPanel('admin')?->getAuthGuard() ?? 'web';
        Auth::guard($guard)->login($user);
        $session = $request->session();
        $session->put('pw_login', true);
        $session->save();

        return response()->json([
            'name' => $session->getName(),
            'id' => $session->getId(),
            'domain' => $request->getHost(),
            'path' => '/',
        ]);
    });

    Route::post('/_playwright/factory', function (Request $request) {
        $type = $request->input('type');
        switch ($type) {
            case 'rekening':
                $rek = Rekening::create([
                    'no_rekening' => str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
                    'nama' => $request->input('nama', 'PW Rek ' . Str::upper(Str::random(4))),
                    'dusun' => '001',
                    'rt' => '01',
                    'rw' => '01',
                    'gender' => 'Laki-laki',
                    'no_kk' => (string) random_int(10_0000_0000_0000_00, 99_9999_9999_9999_99),
                    'nik' => (string) random_int(10_0000_0000_0000_00, 99_9999_9999_9999_99),
                    'tanggal_lahir' => now(),
                    'pendidikan' => 'SMA',
                    'alamat' => '-',
                    'telepon' => '-',
                    'status_desa' => true,
                    'balance' => $request->input('balance', 0),
                    'points_balance' => 0,
                    'user_id' => User::first()?->id,
                ]);
                return $rek;
            case 'sampah':
                return Sampah::create([
                    'jenis_sampah' => $request->input('jenis_sampah', 'PW Sampah ' . Str::upper(Str::random(4))),
                    'saldo_per_kg' => $request->input('saldo_per_kg', 1000),
                    'poin_per_kg' => $request->input('poin_per_kg', 10),
                    'total_berat_terkumpul' => $request->input('total_berat_terkumpul', 0),
                    'user_id' => User::first()?->id,
                ]);
            case 'setor_sampah':
                $rekId = $request->input('rekening_id') ?: Rekening::first()?->id;
                $sampahId = $request->input('sampah_id') ?: Sampah::first()?->id;
                $tanggal = $request->input('tanggal') ?: Carbon::now();
                $setor = SetorSampah::create([
                    'rekening_id' => $rekId,
                    'tanggal' => $tanggal,
                    'jenis_setoran' => 'rekening',
                    'total_saldo_dihasilkan' => 0,
                    'total_poin_dihasilkan' => 0,
                    'berat' => 0,
                    'user_id' => User::first()?->id,
                    'calculation_performed' => true,
                ]);
                if ($sampahId) {
                    $setor->details()->create([
                        'sampah_id' => $sampahId,
                        'berat' => 1,
                        'description' => 'Playwright',
                        'type' => 'masuk',
                        'rekening_id' => $rekId,
                    ]);
                }
                return $setor->load('details');
            case 'sampah_keluar':
                $sampahId = $request->input('sampah_id') ?: Sampah::first()?->id;
                $keluar = SampahKeluar::create([
                    'jenis_keluar' => 'bakar',
                    'tanggal_keluar' => $request->input('tanggal_keluar', Carbon::now()),
                    'berat_keluar' => 1.5,
                    'total_saldo_dihasilkan' => 0,
                    'user_id' => User::first()?->id,
                ]);
                if ($sampahId) {
                    $keluar->details()->create([
                        'sampah_id' => $sampahId,
                        'berat' => 1.5,
                        'description' => 'Playwright',
                        'type' => 'keluar',
                        'rekening_id' => Rekening::first()?->id,
                    ]);
                }
                return $keluar->load('details');
            case 'withdraw_request':
                $rekId = $request->input('rekening_id') ?: Rekening::first()?->id;
                return WithdrawRequest::create([
                    'rekening_id' => $rekId,
                    'amount' => $request->input('amount', 15000),
                    'jenis' => 'cash',
                    'user_id' => User::first()?->id,
                ]);
            default:
                abort(400, 'Unknown factory type');
        }
    });
}
