<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Mail\ContactEmail;

class ContactController extends Controller
{
    /**
     * Envoyer un message de contact
     */
    public function send(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|min:10|max:5000',
        ], [
            'name.required' => 'Le nom est requis',
            'name.min' => 'Le nom doit contenir au moins 2 caractères',
            'email.required' => 'L\'email est requis',
            'email.email' => 'L\'email n\'est pas valide',
            'message.required' => 'Le message est requis',
            'message.min' => 'Le message doit contenir au moins 10 caractères',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()->all()
            ], 400);
        }

        try {
            // Sauvegarder le message dans la base de données
            $messageId = DB::table('contact_messages')->insertGetId([
                'name' => $request->name,
                'email' => $request->email,
                'message' => $request->message,
                'ip_address' => $request->ip(),
                'status' => 'unread',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Récupérer l'adresse email de destination depuis la config ou .env
            $toEmail = env('MAIL_CONTACT_TO', config('mail.from.address'));
            
            // Envoyer l'email
            try {
                Mail::to($toEmail)->send(new ContactEmail(
                    $request->name,
                    $request->email,
                    $request->message,
                    $request->ip()
                ));
            } catch (\Exception $mailError) {
                \Log::error('Contact email failed but message saved', [
                    'error' => $mailError->getMessage(),
                    'message_id' => $messageId,
                ]);
                // On continue même si l'email échoue, le message est sauvegardé
            }

            // Log
            \Log::info('Contact form submitted', [
                'name' => $request->name,
                'email' => $request->email,
                'ip' => $request->ip(),
                'message_id' => $messageId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Contact email failed', [
                'error' => $e->getMessage(),
                'name' => $request->name,
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer plus tard.'
            ], 500);
        }
    }
}

