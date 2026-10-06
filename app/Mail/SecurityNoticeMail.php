<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Pemberitahuan keamanan akun (MFA diaktifkan/dinonaktifkan, kode pemulihan dipakai, uji SMTP). */
class SecurityNoticeMail extends Mailable
{
    public function __construct(public string $name, public string $title, public string $body, public string $ip)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[ManRisk] ' . $this->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.security-notice', with: ['name' => $this->name, 'title' => $this->title, 'body' => $this->body, 'ip' => $this->ip]);
    }
}
