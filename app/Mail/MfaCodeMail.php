<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email berisi kode verifikasi (OTP) MFA. Kode sengaja tidak dicantumkan di subjek. */
class MfaCodeMail extends Mailable
{
    public function __construct(public string $name, public string $code, public string $purpose, public int $ttl, public string $ip)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === 'enable' ? '[ManRisk] Kode aktivasi verifikasi dua langkah' : '[ManRisk] Kode verifikasi masuk');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.mfa-code', with: [
            'name' => $this->name, 'code' => $this->code, 'ttl' => $this->ttl, 'ip' => $this->ip,
            'action' => $this->purpose === 'enable' ? 'mengaktifkan verifikasi dua langkah' : 'masuk ke ManRisk ERM',
        ]);
    }
}
