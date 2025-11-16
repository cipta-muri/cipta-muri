<?php

namespace Database\Factories;

use App\Enums\PermintaanStatus;
use App\Models\PermintaanSetorSampah;
use App\Models\Rekening;
use App\Models\Sampah;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermintaanSetorSampah>
 */
class PermintaanSetorSampahFactory extends Factory
{
    protected $model = PermintaanSetorSampah::class;

    public function definition(): array
    {
        $sampahItems = Sampah::factory()->count(2)->create();

        $detailItems = $sampahItems->map(function (Sampah $sampah) {
            $weight = $this->faker->randomFloat(2, 0.5, 5);

            return [
                'sampah_id' => $sampah->id,
                'berat' => $weight,
                'description' => 'Setoran Sampah',
                'type' => 'masuk',
                'harga_saldo' => $sampah->saldo_per_kg,
                'poin_per_kg' => $sampah->poin_per_kg ?? 0,
            ];
        });

        $totalBerat = $detailItems->sum(fn (array $item) => $item['berat']);
        $totalSaldo = $detailItems->sum(fn (array $item) => $item['berat'] * ($item['harga_saldo'] ?? 0));
        $totalPoin = $detailItems->sum(fn (array $item) => (int) round($item['berat'] * ($item['poin_per_kg'] ?? 0)));

        return [
            'rekening_id' => Rekening::factory(),
            'requested_by_rekening_id' => fn (array $attributes) => $attributes['rekening_id'],
            'requested_by_user_id' => null,
            'jenis_setoran' => 'rekening',
            'tanggal_setor' => now()->toDateString(),
            'total_berat' => $totalBerat,
            'total_saldo_dihasilkan' => $totalSaldo,
            'total_poin_dihasilkan' => $totalPoin,
            'calculation_performed' => true,
            'detail_items' => $detailItems->toArray(),
            'status' => PermintaanStatus::MenungguKonfirmasi->value,
            'requested_at' => now()->subMinutes($this->faker->numberBetween(0, 240)),
            'qr_token' => bin2hex(random_bytes(16)),
            'qr_token_expires_at' => now()->addDays(7),
            'source' => 'mobile_banking',
            'meta' => [
                'device_id' => $this->faker->uuid(),
                'ip' => $this->faker->ipv4(),
            ],
        ];
    }

    public function approved(): self
    {
        return $this->state(fn () => [
            'status' => PermintaanStatus::Disetujui->value,
            'confirmed_at' => now(),
            'confirmed_by' => User::factory(),
            'processed_via' => 'table',
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn () => [
            'status' => PermintaanStatus::Ditolak->value,
            'confirmed_at' => now(),
            'confirmed_by' => User::factory(),
            'rejection_reason' => fake()->sentence(),
            'processed_via' => 'table',
        ]);
    }
}
