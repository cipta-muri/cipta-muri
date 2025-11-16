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
            'sampah.index',
            'sampah.create',
            'sampah.update',
            'sampah.delete',
            // Rekening
            'rekening.index',
            'rekening.create',
            'rekening.update',
            'rekening.delete',
            // Pemasukan
            'pemasukan.index',
            'pemasukan.create',
            'pemasukan.update',
            'pemasukan.delete',
            // Pengeluaran
            'pengeluaran.index',
            'pengeluaran.create',
            'pengeluaran.update',
            'pengeluaran.delete',
            // Setor Sampah
            'setor_sampah.index',
            'setor_sampah.view',
            'setor_sampah.create',
            'setor_sampah.update',
            'setor_sampah.delete',
            // Sampah Keluar
            'sampah_keluar.index',
            'sampah_keluar.create',
            'sampah_keluar.update',
            'sampah_keluar.delete',
            // Berita
            'berita.index',
            'berita.create',
            'berita.update',
            'berita.delete',
            // Withdraw Request
            'withdraw_request.index',
            'withdraw_request.create',
            'withdraw_request.update',
            'withdraw_request.delete',
            // Role & User
            'role.index',
            'role.create',
            'role.update',
            'role.delete',
            'user.index',
            'user.create',
            'user.update',
            'user.delete',
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
    if (!$defaultRek->exists) {
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

function loginAsAdmin(Browser $browser, User $admin): void
{
    $browser->loginAs($admin);
}

function logoutFromAdmin(Browser $browser): void
{
    $browser->visit(dusk_base() . '/_dusk/logout');
}

/**
 * Build a CSS selector that targets a Filament table row for a given record key.
 */
function filamentTableRowSelector(int|string $recordKey): string
{
    $key = addcslashes((string) $recordKey, "\\\"");

    return "[wire\\:key\$=\".table.records.{$key}\"]";
}

/**
 * Force set a value on a Filament text input using JavaScript to avoid wrapper focus issues.
 */
function setFilamentInputValue(Browser $browser, string $selector, string $value, int $waitSeconds = 10): void
{
    $browser->waitFor($selector, $waitSeconds);

    $encodedSelector = addslashes($selector);
    $encodedValue = addslashes($value);

    $browser->script(<<<"JS"
(function () {
    const input = document.querySelector("{$encodedSelector}");
    if (!input) {
        return;
    }
    input.value = "{$encodedValue}";
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
})();
JS);
}

function clickElement(Browser $browser, string $selector, int $waitSeconds = 10): void
{
    $browser->waitFor($selector, $waitSeconds);

    $encodedSelector = addslashes($selector);

    $browser->script(<<<"JS"
(function () {
    const element = document.querySelector("{$encodedSelector}");
    if (!element) {
        return;
    }
    element.click();
})();
JS);
}

function confirmFilamentModal(Browser $browser, int $waitSeconds = 10): void
{
    $selector = '.fi-modal-footer-actions .fi-btn';
    clickElement($browser, $selector, $waitSeconds);
}

function assertFilamentNotification(Browser $browser, string $message, int $waitSeconds = 10): void
{
    $browser->pause(250);
    $browser->waitFor('.fi-no-notification', $waitSeconds)
        ->assertSeeIn('.fi-no-notification', $message);
}

function submitFilamentForm(Browser $browser, string $selector = 'form[wire\\:submit] button[type=submit]'): void
{
    $browser->waitFor($selector, 10);

    $encodedSelector = addslashes($selector);

    $browser->script(<<<"JS"
(function () {
    const button = document.querySelector("{$encodedSelector}");
    if (!button) {
        return;
    }
    button.click();
})();
JS);
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
    $updated = 'Dusk Updated Sampah ' . Str::upper(Str::random(5));

    $this->browse(function (Browser $browser) use ($admin, $name, $updated) {
        loginAsAdmin($browser, $admin);

        // Create
        $browser->visit(dusk_base() . '/admin/sampahs/create');

        setFilamentInputValue($browser, 'input[dusk="sampah-form-jenis_sampah"]', $name);
        setFilamentInputValue($browser, 'input[dusk="sampah-form-saldo_per_kg"]', '1000', 5);

        submitFilamentForm($browser);

        assertFilamentNotification($browser, 'Data berhasil dibuat');

        // Read - appears on index
        $browser->visit(dusk_base() . '/admin/sampahs?tableSearch=' . urlencode($name))
            ->waitForText($name, 10)
            ->assertSee($name);

        // Fetch created record id
        $record = Sampah::where('jenis_sampah', $name)->latest('created_at')->firstOrFail();
        $sampahRowSelector = filamentTableRowSelector($record->getKey());

        // Update via edit page
        $browser->visit(dusk_base() . "/admin/sampahs/{$record->id}/edit");
        setFilamentInputValue($browser, 'input[dusk="sampah-form-jenis_sampah"]', $updated);
        submitFilamentForm($browser);

        assertFilamentNotification($browser, 'Data berhasil disimpan');

        $browser->visit(dusk_base() . '/admin/sampahs')
            ->waitFor($sampahRowSelector, 10)
            ->assertSee($updated)
            ->assertDontSee($name);

        // Delete via table row action (soft delete)
        $browser->visit(dusk_base() . '/admin/sampahs')
            ->waitFor($sampahRowSelector, 10)
            ->tap(function (Browser $browser) use ($sampahRowSelector) {
                clickElement($browser, "{$sampahRowSelector} [dusk=\"sampah-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        $browser->visit(dusk_base() . '/admin/sampahs')
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
        loginAsAdmin($browser, $admin);

        $rekRowSelector = filamentTableRowSelector($rek->getKey());
        $rekSearch = urlencode($rek->nama);
        $rekIndex = dusk_base() . '/admin/rekenings';

        // Ensure record visible on index
        $browser->visit("{$rekIndex}?tableSearch={$rekSearch}")
            ->waitFor($rekRowSelector, 10)
            ->assertSee($rek->nama);

        // Delete via table row action
        $browser->visit("{$rekIndex}?tableSearch={$rekSearch}")
            ->waitFor($rekRowSelector, 10)
            ->tap(function (Browser $browser) use ($rekRowSelector) {
                clickElement($browser, "{$rekRowSelector} [dusk=\"rekening-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dihapus');

        // Show only trashed and restore it
        $browser->visit(dusk_base() . '/admin/rekenings?tableFilters%5Btrashed%5D%5Bvalue%5D=false&tableSearch=' . $rekSearch)
            ->waitForText($rek->nama, 10)
            ->waitFor($rekRowSelector, 10)
            ->tap(function (Browser $browser) use ($rekRowSelector) {
                clickElement($browser, "{$rekRowSelector} [dusk=\"rekening-restore-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dipulihkan');

        // Soft-deleted again to test force delete
        $browser->visit("{$rekIndex}?tableSearch={$rekSearch}")
            ->waitFor($rekRowSelector, 10)
            ->tap(function (Browser $browser) use ($rekRowSelector) {
                clickElement($browser, "{$rekRowSelector} [dusk=\"rekening-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dihapus');

        // Force delete from trashed list via per-row action
        $browser->visit(dusk_base() . '/admin/rekenings?tableFilters%5Btrashed%5D%5Bvalue%5D=false&tableSearch=' . $rekSearch)
            ->waitForText($rek->nama, 10)
            ->waitFor($rekRowSelector, 10)
            ->tap(function (Browser $browser) use ($rekRowSelector) {
                clickElement($browser, "{$rekRowSelector} [dusk=\"rekening-force-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dihapus');

        // Verify it's gone from all views
        $browser->visit("{$rekIndex}?tableSearch={$rekSearch}")
            ->assertDontSee($rek->nama)
            ->visit(dusk_base() . '/admin/rekenings?tableFilters%5Btrashed%5D%5Bvalue%5D=true&tableSearch=' . $rekSearch) // with trashed
            ->assertDontSee($rek->nama)
            ->visit(dusk_base() . '/admin/rekenings?tableFilters%5Btrashed%5D%5Bvalue%5D=false&tableSearch=' . $rekSearch) // only trashed
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
        loginAsAdmin($browser, $admin);

        $pemRowSelector = filamentTableRowSelector($pem->getKey());
        $pemSearch = urlencode($source->nama_pemasukan);
        $pemasukanIndex = dusk_base() . '/admin/pemasukans';

        // Visible on index
        $browser->visit("{$pemasukanIndex}?tableSearch={$pemSearch}")
            ->waitFor($pemRowSelector, 10)
            ->assertSee($source->nama_pemasukan);

        // Soft-delete
        $browser->waitFor($pemRowSelector, 10)
            ->tap(function (Browser $browser) use ($pemRowSelector) {
                clickElement($browser, "{$pemRowSelector} [dusk=\"pemasukan-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dihapus');

        // Restore from trashed
        $browser->visit(dusk_base() . '/admin/pemasukans?tableFilters%5Btrashed%5D%5Bvalue%5D=false&tableSearch=' . $pemSearch)
            ->waitForText($source->nama_pemasukan, 10)
            ->waitFor($pemRowSelector, 10)
            ->tap(function (Browser $browser) use ($pemRowSelector) {
                clickElement($browser, "{$pemRowSelector} [dusk=\"pemasukan-restore-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dipulihkan');

        // Soft-delete again
        $browser->visit("{$pemasukanIndex}?tableSearch={$pemSearch}")
            ->waitFor($pemRowSelector, 10)
            ->tap(function (Browser $browser) use ($pemRowSelector) {
                clickElement($browser, "{$pemRowSelector} [dusk=\"pemasukan-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dihapus');

        // Force delete
        $browser->visit(dusk_base() . '/admin/pemasukans?tableFilters%5Btrashed%5D%5Bvalue%5D=false&tableSearch=' . $pemSearch)
            ->waitForText($source->nama_pemasukan, 10)
            ->waitFor($pemRowSelector, 10)
            ->tap(function (Browser $browser) use ($pemRowSelector) {
                clickElement($browser, "{$pemRowSelector} [dusk=\"pemasukan-force-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dihapus');

        $browser->visit("{$pemasukanIndex}?tableSearch={$pemSearch}")
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

    $this->browse(function (Browser $browser) use ($admin, $kategori, $peng) {
        loginAsAdmin($browser, $admin);

        $pengRowSelector = filamentTableRowSelector($peng->getKey());
        $pengSearch = urlencode($kategori->nama_pengeluaran);
        $pengIndex = dusk_base() . '/admin/pengeluarans';

        // Visible on index
        $browser->visit("{$pengIndex}?tableSearch={$pengSearch}")
            ->waitFor($pengRowSelector, 10)
            ->assertSee($kategori->nama_pengeluaran);

        // Soft-delete
        $browser->waitFor($pengRowSelector, 10)
            ->tap(function (Browser $browser) use ($pengRowSelector) {
                clickElement($browser, "{$pengRowSelector} [dusk=\"pengeluaran-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dihapus');

        // Restore from trashed
        $browser->visit(dusk_base() . '/admin/pengeluarans?tableFilters%5Btrashed%5D%5Bvalue%5D=false&tableSearch=' . $pengSearch)
            ->waitForText($kategori->nama_pengeluaran, 10)
            ->waitFor($pengRowSelector, 10)
            ->tap(function (Browser $browser) use ($pengRowSelector) {
                clickElement($browser, "{$pengRowSelector} [dusk=\"pengeluaran-restore-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dipulihkan');

        // Soft-delete again and force delete
        $browser->visit("{$pengIndex}?tableSearch={$pengSearch}")
            ->waitFor($pengRowSelector, 10)
            ->tap(function (Browser $browser) use ($pengRowSelector) {
                clickElement($browser, "{$pengRowSelector} [dusk=\"pengeluaran-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dihapus');

        $browser->visit(dusk_base() . '/admin/pengeluarans?tableFilters%5Btrashed%5D%5Bvalue%5D=false&tableSearch=' . $pengSearch)
            ->waitForText($kategori->nama_pengeluaran, 10)
            ->waitFor($pengRowSelector, 10)
            ->tap(function (Browser $browser) use ($pengRowSelector) {
                clickElement($browser, "{$pengRowSelector} [dusk=\"pengeluaran-force-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            });

        assertFilamentNotification($browser, 'Data berhasil dihapus');

        $browser->visit("{$pengIndex}?tableSearch={$pengSearch}")
            ->assertDontSee($kategori->nama_pengeluaran);
    });
});

test('Logout (user dapat logout)', function () {
    $admin = ensureAdminWithFullAccess();

    $this->browse(function (Browser $browser) use ($admin) {
        logoutFromAdmin($browser);


        // Ensure we are back on login page
        $browser
            ->visit(dusk_base() . '/admin/login')
            ->waitFor('input[id="data.email"]', 10)
            ->assertPathIs('/admin/login');
    });
});
