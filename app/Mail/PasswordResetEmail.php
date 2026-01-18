<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de réinitialisation de mot de passe avec code
 * 
 * Utilisé pour :
 * - Envoyer un code de réinitialisation à l'utilisateur
 * - Permettre de réinitialiser le mot de passe en toute sécurité
 * 
 * Le code est valide pendant 1 heure
 */
class PasswordResetEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $userName;
    public $expiresIn; // En heures

    /**
     * Create a new message instance.
     */
    public function __construct($code, $userName, $expiresIn = 1)
    {
        $this->code = $code;
        $this->userName = $userName;
        $this->expiresIn = $expiresIn;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Réinitialisation de votre mot de passe Cauris',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'code' => $this->code,
                'userName' => $this->userName,
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
