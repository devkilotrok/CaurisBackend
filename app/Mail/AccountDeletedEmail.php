<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $pseudo;
    public $reason;

    /**
     * Create a new message instance.
     */
    public function __construct($pseudo, $reason = null)
    {
        $this->pseudo = $pseudo;
        $this->reason = $reason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '❌ Votre compte a été supprimé - CAURIS DEGUE Callbreak',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.account-deleted',
            with: [
                'pseudo' => $this->pseudo,
                'reason' => $this->reason,
            ],
        );
    }
}

