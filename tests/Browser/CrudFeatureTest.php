<?php

/** @var \Tests\DuskTestCase $this */

use App\Models\User;
use App\Models\Sampah;
use App\Models\Rekening;
use App\Models\SetorSampah;
use App\Models\SampahKeluar;
use App\Models\WithdrawRequest;
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
    $adminRole = HexaRole::firstOrCreate(
        ['name' => 'Super Admin', 'guard' => 'web'],
        ['created_by_name' => 'Seeder', 'access' => []],
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
            'nik' => (string) random_int(10_0000_0000_0000_00, 99_9999_9999_9999_99),
            'password' => 'password',
            'email_verified_at' => now(),
        ]
    );
    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

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
    $browser->visit(dusk_base() . '/admin/login')->assertHostIs(dusk_host());

    $browser->waitFor('button[type=submit]', 10);

    $browser->script(<<<"JS"
(function() {
    const fillFirst = (selectors, value) => {
        for (const sel of selectors) {
            const el = document.querySelector(sel);
            if (el) {
                el.value = value;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            }
        }
        return false;
    };
    fillFirst(['input[id="data.email"]','input[name="email"]','input[id="email"]','input[name="nik"]','input[id="nik"]'], "{$email}");
    fillFirst(['input[id="data.password"]','input[name="password"]','input[id="password"]'], "{$password}");
})();
JS);

    $browser->click('button[type=submit]')
        ->waitForLocation('/admin', 20)
        ->assertPathIs('/admin')
        ->waitFor('.fi-main', 20);
}

function logoutFromAdmin(Browser $browser): void
{
    $browser->visit(dusk_base() . '/_dusk/logout');
}

function filamentTableRowSelector(int|string $recordKey): string
{
    $key = addcslashes((string) $recordKey, "\\\"");
    return "[wire\\:key\$=\".table.records.{$key}\"]";
}

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
        $browser->visit(dusk_base() . '/admin')->waitFor('.fi-main', 20)->assertPresent('.fi-main');
    });
});

