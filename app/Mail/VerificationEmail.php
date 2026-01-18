<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de vérification avec code à 6 chiffres
 * 
 * Utilisé pour :
 * - Confirmer l'inscription d'un nouvel utilisateur
 * - Activer le compte après vérification
 * 
 * Le code est valide pendant 24 heures
 */
class VerificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $userName;
    public $expiresIn; // En heures
    public $firstName;

    /**
     * Create a new message instance.
     */
    public function __construct($code, $userName, $expiresIn = 24, $firstName = null)
    {
        $this->code = $code;
        $this->userName = $userName;
        $this->expiresIn = $expiresIn;
        $this->firstName = $firstName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vérification de votre compte Cauris',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification',
            with: [
                'code' => $this->code,
                'userName' => $this->userName,
                'firstName' => $this->firstName,
                'expiresIn' => $this->expiresIn,
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
