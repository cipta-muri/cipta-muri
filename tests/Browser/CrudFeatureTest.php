<?php

/** @var \Tests\DuskTestCase $this */


use App\Models\User;
use App\Models\Sampah;
use App\Models\Rekening;
use App\Models\Pemasukan;
use App\Models\Pengeluaran;
use App\Models\SumberPemasukan;
use App\Models\KategoriPengeluaran;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;


/**
 * Helpers
 */
function dusk_base(): string
{
    $url = env('DUSK_BASE_URL') ?: (config('app.url') ?? 'http://127.0.0.1:8000');
    return rtrim($url, '/');
}

function dusk_host(): string
{
    return parse_url(dusk_base(), PHP_URL_HOST) ?? 'localhost';
}
function ensureAdminWithFullAccess(): User
{
    // Make sure the Admin role exists
    $adminRole = HexaRole::firstOrCreate(
        ['name' => 'Super Admin', 'guard' => 'web'],
        ['created_by_name' => 'Seeder', 'access' => []],
    );

    // Grant all panel gates to Admin role so it can access every resource
    $panel = Filament::getPanel('admin');
    $allGates = hexa()->panelGates($panel);
    // Fallback hard-coded gates if dynamic discovery returns empty
    if (empty($allGates)) {
        $allGates = [
            // Sampah
            'sampah.index','sampah.create','sampah.update','sampah.delete',
            // Rekening
            'rekening.index','rekening.create','rekening.update','rekening.delete',
            // Pemasukan
            'pemasukan.index','pemasukan.create','pemasukan.update','pemasukan.delete',
            // Pengeluaran
            'pengeluaran.index','pengeluaran.create','pengeluaran.update','pengeluaran.delete',
            // Setor Sampah
            'setor_sampah.index','setor_sampah.view','setor_sampah.create','setor_sampah.update','setor_sampah.delete',
            // Sampah Keluar
            'sampah_keluar.index','sampah_keluar.create','sampah_keluar.update','sampah_keluar.delete',
            // Berita
            'berita.index','berita.create','berita.update','berita.delete',
            // Withdraw Request
            'withdraw_request.index','withdraw_request.create','withdraw_request.update','withdraw_request.delete',
            // Role & User
            'role.index','role.create','role.update','role.delete',
            'user.index','user.create','user.update','user.delete',
        ];
    }
    $adminRole->access = $allGates;
    $adminRole->save();

    // Ensure admin user exists and has Admin role
    $admin = User::firstOrCreate(
        ['email' => 'testing@ciptamuri.com'],
        [
            'name' => 'Tester',
            'password' => 'password',
            'email_verified_at' => now(),
        ]
    );

    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

    // Ensure default donation/cash Rekening exists (no_rekening '00000000')
    $defaultRek = Rekening::firstOrNew(['no_rekening' => '00000000']);
    if (! $defaultRek->exists) {
        $defaultRek->fill([
            'nama' => 'Kas Bank Sampah',
            'gender' => 'Laki-laki',
            'status_desa' => true,
            'no_kk' => (string) random_int(10_0000_0000_0000_00, 99_9999_9999_9999_99),
            'nik' => (string) random_int(10_0000_0000_0000_00, 99_9999_9999_9999_99),
            'user_id' => $admin->id,
            'balance' => 0,
        ]);
        $defaultRek->save();
    }

    return $admin->fresh();
}

function loginToAdmin(Browser $browser, string $email, string $password): void
{
    // Always use form-based login to avoid relying on _dusk routes and APP_URL
    $browser
        ->visit(dusk_base() . '/admin/login')
        ->assertHostIs(dusk_host())
        ->waitFor('input[id="data.email"]', 10)
        ->type('input[id="data.email"]', $email)
        ->waitFor('input[id="data.password"]', 5)
        ->type('input[id="data.password"]', $password)
        ->click('button[type=submit]')
        ->waitForLocation('/admin', 10)
        ->assertPathIs('/admin');
}

