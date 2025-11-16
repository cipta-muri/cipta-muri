<?php

namespace Database\Factories;

use App\Enums\PermintaanStatus;
use App\Models\PermintaanTarikSaldo;
use App\Models\Rekening;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermintaanTarikSaldo>
 */
class PermintaanTarikSaldoFactory extends Factory
{
    protected $model = PermintaanTarikSaldo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rekening_id' => Rekening::factory(),
            'requested_by_rekening_id' => fn (array $attributes) => $attributes['rekening_id'],
            'requested_by_user_id' => null,
            'amount' => $this->faker->numberBetween(25000, 1500000),
            'jenis' => $this->faker->randomElement(['tunai', 'transfer', 'qris']),
            'catatan' => $this->faker->sentence(),
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
