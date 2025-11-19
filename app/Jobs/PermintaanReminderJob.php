<?php

namespace App\Jobs;

use App\Enums\PermintaanStatus;
use App\Models\PermintaanSetorSampah;
use App\Models\PermintaanTarikSaldo;
use App\Models\User;
use App\Notifications\AdminPermintaanReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class PermintaanReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $slaHours = 6,
    ) {}

    public function handle(): void
    {
        $tarik = $this->collectPending(PermintaanTarikSaldo::class);
        $setor = $this->collectPending(PermintaanSetorSampah::class);

        if ($tarik->isEmpty() && $setor->isEmpty()) {
            return;
        }

        $recipients = $this->recipients();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new AdminPermintaanReminderNotification($tarik, $setor, $this->slaHours)
        );
    }

    protected function collectPending(string $modelClass): Collection
    {
        return $modelClass::query()
            ->where('status', PermintaanStatus::MenungguKonfirmasi->value)
            ->whereNotNull('requested_at')
            ->where('requested_at', '<=', now()->subHours($this->slaHours))
            ->orderBy('requested_at')
            ->get(['id', 'requested_at', 'source'])
            ->map(fn ($record) => [
                'id' => $record->id,
                'requested_at' => $record->requested_at,
                'source' => $record->source,
            ]);
    }

    protected function recipients(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Super Admin', 'Admin', 'Operator']))
            ->get();
    }
}
