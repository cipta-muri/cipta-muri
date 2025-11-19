<?php

namespace App\Jobs;

use App\Enums\PermintaanStatus;
use App\Models\PermintaanSetorSampah;
use App\Models\PermintaanTarikSaldo;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CleanupPermintaanJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $retentionDays = 30,
        protected int $expirationDays = 7,
    ) {}

    public function handle(): void
    {
        $systemUser = $this->resolveSystemUser();

        $this->expireWaiting(PermintaanTarikSaldo::class, $systemUser);
        $this->expireWaiting(PermintaanSetorSampah::class, $systemUser);

        $this->purgeRejected(PermintaanTarikSaldo::class);
        $this->purgeRejected(PermintaanSetorSampah::class);
    }

    protected function expireWaiting(string $modelClass, ?User $systemUser): void
    {
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = $modelClass::query()
            ->where('status', PermintaanStatus::MenungguKonfirmasi->value)
            ->whereNotNull('requested_at')
            ->where('requested_at', '<=', now()->subDays($this->expirationDays));

        $query->chunkById(100, function ($records) use ($systemUser, $modelClass) {
            foreach ($records as $record) {
                try {
                    $record->reject(
                        $systemUser,
                        'Ditolak otomatis karena melebihi batas SLA',
                        'scheduler'
                    );
                } catch (ValidationException $e) {
                    Log::warning("Gagal menandai permintaan {$modelClass} sebagai kedaluwarsa", [
                        'id' => $record->id,
                        'message' => $e->getMessage(),
                    ]);
                } catch (Throwable $e) {
                    Log::error("Kesalahan saat auto-reject permintaan {$modelClass}", [
                        'id' => $record->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    protected function purgeRejected(string $modelClass): void
    {
        $modelClass::query()
            ->where('status', PermintaanStatus::Ditolak->value)
            ->where('updated_at', '<=', now()->subDays($this->retentionDays))
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    $record->delete();
                }
            });

        $modelClass::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($this->retentionDays))
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    $record->forceDelete();
                }
            });
    }

    protected function resolveSystemUser(): ?User
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Super Admin'))
            ->first()
            ?? User::query()->first();
    }
}
