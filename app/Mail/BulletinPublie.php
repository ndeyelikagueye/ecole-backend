<?php

namespace App\Mail;

use App\Models\Bulletin;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BulletinPublie extends Mailable
{
    use Queueable, SerializesModels;

    public $bulletin;
    public $periodeLibelle;

    public function __construct(Bulletin $bulletin)
    {
        $this->bulletin = $bulletin;
        $this->periodeLibelle = match($bulletin->periode) {
            'trimestre_1' => '1er Trimestre',
            'trimestre_2' => '2ème Trimestre', 
            'trimestre_3' => '3ème Trimestre',
            default => $bulletin->periode
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📋 Nouveau bulletin disponible - ' . $this->periodeLibelle,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.bulletin-publie',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}