<?php

namespace App\Models\Concerns;

use App\Enums\PermintaanStatus;
use App\Notifications\PermintaanStatusNotification;

trait NotifiesPermintaanStatus
{
    protected function notifyNasabah(PermintaanStatus $status, string $title, ?string $message = null, ?string $url = null): void
    {
        $user = $this->rekening?->user;

        if (! $user) {
            return;
        }

        $user->notify(new PermintaanStatusNotification(
            $this->permintaanNotificationType(),
            $status,
            $title,
            $message,
            $url
        ));
    }

    abstract protected function permintaanNotificationType(): string;
}