test('Sampah: Create, Read, Update, Delete', function () {
    $admin = ensureAdminWithFullAccess();

    $name = 'Dusk Sampah ' . Str::upper(Str::random(5));
    $updated = 'Dusk Updated Sampah ' . Str::upper(Str::random(5));

    $this->browse(function (Browser $browser) use ($admin, $name, $updated) {
        loginToAdmin($browser, $admin->email, 'password');

        $browser->visit(dusk_base() . '/admin/sampahs/create');

        setFilamentInputValue($browser, 'input[dusk="sampah-form-jenis_sampah"]', $name);
        setFilamentInputValue($browser, 'input[dusk="sampah-form-saldo_per_kg"]', '1000', 5);

        submitFilamentForm($browser);

        assertFilamentNotification($browser, 'Data berhasil dibuat');

        $browser->visit(dusk_base() . '/admin/sampahs?tableSearch=' . urlencode($name))
            ->waitForText($name, 10)
            ->assertSee($name);

        $record = Sampah::where('jenis_sampah', $name)->latest('created_at')->firstOrFail();
        $sampahRowSelector = filamentTableRowSelector($record->getKey());

        $browser->visit(dusk_base() . "/admin/sampahs/{$record->id}/edit");
        setFilamentInputValue($browser, 'input[dusk="sampah-form-jenis_sampah"]', $updated);
        submitFilamentForm($browser);

        assertFilamentNotification($browser, 'Data berhasil disimpan');

        $browser->visit(dusk_base() . '/admin/sampahs')
            ->waitFor($sampahRowSelector, 10)
            ->assertSee($updated)
            ->assertDontSee($name);

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

test('Setor Sampah: Create and Delete', function () {
    $admin = ensureAdminWithFullAccess();

    $rekening = Rekening::factory()->create([
        'balance' => 100000,
        'user_id' => $admin->id,
    ]);

    $sampah = Sampah::factory()->create([
        'jenis_sampah' => 'DUSK SETOR ' . Str::upper(Str::random(4)),
        'saldo_per_kg' => 1500,
        'total_berat_terkumpul' => 0,
        'user_id' => $admin->id,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $rekening, $sampah) {
        loginToAdmin($browser, $admin->email, 'password');

        $berat = 1.25;
        $totalSaldo = (int) round($sampah->saldo_per_kg * $berat);
        $tanggal = now()->format('Y-m-d');

        $browser->visit(dusk_base() . '/admin/setor-sampahs/create');

        setFilamentInputValue($browser, 'input[id="data.jenis_setoran"]', 'rekening');
        setFilamentInputValue($browser, 'input[id="data.rekening_id"]', (string) $rekening->id);
        setFilamentInputValue($browser, 'input[id="data.tanggal"]', $tanggal);
        setFilamentInputValue($browser, 'input[id="data.details.0.sampah_id"]', (string) $sampah->id);
        setFilamentInputValue($browser, 'input[id="data.details.0.berat"]', (string) $berat);
        setFilamentInputValue($browser, 'input[id="data.calculation_performed"]', '1');
        setFilamentInputValue($browser, 'input[id="data.total_saldo_dihasilkan"]', (string) $totalSaldo);
        setFilamentInputValue($browser, 'input[id="data.total_poin_dihasilkan"]', '0');
        setFilamentInputValue($browser, 'input[id="data.berat"]', (string) $berat);

        submitFilamentForm($browser);

        $browser->waitForText($rekening->nama, 10);

        $record = SetorSampah::where('rekening_id', $rekening->id)
            ->whereDate('tanggal', $tanggal)
            ->latest('created_at')
            ->firstOrFail();

        $rowSelector = filamentTableRowSelector($record->getKey());

        $browser->visit(dusk_base() . '/admin/setor-sampahs?tableSearch=' . urlencode($rekening->nama))
            ->waitFor($rowSelector, 10)
            ->tap(function (Browser $browser) use ($rowSelector) {
                clickElement($browser, "{$rowSelector} [dusk=\"setor-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            })
            ->assertDontSee($rekening->nama);
    });
});

test('Sampah Keluar: Create and Delete', function () {
    $admin = ensureAdminWithFullAccess();

    $sampah = Sampah::factory()->create([
        'jenis_sampah' => 'DUSK KELUAR ' . Str::upper(Str::random(4)),
        'total_berat_terkumpul' => 10,
        'user_id' => $admin->id,
    ]);

    $tanggal = Carbon::now()->addDay()->format('Y-m-d');

    $this->browse(function (Browser $browser) use ($admin, $sampah, $tanggal) {
        loginToAdmin($browser, $admin->email, 'password');

        $browser->visit(dusk_base() . '/admin/sampah-keluars/create');

        setFilamentInputValue($browser, 'input[id="data.jenis_keluar"]', 'bakar');
        setFilamentInputValue($browser, 'input[id="data.tanggal_keluar"]', $tanggal);
        setFilamentInputValue($browser, 'input[id="data.details.0.sampah_id"]', (string) $sampah->id);
        setFilamentInputValue($browser, 'input[id="data.details.0.berat"]', '1.5');

        submitFilamentForm($browser);

        $record = SampahKeluar::where('tanggal_keluar', $tanggal)
            ->latest('created_at')
            ->firstOrFail();

        $rowSelector = filamentTableRowSelector($record->getKey());

        $browser->visit(dusk_base() . '/admin/sampah-keluars')
            ->waitFor($rowSelector, 10)
            ->tap(function (Browser $browser) use ($rowSelector) {
                clickElement($browser, "{$rowSelector} [dusk=\"sampah-keluar-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            })
            ->assertDontSee($tanggal);
    });
});

test('Withdraw Request: Create and Delete', function () {
    $admin = ensureAdminWithFullAccess();

    $rekening = Rekening::factory()->create([
        'balance' => 250000,
        'user_id' => $admin->id,
    ]);

    $amount = 15000;

    $this->browse(function (Browser $browser) use ($admin, $rekening, $amount) {
        loginToAdmin($browser, $admin->email, 'password');

        $browser->visit(dusk_base() . '/admin/withdraw-requests/create');

        setFilamentInputValue($browser, 'input[id="data.rekening_id"]', (string) $rekening->id);
        setFilamentInputValue($browser, 'input[id="data.jenis"]', 'cash');
        setFilamentInputValue($browser, 'input[id="data.amount"]', (string) $amount);

        submitFilamentForm($browser);

        $record = WithdrawRequest::where('rekening_id', $rekening->id)
            ->latest('created_at')
            ->firstOrFail();

        $rowSelector = filamentTableRowSelector($record->getKey());

        $search = urlencode($rekening->nama);
        $browser->visit(dusk_base() . '/admin/withdraw-requests?tableSearch=' . $search)
            ->waitFor($rowSelector, 10)
            ->tap(function (Browser $browser) use ($rowSelector) {
                clickElement($browser, "{$rowSelector} [dusk=\"withdraw-delete-action\"]");
            })
            ->whenAvailable('.fi-modal', function (Browser $modal) {
                clickElement($modal, 'button[type=submit]');
            })
            ->assertDontSee($rekening->nama);
    });
});

test('Logout (user dapat logout)', function () {
    $admin = ensureAdminWithFullAccess();

    $this->browse(function (Browser $browser) use ($admin) {
        logoutFromAdmin($browser);

        $browser
            ->visit(dusk_base() . '/admin/login')
            ->waitFor('input[id="data.email"]', 10)
            ->assertPathIs('/admin/login');
    });
});
