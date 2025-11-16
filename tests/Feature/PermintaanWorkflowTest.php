<?php

namespace Tests\Feature;

use App\Enums\PermintaanStatus;
use App\Jobs\CleanupPermintaanJob;
use App\Models\PermintaanSetorSampah;
use App\Models\PermintaanTarikSaldo;
use App\Models\Rekening;
use App\Models\Sampah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermintaanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_tarikan_dapat_dikonfirmasi(): void
    {
        $admin = User::factory()->create();
        $rekening = Rekening::factory()->create(['balance' => 1_000_000]);

        $permintaan = PermintaanTarikSaldo::factory()->create([
            'rekening_id' => $rekening->id,
            'requested_by_rekening_id' => $rekening->id,
            'amount' => 125_000,
        ]);

        $permintaan->confirm($admin, 'test');

        $permintaan->refresh();

        $this->assertTrue($permintaan->status === PermintaanStatus::Disetujui);
        $this->assertNotNull($permintaan->final_withdraw_request_id);
        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $permintaan->final_withdraw_request_id,
            'amount' => 125_000,
        ]);
    }

    public function test_setoran_dapat_dikonfirmasi(): void
    {
        $admin = User::factory()->create();
        $rekening = Rekening::factory()->create();
        $sampah = Sampah::factory()->create(['saldo_per_kg' => 4000]);

        $permintaan = PermintaanSetorSampah::factory()->create([
            'rekening_id' => $rekening->id,
            'requested_by_rekening_id' => $rekening->id,
            'detail_items' => [
                [
                    'sampah_id' => $sampah->id,
                    'berat' => 5,
                    'description' => 'Plastik',
                    'type' => 'masuk',
                    'harga_saldo' => 4000,
                ],
            ],
            'total_berat' => 5,
            'total_saldo_dihasilkan' => 20_000,
            'total_poin_dihasilkan' => 50,
        ]);

        $permintaan->confirm($admin, 'test');

        $permintaan->refresh();

        $this->assertTrue($permintaan->status === PermintaanStatus::Disetujui);
        $this->assertNotNull($permintaan->final_setor_sampah_id);
        $this->assertDatabaseHas('setor_sampah', [
            'id' => $permintaan->final_setor_sampah_id,
            'total_saldo_dihasilkan' => 20_000,
        ]);
        $this->assertDatabaseHas('sampah_transactions', [
            'transactable_id' => $permintaan->final_setor_sampah_id,
            'berat' => 5,
        ]);
    }

    public function test_cleanup_job_menandai_dan_menghapus_permintaan_kedaluwarsa(): void
    {
        $admin = User::factory()->create();
        $rekening = Rekening::factory()->create(['balance' => 500_000]);

        $waiting = PermintaanTarikSaldo::factory()->create([
            'rekening_id' => $rekening->id,
            'requested_by_rekening_id' => $rekening->id,
            'requested_at' => now()->subDays(10),
        ]);

        $rejected = PermintaanTarikSaldo::factory()->rejected()->create([
            'rekening_id' => $rekening->id,
            'requested_by_rekening_id' => $rekening->id,
            'updated_at' => now()->subDays(40),
        ]);

        (new CleanupPermintaanJob(retentionDays: 30, expirationDays: 7))->handle();

        $this->assertEquals(PermintaanStatus::Ditolak, $waiting->fresh()->status);
        $this->assertSoftDeleted('permintaan_tarik_saldo', ['id' => $rejected->id]);
    }
}
