<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Obtenir ou créer une conversation active pour l'utilisateur
     * Toujours créer une nouvelle conversation (les anciennes sont fermées)
     */
    public function getOrCreateConversation(Request $request)
    {
        try {
            $user = $request->user();

            // Toujours créer une nouvelle conversation
            // Les anciennes conversations restent en historique mais ne sont plus actives
            $conversation = ChatConversation::create([
                'user_id' => $user->user_id,
                'status' => 'active',
                'assistant_type' => 'ai',
            ]);
            
            // Créer un message de bienvenue pour les nouvelles conversations (avec le pseudo)
            $userPseudo = $user->pseudo ?? null;
            $pseudo = $userPseudo ? " $userPseudo" : "";
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'ai',
                'sender_id' => null,
                'message' => "Bonjour$pseudo ! 👋 Je suis votre assistant virtuel pour CAURIS DEGUE Callbreak. " .
                    "Je suis là pour répondre à toutes vos questions sur le jeu, les règles, les paiements, les salons, et bien plus encore. " .
                    "N'hésitez pas à me poser vos questions, je ferai de mon mieux pour vous aider ! 😊",
            ]);

            // Charger les messages (juste le message de bienvenue pour une nouvelle conversation)
            $messages = $conversation->messages()->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation' => [
                        'id' => $conversation->id,
                        'status' => $conversation->status,
                        'assistant_type' => $conversation->assistant_type,
                        'assigned_manager' => $conversation->manager ? [
                            'id' => $conversation->manager->user_id,
                            'pseudo' => $conversation->manager->pseudo,
                        ] : null,
                    ],
                    'messages' => $messages->map(function ($message) {
                        return [
                            'id' => $message->id,
                            'sender_type' => $message->sender_type,
                            'message' => $message->message,
                            'created_at' => $message->created_at->toISOString(),
                        ];
                    }),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer un message dans la conversation
     */
    public function sendMessage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'conversation_id' => 'required|integer|exists:chat_conversations,id',
                'message' => 'required|string|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $conversation = ChatConversation::findOrFail($request->conversation_id);

            // Vérifier que la conversation appartient à l'utilisateur
            if ($conversation->user_id != $user->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé'
                ], 403);
            }

            // Créer le message de l'utilisateur
            $userMessage = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'user',
                'sender_id' => $user->user_id,
                'message' => $request->message,
            ]);

            // Mettre à jour la date du dernier message
            $conversation->update(['last_message_at' => now()]);

            // Si l'assistant est l'IA, générer une réponse
            if ($conversation->assistant_type === 'ai') {
                // Vérifier si l'utilisateur demande à parler avec un manager
                $messageLower = strtolower(trim($request->message));
                $managerKeywords = [
                    'manager', 'gérant', 'responsable', 'admin', 'administrateur', 
                    'support', 'aide humaine', 'personne', 'humain', 
                    'discuter avec', 'parler avec', 'contacter', 'joindre',
                    'j\'aimerais', 'je veux', 'je souhaite', 'besoin de parler'
                ];
                $wantsManager = false;
                
                // Vérifier les mots-clés
                foreach ($managerKeywords as $keyword) {
                    if (strpos($messageLower, $keyword) !== false) {
                        // Vérifier que ce n'est pas juste une question générale
                        $contextKeywords = ['règle', 'paiement', 'salon', 'cauris', 'solde', 'comment'];
                        $hasContext = false;
                        foreach ($contextKeywords as $ctx) {
                            if (strpos($messageLower, $ctx) !== false) {
                                $hasContext = true;
                                break;
                            }
                        }
                        // Si le message contient un mot-clé manager ET ne contient pas de contexte général, c'est une demande de manager
                        if (!$hasContext || strpos($messageLower, 'manager') !== false || 
                            strpos($messageLower, 'discuter') !== false || 
                            strpos($messageLower, 'parler') !== false) {
                            $wantsManager = true;
                            break;
                        }
                    }
                }
                
                if ($wantsManager) {
                    // Changer le statut pour attendre un manager
                    $conversation->update([
                        'status' => 'waiting_manager',
                        'assistant_type' => 'manager',
                    ]);
                    
                    // Récupérer les informations de l'utilisateur
                    $user = $request->user();
                    $userPseudo = $user->pseudo ?? 'Utilisateur';
                    $userEmail = $user->email ?? 'Non renseigné';
                    
                    // Créer un message personnalisé
                    $managerMessage = "Parfait ! J'ai bien noté votre demande de parler avec un manager. " .
                        "Un membre de notre équipe vous contactera bientôt. " .
                        "En attendant, voici les informations transmises :\n\n" .
                        "👤 Pseudo : $userPseudo\n" .
                        "📧 Email : $userEmail\n\n" .
                        "⚠️ Important : Si cet email n'est pas correct ou si vous souhaitez utiliser un autre email, " .
                        "veuillez le modifier dans votre profil (section Paramètres) avant que le manager ne vous réponde. " .
                        "Cela nous permet de vérifier votre identité et de sécuriser les communications concernant votre compte.\n\n" .
                        "Un manager vous répondra dans les plus brefs délais. " .
                        "Merci de votre patience ! 😊";
                    
                    $aiMessage = ChatMessage::create([
                        'conversation_id' => $conversation->id,
                        'sender_type' => 'ai',
                        'sender_id' => null,
                        'message' => $managerMessage,
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'user_message' => [
                                'id' => $userMessage->id,
                                'sender_type' => $userMessage->sender_type,
                                'message' => $userMessage->message,
                                'created_at' => $userMessage->created_at->toISOString(),
                            ],
                            'ai_message' => [
                                'id' => $aiMessage->id,
                                'sender_type' => $aiMessage->sender_type,
                                'message' => $aiMessage->message,
                                'created_at' => $aiMessage->created_at->toISOString(),
                            ],
                            'conversation_updated' => [
                                'status' => 'waiting_manager',
                                'assistant_type' => 'manager',
                            ],
                        ],
                    ], 200);
                }
                
                // Générer une réponse normale de l'IA (avec le pseudo de l'utilisateur)
                $user = $request->user();
                $userPseudo = $user->pseudo ?? null;
                $aiResponse = $this->generateAIResponse($request->message, $conversation, $userPseudo);
                
                // Créer le message de l'IA
                $aiMessage = ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => 'ai',
                    'sender_id' => null,
                    'message' => $aiResponse,
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'user_message' => [
                            'id' => $userMessage->id,
                            'sender_type' => $userMessage->sender_type,
                            'message' => $userMessage->message,
                            'created_at' => $userMessage->created_at->toISOString(),
                        ],
                        'ai_message' => [
                            'id' => $aiMessage->id,
                            'sender_type' => $aiMessage->sender_type,
                            'message' => $aiMessage->message,
                            'created_at' => $aiMessage->created_at->toISOString(),
                        ],
                    ],
                ], 200);
            } else {
                // Si c'est un manager, vérifier si c'est un remerciement pour répondre quand même
                $messageLower = strtolower(trim($request->message));
                $isThankYou = strpos($messageLower, 'merci') !== false || 
                             strpos($messageLower, 'thanks') !== false ||
                             strpos($messageLower, 'thank you') !== false ||
                             strpos($messageLower, 'thank') !== false;
                
                if ($isThankYou) {
                    // Répondre aux remerciements même en mode manager
                    $user = $request->user();
                    $userPseudo = $user->pseudo ?? null;
                    $pseudo = $userPseudo ? " $userPseudo" : "";
                    
                    $thankYouResponse = "Je vous en prie$pseudo ! 😊 " .
                        "C'était un plaisir de vous aider aujourd'hui. " .
                        "Un manager vous contactera bientôt. " .
                        "N'hésitez pas à revenir si vous avez d'autres questions sur CAURIS DEGUE Callbreak. " .
                        "Bonne partie et à bientôt ! 🎮";
                    
                    $aiMessage = ChatMessage::create([
                        'conversation_id' => $conversation->id,
                        'sender_type' => 'ai',
                        'sender_id' => null,
                        'message' => $thankYouResponse,
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'user_message' => [
                                'id' => $userMessage->id,
                                'sender_type' => $userMessage->sender_type,
                                'message' => $userMessage->message,
                                'created_at' => $userMessage->created_at->toISOString(),
                            ],
                            'ai_message' => [
                                'id' => $aiMessage->id,
                                'sender_type' => $aiMessage->sender_type,
                                'message' => $aiMessage->message,
                                'created_at' => $aiMessage->created_at->toISOString(),
                            ],
                        ],
                    ], 200);
                }
                
                // Sinon, juste retourner le message de l'utilisateur
                return response()->json([
                    'success' => true,
                    'data' => [
                        'user_message' => [
                            'id' => $userMessage->id,
                            'sender_type' => $userMessage->sender_type,
                            'message' => $userMessage->message,
                            'created_at' => $userMessage->created_at->toISOString(),
                        ],
                    ],
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error('Erreur ChatController::sendMessage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer une réponse de l'IA basée sur les règles et fonctionnalités de l'application
     */
    private function generateAIResponse(string $userMessage, ChatConversation $conversation, ?string $userPseudo = null): string
    {
        // Contexte de l'application pour l'IA
        $appContext = $this->getApplicationContext();
        
        // Historique des messages récents (derniers 10 messages)
        $recentMessages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(function ($msg) {
                $role = $msg->sender_type === 'user' ? 'user' : 'assistant';
                return [
                    'role' => $role,
                    'content' => $msg->message,
                ];
            })
            ->toArray();

        // Construire le prompt pour l'IA
        $pseudoInfo = $userPseudo ? "\n\nINFORMATION UTILISATEUR:\n- Le pseudo de l'utilisateur est : $userPseudo\n- Utilise son pseudo dans tes réponses quand c'est approprié (salutations, remerciements, etc.)\n- Exemple : 'Bonjour $userPseudo !' au lieu de juste 'Bonjour !'\n" : "";
        
        $systemPrompt = "Tu es un assistant virtuel intelligent et amical pour l'application CAURIS DEGUE Callbreak, un jeu de cartes en ligne. " .
            "Ton rôle est d'aider les utilisateurs avec leurs questions sur le jeu, les règles, les fonctionnalités, " .
            "les paiements, et tout autre aspect de l'application.\n\n" .
            "CONTEXTE DE L'APPLICATION:\n" . $appContext . $pseudoInfo . "\n\n" .
            "Instructions importantes:\n" .
            "- Réponds TOUJOURS en français de manière amicale, professionnelle et naturelle\n" .
            "- Utilise le pseudo de l'utilisateur dans tes salutations et quand c'est approprié (ex: 'Bonjour [pseudo] !')\n" .
            "- Distingue bien une simple salutation ('Bonjour') d'une question sur ton état ('Comment allez-vous ?')\n" .
            "- Pour 'Bonjour' seul : réponds par une salutation simple avec le pseudo, sans mentionner ton état\n" .
            "- Pour 'Comment allez-vous ?' : réponds que tu vas bien, puis propose ton aide\n" .
            "- Analyse le contexte de la conversation précédente pour donner des réponses pertinentes\n" .
            "- Ne répète JAMAIS le même message générique. Adapte ta réponse à la question spécifique de l'utilisateur\n" .
            "- Si l'utilisateur demande des détails supplémentaires, fournis des informations plus approfondies\n" .
            "- Si l'utilisateur demande à parler avec un manager, rassure-le qu'un manager répondra bientôt et affiche son pseudo et email\n" .
            "- Si tu ne connais pas la réponse exacte, dis-le honnêtement mais propose des alternatives utiles\n" .
            "- Pour les questions complexes ou les problèmes techniques, propose de transférer vers un manager humain\n" .
            "- Sois concis mais complet dans tes réponses\n" .
            "- Utilise des emojis avec modération (1-2 par message maximum) pour rendre les réponses plus amicales\n" .
            "- Varie tes formulations pour éviter la répétition\n" .
            "- Si l'utilisateur dit 'merci', 'ok merci', ou remercie, réponds TOUJOURS de manière professionnelle et chaleureuse\n" .
            "- Pour les remerciements, montre que c'était un plaisir de l'aider et invite-le à revenir si besoin\n" .
            "- Exemple de réponse à 'merci' : 'Je vous en prie [pseudo] ! 😊 C'était un plaisir de vous aider. N'hésitez pas à revenir si vous avez d'autres questions. Bonne partie ! 🎮'";

        // Si OpenAI est configuré, utiliser leur API
        $openaiKey = env('OPENAI_API_KEY');
        if ($openaiKey) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $openaiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => array_merge(
                        [['role' => 'system', 'content' => $systemPrompt]],
                        $recentMessages,
                        [['role' => 'user', 'content' => $userMessage]]
                    ),
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['choices'][0]['message']['content'] ?? 'Désolé, je n\'ai pas pu générer de réponse.';
                }
            } catch (\Exception $e) {
                Log::error('Erreur OpenAI API: ' . $e->getMessage());
            }
        }

        // Fallback: Réponses basées sur des règles simples
        return $this->generateFallbackResponse($userMessage, $userPseudo);
    }

    /**
     * Obtenir le contexte de l'application pour l'IA
     */
    private function getApplicationContext(): string
    {
        return "
RÈGLES DU JEU CALLBREAK:
- Le jeu se joue avec 4 joueurs et un jeu de 52 cartes
- Chaque joueur reçoit 13 cartes
- Les joueurs font des annonces (de 2 à 13) avant de commencer
- Si le total des annonces est strictement inférieur à 10, chaque joueur reçoit automatiquement +1 à son annonce
- Le joueur qui a fait l'annonce la plus élevée commence
- Les atouts sont les piques (S)
- Si un joueur n'a aucun pique, la manche est redistribuée
- Le but est de gagner exactement le nombre de plis annoncés
- Les gains sont calculés en fonction des annonces réussies

FONCTIONNALITÉS DE L'APPLICATION:
- Création de salons de jeu avec mise minimale
- Rejoindre des salons avec code ou automatiquement
- Mode bot (jouer avec des bots) et mode humain (jouer avec d'autres joueurs)
- Système de paiement avec Cauris (monnaie virtuelle)
- Dépôts via FedaPay (paiement mobile)
- Retraits vers compte bancaire
- Système d'amis et invitations
- Tableau des scores et statistiques
- Profil utilisateur avec avatar et préférences

PAIEMENTS:
- Les dépôts se font via FedaPay (mobile money)
- Les retraits nécessitent une validation par un administrateur
- Les transactions sont en FCFA et converties en Cauris
- Le solde minimum requis pour rejoindre un salon dépend de la mise minimale

SALONS:
- Chaque salon a un code unique
- Les salons peuvent être créés avec une mise minimale
- En mode humain, 4 joueurs doivent être présents avant de commencer
- Les joueurs sont débités de la mise minimale en rejoignant un salon humain
";
    }

    /**
     * Générer une réponse de fallback si l'API IA n'est pas disponible
     */
    private function generateFallbackResponse(string $userMessage, ?string $userPseudo = null): string
    {
        $message = strtolower(trim($userMessage));
        $pseudo = $userPseudo ? " $userPseudo" : "";

        // PRIORITÉ 1: Détecter les questions de politesse et salutations (AVANT tout le reste)
        // Détecter les questions sur l'état/la santé (comment allez-vous, ça va, etc.)
        // IMPORTANT: Distinguer "comment allez-vous" de "bonjour"
        if (strpos($message, 'comment allez') !== false || 
            strpos($message, 'comment vas') !== false ||
            strpos($message, 'comment tu vas') !== false ||
            strpos($message, 'comment vous portez') !== false ||
            strpos($message, 'comment vous sentez') !== false ||
            (strpos($message, 'ça va') !== false && strlen($message) <= 15 && strpos($message, 'bonjour') === false) ||
            (strpos($message, 'ca va') !== false && strlen($message) <= 15 && strpos($message, 'bonjour') === false)) {
            return "Je vais très bien, merci de demander$pseudo ! 😊 " .
                "Je suis là pour vous aider avec CAURIS DEGUE Callbreak. " .
                "Avez-vous une question sur le jeu, les paiements, les salons ou autre chose ?";
        }

        // Détecter les salutations simples (sans question sur l'état)
        // Ne pas inclure "comment allez" dans cette détection
        if ((strpos($message, 'bonjour') !== false && strpos($message, 'comment allez') === false) || 
            (strpos($message, 'salut') !== false && strpos($message, 'comment') === false) ||
            strpos($message, 'bonsoir') !== false ||
            (strpos($message, 'hello') !== false && strpos($message, 'how are') === false) ||
            (strpos($message, 'hi') !== false && strpos($message, 'how are') === false) ||
            strpos($message, 'bonne journée') !== false ||
            strpos($message, 'bonne soirée') !== false) {
            $greeting = strpos($message, 'bonsoir') !== false ? "Bonsoir" : "Bonjour";
            return "$greeting$pseudo ! 👋 " .
                "Je suis votre assistant virtuel pour CAURIS DEGUE Callbreak. " .
                "Comment puis-je vous aider aujourd'hui ?";
        }

        // PRIORITÉ 1.5: Détecter les remerciements (AVANT les réponses courtes)
        // Détecter les remerciements seuls ou combinés avec "ok"
        if (strpos($message, 'merci') !== false || 
            strpos($message, 'thanks') !== false ||
            strpos($message, 'thank you') !== false ||
            strpos($message, 'thank') !== false) {
            
            // Si c'est juste un remerciement simple ou combiné avec "ok"
            if (strlen($message) <= 15 || 
                (strpos($message, 'ok') !== false && strlen($message) <= 20)) {
                return "Je vous en prie$pseudo ! 😊 " .
                    "C'était un plaisir de vous aider aujourd'hui. " .
                    "N'hésitez pas à revenir si vous avez d'autres questions sur CAURIS DEGUE Callbreak. " .
                    "Bonne partie et à bientôt ! 🎮";
            }
            
            // Si le remerciement est dans une phrase plus longue, répondre quand même
            return "De rien$pseudo ! 😊 " .
                "C'est toujours un plaisir de vous assister. " .
                "Si vous avez d'autres questions, je suis là pour vous aider !";
        }

        // Détecter les réponses courtes (ok, d'accord, etc.) - SEULEMENT si pas de remerciement
        if ((strpos($message, 'ok') !== false || 
             strpos($message, 'd\'accord') !== false ||
             strpos($message, 'daccord') !== false ||
             strpos($message, 'parfait') !== false) && 
             strlen($message) <= 10 &&
             strpos($message, 'merci') === false) {
            return "Parfait ! 😊 Y a-t-il autre chose sur lequel je peux vous aider ?";
        }

        // PRIORITÉ 2: Détecter les questions sur les règles du jeu
        if (strpos($message, 'règle') !== false || 
            strpos($message, 'comment jouer') !== false || 
            strpos($message, 'comment gagner') !== false ||
            strpos($message, 'annonce') !== false ||
            strpos($message, 'plis') !== false ||
            strpos($message, 'pique') !== false ||
            strpos($message, 'atout') !== false) {
            
            if (strpos($message, 'détail') !== false || strpos($message, 'plus') !== false || strpos($message, 'explication') !== false) {
                return "Voici les détails sur les règles du jeu Callbreak :\n\n" .
                    "🎴 **Distribution** : Chaque joueur reçoit 13 cartes parmi 52.\n\n" .
                    "📢 **Annonces** : Avant de commencer, chaque joueur annonce combien de plis il pense gagner (entre 2 et 13). " .
                    "Si le total de toutes les annonces est strictement inférieur à 10, chaque joueur reçoit automatiquement +1 à son annonce.\n\n" .
                    "♠️ **Atouts** : Les piques (S) sont les atouts. Si un joueur n'a aucun pique, la manche est redistribuée automatiquement.\n\n" .
                    "🎯 **Objectif** : Gagner exactement le nombre de plis annoncés. Si vous gagnez plus ou moins, vous perdez des points.\n\n" .
                    "💰 **Gains** : Les gains sont calculés en fonction de vos annonces réussies et de la mise du salon.\n\n" .
                    "Souhaitez-vous des précisions sur un point particulier ?";
            }
            
            return "Les règles du jeu Callbreak sont disponibles dans la section 'Règles' de l'application. " .
                "En résumé, chaque joueur fait une annonce (de 2 à 13), puis essaie de gagner exactement ce nombre de plis. " .
                "Les piques sont les atouts. Si le total des annonces est strictement inférieur à 10, chaque joueur reçoit +1 automatiquement. " .
                "Souhaitez-vous plus de détails sur un aspect spécifique ?";
        }

        // Détecter les questions sur le solde et les paiements
        if (strpos($message, 'solde') !== false || 
            strpos($message, 'cauris') !== false || 
            strpos($message, 'argent') !== false ||
            strpos($message, 'balance') !== false ||
            strpos($message, 'combien j\'ai') !== false) {
            return "Votre solde en Cauris s'affiche dans votre profil en haut de l'écran d'accueil. " .
                "Pour recharger, allez dans la section 'Caisse' et choisissez 'Déposer'. " .
                "Vous pouvez déposer via FedaPay (mobile money). " .
                "Pour retirer, utilisez l'option 'Retirer' qui nécessite une validation par un administrateur. " .
                "Le taux de conversion est : 10 Cauris = 1 000 FCFA.";
        }

        // Détecter les questions sur les salons
        if (strpos($message, 'salon') !== false || 
            strpos($message, 'rejoindre') !== false ||
            strpos($message, 'créer un salon') !== false ||
            strpos($message, 'code') !== false) {
            return "Pour rejoindre un salon, vous pouvez soit entrer un code de salon, soit utiliser 'Rejoindre automatiquement' pour trouver un salon disponible. " .
                "En mode humain, vous devez attendre que 4 joueurs soient présents avant de commencer. " .
                "La mise minimale sera automatiquement débitée de votre solde lorsque vous rejoignez un salon en mode humain. " .
                "Pour créer votre propre salon, utilisez l'option 'Créer un salon' dans le menu principal.";
        }

        // Détecter les questions sur les dépôts
        if (strpos($message, 'dépôt') !== false || 
            strpos($message, 'recharger') !== false ||
            strpos($message, 'ajouter de l\'argent') !== false ||
            strpos($message, 'payer') !== false) {
            return "Pour recharger votre compte, allez dans 'Caisse' > 'Déposer'. " .
                "Entrez le nombre de Cauris souhaité, le montant en FCFA sera calculé automatiquement (10 Cauris = 1 000 FCFA). " .
                "Vous serez redirigé vers FedaPay pour finaliser le paiement avec votre mobile money. " .
                "Une fois le paiement validé, votre solde sera crédité automatiquement.";
        }

        // Détecter les questions sur les retraits
        if (strpos($message, 'retrait') !== false || 
            strpos($message, 'retirer') !== false ||
            strpos($message, 'retirer mes gains') !== false) {
            return "Pour retirer vos gains, allez dans 'Caisse' > 'Retirer'. " .
                "Entrez le montant en FCFA et vos coordonnées bancaires. " .
                "Votre demande sera soumise à validation par un administrateur. " .
                "Vous recevrez un email une fois la transaction validée ou rejetée. " .
                "Les retraits sont généralement traités sous 24-48 heures.";
        }

        // Détecter les questions sur les amis
        if (strpos($message, 'ami') !== false || 
            strpos($message, 'ami') !== false ||
            strpos($message, 'inviter') !== false) {
            return "Vous pouvez ajouter des amis dans la section 'Mes Amis'. " .
                "Recherchez un utilisateur par pseudo ou email, puis envoyez-lui une demande d'amitié. " .
                "Vous pouvez également inviter vos amis à rejoindre vos salons de jeu. " .
                "C'est plus amusant de jouer avec des amis ! 😊";
        }


        // Réponse par défaut plus personnalisée
        return "Je comprends votre question. Laissez-moi vous aider ! " .
            "Je peux vous renseigner sur :\n\n" .
            "• Les règles du jeu Callbreak\n" .
            "• Les paiements et transactions (dépôts/retraits)\n" .
            "• La création et le rejoignement de salons\n" .
            "• Le système d'amis et d'invitations\n" .
            "• Votre solde et vos statistiques\n\n" .
            "Pouvez-vous être plus précis sur ce que vous souhaitez savoir ? " .
            "Si votre question est complexe, je peux vous mettre en contact avec un manager humain. 😊";
    }

    /**
     * Demander un transfert vers un manager
     */
    public function requestManager(Request $request)
    {
        try {
            $user = $request->user();
            $conversation = ChatConversation::where('user_id', $user->user_id)
                ->whereIn('status', ['active', 'waiting_manager'])
                ->latest()
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune conversation active'
                ], 404);
            }

            // Changer le statut pour attendre un manager
            $conversation->update([
                'status' => 'waiting_manager',
                'assistant_type' => 'manager',
            ]);

            // Récupérer les informations de l'utilisateur
            $userPseudo = $user->pseudo ?? 'Utilisateur';
            $userEmail = $user->email ?? 'Non renseigné';

            // Créer un message système personnalisé
            $managerMessage = "Parfait ! J'ai bien noté votre demande de parler avec un manager. " .
                "Un membre de notre équipe vous contactera bientôt. " .
                "En attendant, voici les informations transmises :\n\n" .
                "👤 Pseudo : $userPseudo\n" .
                "📧 Email : $userEmail\n\n" .
                "⚠️ **Important** : Si cet email n'est pas correct ou si vous souhaitez utiliser un autre email, " .
                "veuillez le modifier dans votre profil (section Paramètres) avant que le manager ne vous réponde. " .
                "Cela nous permet de vérifier votre identité et de sécuriser les communications concernant votre compte.\n\n" .
                "Un manager vous répondra dans les plus brefs délais. " .
                "Merci de votre patience ! 😊";

            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'ai',
                'sender_id' => null,
                'message' => $managerMessage,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demande de transfert envoyée',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fermer une conversation (appelé quand l'utilisateur quitte le chat)
     */
    public function closeConversation(Request $request)
    {
        try {
            $user = $request->user();
            $conversationId = $request->input('conversation_id');

            if (!$conversationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de conversation requis'
                ], 422);
            }

            $conversation = ChatConversation::where('id', $conversationId)
                ->where('user_id', $user->user_id)
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation non trouvée'
                ], 404);
            }

            // Fermer la conversation
            $conversation->update([
                'status' => 'closed',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation fermée',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

}

