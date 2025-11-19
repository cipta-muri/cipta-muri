<?php

namespace App\Notifications;

use App\Enums\PermintaanStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PermintaanStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $permintaanType,
        public readonly PermintaanStatus $status,
        public readonly string $title,
        public readonly ?string $message = null,
        public readonly ?string $actionUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->message ?? "Status permintaan {$this->permintaanLabel()} kini {$this->status->label()}.");

        if ($this->actionUrl) {
            $mail->action('Lihat Detail', $this->actionUrl);
        }

        return $mail->line('Terima kasih telah menggunakan layanan Cipta Muri.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->permintaanType,
            'status' => $this->status->value,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
        ];
    }

    protected function permintaanLabel(): string
    {
        return match ($this->permintaanType) {
            'tarik_saldo' => 'penarikan saldo',
            'setor_sampah' => 'setoran sampah',
            default => 'permintaan',
        };
    }
}
