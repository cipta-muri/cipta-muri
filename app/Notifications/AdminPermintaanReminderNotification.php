<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class AdminPermintaanReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Collection $tarik,
        protected Collection $setor,
        protected int $slaHours,
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = $this->tarik->count() + $this->setor->count();

        return (new MailMessage)
            ->subject('Reminder Permintaan Menunggu Konfirmasi')
            ->line("Ada {$total} permintaan yang menunggu lebih dari {$this->slaHours} jam.")
            ->line($this->summaryLine())
            ->action('Buka Panel Admin', url('/admin'))
            ->line('Mohon tindak lanjuti agar SLA tetap terjaga.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tarik_count' => $this->tarik->count(),
            'setor_count' => $this->setor->count(),
            'sla_hours' => $this->slaHours,
            'tarik_ids' => $this->tarik->pluck('id')->take(5)->values(),
            'setor_ids' => $this->setor->pluck('id')->take(5)->values(),
        ];
    }

    protected function summaryLine(): string
    {
        $parts = [];

        if ($this->tarik->isNotEmpty()) {
            $parts[] = "{$this->tarik->count()} penarikan";
        }

        if ($this->setor->isNotEmpty()) {
            $parts[] = "{$this->setor->count()} setoran";
        }

        return 'Rincian: ' . implode(' & ', $parts);
    }
}
