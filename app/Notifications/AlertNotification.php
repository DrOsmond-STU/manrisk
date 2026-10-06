<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi early warning. Lonceng aplikasi membaca tabel `alerts` sehingga selalu tampil;
 * preferensi pengguna hanya mengatur salinan email (preferences.notify berisi 'mail', bawaan aktif).
 */
class AlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Alert $alert)
    {
    }

    public function via(object $notifiable): array
    {
        $prefs = $notifiable->preferences['notify'] ?? ['mail'];
        // 'database' hanya arsip pengiriman (tidak dibaca UI, bukan pilihan pengguna); email mengikuti preferensi
        return in_array('mail', (array) $prefs, true) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $severity = ['critical' => 'KRITIS', 'warning' => 'PERINGATAN', 'info' => 'INFO'][$this->alert->severity] ?? 'INFO';
        $mail = (new MailMessage())
            ->subject("[ManRisk] {$severity}: " . mb_substr($this->alert->title, 0, 120))
            ->greeting("Halo {$notifiable->name},")
            ->line($this->alert->title);
        if ($this->alert->message) {
            $mail->line($this->alert->message);
        }
        if ($this->alert->link) {
            $mail->action('Buka di ManRisk', url($this->alert->link));
        }
        return $mail->line('Pesan ini dikirim otomatis oleh ManRisk ERM.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'type' => $this->alert->type,
            'severity' => $this->alert->severity,
            'title' => $this->alert->title,
            'message' => $this->alert->message,
            'link' => $this->alert->link,
        ];
    }
}