function logoutFromAdmin(Browser $browser): void
{
    $browser->visit(dusk_base() . '/_dusk/logout');
}

test('Test Login (user dapat login)', function () {
    $admin = ensureAdminWithFullAccess();

    $this->browse(function (Browser $browser) use ($admin) {
        loginToAdmin($browser, $admin->email, 'password');
        // Ensure we are on a Filament page (not 404/nginx)
        $browser->visit(dusk_base() . '/admin')->waitFor('.fi-main', 10)->assertPresent('.fi-main');
    });
});

test('Sampah: Create, Read, Update, Delete', function () {
    $admin = ensureAdminWithFullAccess();

    $name = 'Dusk Sampah ' . Str::upper(Str::random(5));
    $updated = $name . ' Updated';

    $this->browse(function (Browser $browser) use ($admin, $name, $updated) {
        // Create
        $browser->visit(dusk_base() . '/admin/sampahs/create')
            ->waitFor('input[id="data.jenis_sampah"]', 10)
            ->type('input[id="data.jenis_sampah"]', $name)
            ->waitFor('input[id="data.saldo_per_kg"]', 5)
            ->type('input[id="data.saldo_per_kg"]', '1000')
            ->click('button[type=submit]')
            ->waitForText('Data berhasil dibuat', 10)
            ->assertSee('Data berhasil dibuat');

        // Read - appears on index
        $browser->visit(dusk_base() . '/admin/sampahs')
            ->assertSee($name);

        // Fetch created record id
        $record = Sampah::where('jenis_sampah', $name)->latest('created_at')->firstOrFail();

        // Update via edit page
        $browser->visit(dusk_base() . "/admin/sampahs/{$record->id}/edit")
            ->waitFor('input[name="data.jenis_sampah"]', 10)
            ->clear('input[name="data.jenis_sampah"]')
            ->type('input[name="data.jenis_sampah"]', $updated)
            ->click('button[type=submit]')
            ->waitForText('Data berhasil disimpan', 10)
            ->assertSee('Data berhasil disimpan')
            ->visit(dusk_base() . '/admin/sampahs')
            ->assertSee($updated)
            ->assertDontSee($name);

        // Delete via edit page action (soft delete)
        $browser->visit(dusk_base() . "/admin/sampahs/{$record->id}/edit")
            ->press('Hapus')
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->press('Hapus');
            })
            ->waitForText('Data berhasil dihapus', 10)
            ->assertSee('Data berhasil dihapus')
            ->visit(dusk_base() . '/admin/sampahs')
            ->assertDontSee($updated);
    });
});

