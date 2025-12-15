<?php

use App\Models\User;
use App\Models\Rekening;
use Filament\Facades\Filament;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Ensure migrations are up for the current env
Artisan::call('migrate', ['--force' => true]);

// Ensure Super Admin role with full gates
$adminRole = HexaRole::firstOrCreate(
    ['name' => 'Super Admin', 'guard' => 'web'],
    ['created_by_name' => 'Playwright', 'access' => []],
);

$panel = Filament::getPanel('admin');
$allGates = hexa()->panelGates($panel);
if (empty($allGates)) {
    $allGates = [
        'sampah.index', 'sampah.create', 'sampah.update', 'sampah.delete',
        'rekening.index', 'rekening.create', 'rekening.update', 'rekening.delete',
        'pemasukan.index', 'pemasukan.create', 'pemasukan.update', 'pemasukan.delete',
        'pengeluaran.index', 'pengeluaran.create', 'pengeluaran.update', 'pengeluaran.delete',
        'setor_sampah.index', 'setor_sampah.view', 'setor_sampah.create', 'setor_sampah.update', 'setor_sampah.delete',
        'sampah_keluar.index', 'sampah_keluar.create', 'sampah_keluar.update', 'sampah_keluar.delete',
        'berita.index', 'berita.create', 'berita.update', 'berita.delete',
        'withdraw_request.index', 'withdraw_request.create', 'withdraw_request.update', 'withdraw_request.delete',
        'role.index', 'role.create', 'role.update', 'role.delete',
        'user.index', 'user.create', 'user.update', 'user.delete',
    ];
}
$adminRole->access = $allGates;
$adminRole->save();

$admin = User::firstOrCreate(
    ['email' => 'testing@ciptamuri.com'],
    [
        'name' => 'Tester',
        'nik' => '9999999999999999',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]
);
$admin->roles()->syncWithoutDetaching([$adminRole->id]);

$defaultRek = Rekening::firstOrNew(['no_rekening' => '00000000']);
if (! $defaultRek->exists) {
    $defaultRek->fill([
        'nama' => 'Kas Bank Sampah',
        'gender' => 'Laki-laki',
        'status_desa' => true,
        'no_kk' => (string) random_int(10_0000_0000_0000_00, 99_9999_9999_9999_99),
        'nik' => (string) random_int(10_0000_0000_0000_00, 99_9999_9999_9999_99),
        'tanggal_lahir' => now(),
        'pendidikan' => '-',
        'alamat' => '-',
        'telepon' => '-',
        'balance' => 0,
        'points_balance' => 0,
        'user_id' => $admin->id,
    ]);
    $defaultRek->save();
}

echo "Playwright admin/bootstrap ready.\n";
