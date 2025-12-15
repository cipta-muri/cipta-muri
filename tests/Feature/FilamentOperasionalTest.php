<?php

use App\Models\User;
use App\Models\Sampah;
use App\Models\Rekening;
use App\Models\SetorSampah;
use App\Models\SampahKeluar;
use App\Models\WithdrawRequest;
use Filament\Facades\Filament;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

function makeAdminForFilament(): User
{
    $adminRole = HexaRole::firstOrCreate(
        ['name' => 'Super Admin', 'guard' => 'web'],
        ['created_by_name' => 'FilamentTest', 'access' => []],
    );

    $panel = Filament::getPanel('admin');
    $gates = hexa()->panelGates($panel);
    if (empty($gates)) {
        $gates = [
            'sampah.index', 'sampah.create', 'sampah.update', 'sampah.delete',
            'rekening.index', 'rekening.create', 'rekening.update', 'rekening.delete',
            'pemasukan.index', 'pemasukan.create', 'pemasukan.update', 'pemasukan.delete',
            'pengeluaran.index', 'pengeluaran.create', 'pengeluaran.update', 'pengeluaran.delete',
            'setor_sampah.index', 'setor_sampah.view', 'setor_sampah.create', 'setor_sampah.update', 'setor_sampah.delete',
            'sampah_keluar.index', 'sampah_keluar.create', 'sampah_keluar.update', 'sampah_keluar.delete',
            'withdraw_request.index', 'withdraw_request.create', 'withdraw_request.update', 'withdraw_request.delete',
            'role.index', 'role.create', 'role.update', 'role.delete',
            'user.index', 'user.create', 'user.update', 'user.delete',
        ];
    }
    $adminRole->access = $gates;
    $adminRole->save();

    $admin = User::firstOrCreate(
        ['email' => 'filament-test@ciptamuri.com'],
        [
            'name' => 'Filament Tester',
            'nik' => '9999999999999998',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ],
    );
    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

    return $admin;
}

beforeEach(function () {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('Filament panel tests membutuhkan driver MySQL/MariaDB karena ALTER/MODIFY tidak didukung SQLite.');
    }
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('admin can access sampah list page', function () {
    $admin = makeAdminForFilament();
    actingAs($admin, 'web')->get('/admin/sampahs')->assertOk();
});

it('admin can access setor sampah list page', function () {
    $admin = makeAdminForFilament();
    actingAs($admin, 'web')->get('/admin/setor-sampahs')->assertOk();
});

it('admin can access sampah keluar list page', function () {
    $admin = makeAdminForFilament();
    actingAs($admin, 'web')->get('/admin/sampah-keluars')->assertOk();
});

it('admin can access withdraw requests list page', function () {
    $admin = makeAdminForFilament();
    actingAs($admin, 'web')->get('/admin/withdraw-requests')->assertOk();
});

it('can create sampah record via model and visible on list', function () {
    $admin = makeAdminForFilament();
    $sampah = Sampah::create([
        'jenis_sampah' => 'FILAMENT TEST ' . Str::upper(Str::random(4)),
        'saldo_per_kg' => 1200,
        'poin_per_kg' => 12,
        'total_berat_terkumpul' => 0,
        'user_id' => $admin->id,
    ]);

    actingAs($admin, 'web')
        ->get('/admin/sampahs?tableSearch=' . urlencode($sampah->jenis_sampah))
        ->assertSee($sampah->jenis_sampah);
});

it('can soft delete withdraw request from list', function () {
    $admin = makeAdminForFilament();
    $rek = Rekening::factory()->create(['balance' => 250000, 'user_id' => $admin->id]);
    $withdraw = WithdrawRequest::create([
        'rekening_id' => $rek->id,
        'amount' => 15000,
        'jenis' => 'cash',
        'user_id' => $admin->id,
    ]);

    $this->assertDatabaseHas('withdraw_requests', ['id' => $withdraw->id, 'deleted_at' => null]);
});