test('Rekening: Delete, Restore, Force Delete', function () {
    $admin = ensureAdminWithFullAccess();

    // Prepare a Rekening record directly to avoid complex form filling, then manage via UI
    $prefix = (bool) random_int(0, 1) ? '0' : '1'; // 0=desa, 1=luar desa
    $datePart = Carbon::now()->format('my');
    do {
        $sequencePart = str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
        $noRekening = $prefix . $datePart . $sequencePart; // 8 digits
    } while (Rekening::where('no_rekening', $noRekening)->exists());

    $rek = Rekening::create([
        'no_rekening' => $noRekening,
        'nama' => 'Dusk Rekening ' . Str::upper(Str::random(4)),
        'gender' => 'Laki-laki',
        'no_kk' => (string) random_int(10_0000_0000_0000_00, 99_9999_9999_9999_99),
        'nik' => (string) random_int(10_0000_0000_0000_00, 99_9999_9999_9999_99),
        'status_desa' => $prefix === '0',
        'user_id' => $admin->id,
        'balance' => 0,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $rek) {
        // Ensure record visible on index
        $browser->visit(dusk_base() . '/admin/rekenings')
            ->waitForText($rek->nama, 10)
            ->assertSee($rek->nama);

        // Delete via table row action
        $browser->visit(dusk_base() . '/admin/rekenings')
            ->with("tbody tr:contains('{$rek->nama}')", function (Browser $row) {
                $row->press('Hapus');
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->press('Hapus');
            })
            ->waitForText('Data berhasil dihapus', 10)
            ->assertSee('Data berhasil dihapus');

        // Show only trashed and restore it
        $browser->visit(dusk_base() . '/admin/rekenings?tableFilters%5Btrashed%5D%5Bvalue%5D=false')
            ->waitForText($rek->nama, 10)
            ->with("tbody tr:contains('{$rek->nama}')", function (Browser $row) {
                $row->press('Pulihkan');
            })
            ->waitForText('Data berhasil dipulihkan', 10)
            ->assertSee('Data berhasil dipulihkan');

        // Soft-deleted again to test force delete
        $browser->visit(dusk_base() . '/admin/rekenings')
            ->with("tbody tr:contains('{$rek->nama}')", function (Browser $row) {
                $row->press('Hapus');
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->press('Hapus');
            })
            ->waitForText('Data berhasil dihapus', 10);

        // Force delete from trashed list via per-row action
        $browser->visit(dusk_base() . '/admin/rekenings?tableFilters%5Btrashed%5D%5Bvalue%5D=false')
            ->waitForText($rek->nama, 10)
            ->with("tbody tr:contains('{$rek->nama}')", function (Browser $row) {
                $row->press('Hapus selamanya');
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->press('Hapus');
            })
            ->waitForText('Data berhasil dihapus', 10)
            ->assertSee('Data berhasil dihapus');

        // Verify it's gone from all views
        $browser->visit(dusk_base() . '/admin/rekenings')
            ->assertDontSee($rek->nama)
            ->visit(dusk_base() . '/admin/rekenings?tableFilters%5Btrashed%5D%5Bvalue%5D=true') // with trashed
            ->assertDontSee($rek->nama)
            ->visit(dusk_base() . '/admin/rekenings?tableFilters%5Btrashed%5D%5Bvalue%5D=false') // only trashed
            ->assertDontSee($rek->nama);
    });
});

test('Pemasukan: Delete, Restore, Force Delete', function () {
    $admin = ensureAdminWithFullAccess();

    // Seed source and pemasukan record programmatically for UI actions
    $source = SumberPemasukan::create([
        'nama_pemasukan' => 'DUSK SOURCE ' . Str::upper(Str::random(6)),
    ]);

    $pem = Pemasukan::create([
        'tanggal' => now(),
        'sumber_pemasukan_id' => $source->id,
        'nominal' => 12345,
        'metode_pembayaran' => 'Tunai',
        'user_id' => $admin->id,
        'rekening_id' => Rekening::where('no_rekening', '00000000')->value('id'),
    ]);

    $this->browse(function (Browser $browser) use ($admin, $source, $pem) {
        // Visible on index
        $browser->visit(dusk_base() . '/admin/pemasukans')
            ->waitForText($source->nama_pemasukan, 10)
            ->assertSee($source->nama_pemasukan);

        // Soft-delete
        $browser->with("tbody tr:contains('{$source->nama_pemasukan}')", function (Browser $row) {
                $row->press('Hapus');
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->press('Hapus');
            })
            ->waitForText('Data berhasil dihapus', 10)
            ->assertSee('Data berhasil dihapus');

        // Restore from trashed
        $browser->visit(dusk_base() . '/admin/pemasukans?tableFilters%5Btrashed%5D%5Bvalue%5D=false')
            ->waitForText($source->nama_pemasukan, 10)
            ->with("tbody tr:contains('{$source->nama_pemasukan}')", function (Browser $row) {
                $row->press('Pulihkan');
            })
            ->waitForText('Data berhasil dipulihkan', 10)
            ->assertSee('Data berhasil dipulihkan');

        // Soft-delete again
        $browser->visit(dusk_base() . '/admin/pemasukans')
            ->with("tbody tr:contains('{$source->nama_pemasukan}')", function (Browser $row) {
                $row->press('Hapus');
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->press('Hapus');
            })
            ->waitForText('Data berhasil dihapus', 10);

        // Force delete
        $browser->visit(dusk_base() . '/admin/pemasukans?tableFilters%5Btrashed%5D%5Bvalue%5D=false')
            ->waitForText($source->nama_pemasukan, 10)
            ->with("tbody tr:contains('{$source->nama_pemasukan}')", function (Browser $row) {
                $row->press('Hapus selamanya');
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->press('Hapus');
            })
            ->waitForText('Data berhasil dihapus', 10)
            ->assertSee('Data berhasil dihapus')
            ->visit(dusk_base() . '/admin/pemasukans')
            ->assertDontSee($source->nama_pemasukan);
       
    });
});

