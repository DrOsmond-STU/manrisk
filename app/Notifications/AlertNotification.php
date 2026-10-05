<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Notifikasi early warning: tersimpan di basis data (lonceng) dan dikirim lewat email. */
class AlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Alert $alert)
    {
    }

    public function via(object $notifiable): array
    {
        $prefs = $notifiable->preferences['notify'] ?? ['database', 'mail'];
        return array_values(array_intersect(['database', 'mail'], $prefs ?: ['database']));
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
