<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ScheduledReportMail extends Mailable
{
    use Queueable;

    public function __construct(public string $title, public string $content, public string $file, public string $mime)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ManRisk] Laporan terjadwal: ' . $this->title);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Terlampir laporan <b>' . e($this->title) . '</b> yang dijadwalkan di ManRisk ERM (' . now()->format('d/m/Y H:i') . ').</p><p style="color:#526883;font-size:12px">Email otomatis — jangan dibalas.</p>');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->content, $this->file)->withMime($this->mime)];
    }
}