test('Pengeluaran: Delete, Restore, Force Delete', function () {
    $admin = ensureAdminWithFullAccess();

    $kategori = KategoriPengeluaran::create([
        'nama_pengeluaran' => 'DUSK KAT ' . Str::upper(Str::random(6)),
    ]);

    $peng = Pengeluaran::create([
        'tanggal' => now(),
        'kategori_pengeluaran_id' => $kategori->id,
        'nominal' => 5432,
        'metode_pembayaran' => 'Tunai',
        'user_id' => $admin->id,
        'rekening_id' => Rekening::where('no_rekening', '00000000')->value('id'),
    ]);

    $this->browse(function (Browser $browser) use ($admin, $kategori) {
        loginToAdmin($browser, $admin->email, 'password');

        // Visible on index
        $browser->visit(dusk_base() . '/admin/pengeluarans')
            ->waitForText($kategori->nama_pengeluaran, 10)
            ->assertSee($kategori->nama_pengeluaran);

        // Soft-delete
        $browser->with("tbody tr:contains('{$kategori->nama_pengeluaran}')", function (Browser $row) {
                $row->press('Hapus');
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->press('Hapus');
            })
            ->waitForText('Data berhasil dihapus', 10)
            ->assertSee('Data berhasil dihapus');

        // Restore from trashed
        $browser->visit(dusk_base() . '/admin/pengeluarans?tableFilters%5Btrashed%5D%5Bvalue%5D=false')
            ->waitForText($kategori->nama_pengeluaran, 10)
            ->with("tbody tr:contains('{$kategori->nama_pengeluaran}')", function (Browser $row) {
                $row->press('Pulihkan');
            })
            ->waitForText('Data berhasil dipulihkan', 10)
            ->assertSee('Data berhasil dipulihkan');

        // Soft-delete again and force delete
        $browser->visit(dusk_base() . '/admin/pengeluarans')
            ->with("tbody tr:contains('{$kategori->nama_pengeluaran}')", function (Browser $row) {
                $row->press('Hapus');
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->press('Hapus');
            })
            ->waitForText('Data berhasil dihapus', 10)
            ->visit(dusk_base() . '/admin/pengeluarans?tableFilters%5Btrashed%5D%5Bvalue%5D=false')
            ->waitForText($kategori->nama_pengeluaran, 10)
            ->with("tbody tr:contains('{$kategori->nama_pengeluaran}')", function (Browser $row) {
                $row->press('Hapus selamanya');
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                $modal->press('Hapus');
            })
            ->waitForText('Data berhasil dihapus', 10)
            ->assertSee('Data berhasil dihapus')
            ->visit(dusk_base() . '/admin/pengeluarans')
            ->assertDontSee($kategori->nama_pengeluaran);
    });
});

test('Logout (user dapat logout)', function () {
    $admin = ensureAdminWithFullAccess();

    $this->browse(function (Browser $browser) use ($admin) {
        logoutFromAdmin($browser);

        // Ensure we are back on login page
        $browser->waitFor('input[id="data.email"]', 10)
            ->assertPathIs('/admin/login');
    });
});

