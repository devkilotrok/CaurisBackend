<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionRejectedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $type;
    public $caurisAmount;
    public $fcfaAmount;
    public $pseudo;
    public $notes;

    /**
     * Create a new message instance.
     */
    public function __construct($type, $caurisAmount, $fcfaAmount, $pseudo, $notes = null)
    {
        $this->type = $type;
        $this->caurisAmount = $caurisAmount;
        $this->fcfaAmount = $fcfaAmount;
        $this->pseudo = $pseudo;
        $this->notes = $notes;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->type === 'depot' 
            ? '⚠️ Votre dépôt a été rejeté - CAURIS DEGUE Callbreak'
            : '❌ Votre retrait a été rejeté - CAURIS DEGUE Callbreak';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.transaction-rejected',
            with: [
                'type' => $this->type,
                'caurisAmount' => $this->caurisAmount,
                'fcfaAmount' => $this->fcfaAmount,
                'pseudo' => $this->pseudo,
                'notes' => $this->notes,
            ],
        );
    }
}

