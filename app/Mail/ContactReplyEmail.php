<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactReplyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $clientName;
    public $clientEmail;
    public $originalMessage;
    public $replyMessage;
    public $managerName;

    /**
     * Create a new message instance.
     */
    public function __construct($clientName, $clientEmail, $originalMessage, $replyMessage, $managerName)
    {
        $this->clientName = $clientName;
        $this->clientEmail = $clientEmail;
        $this->originalMessage = $originalMessage;
        $this->replyMessage = $replyMessage;
        $this->managerName = $managerName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Réponse à votre message - CAURIS DEGUE Callbreak',
            to: [$this->clientEmail],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-reply',
            with: [
                'clientName' => $this->clientName,
                'clientEmail' => $this->clientEmail,
                'originalMessage' => $this->originalMessage,
                'replyMessage' => $this->replyMessage,
                'managerName' => $this->managerName,
                'date' => now()->format('d/m/Y à H:i:s'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

