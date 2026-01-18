<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de votre mot de passe</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #DC143C 0%, #FF6347 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
        }
        .message {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .code-container {
            text-align: center;
            margin: 30px 0;
        }
        .code {
            display: inline-block;
            background: linear-gradient(135deg, #DC143C 0%, #FF6347 100%);
            color: white;
            font-size: 42px;
            font-weight: bold;
            padding: 20px 40px;
            border-radius: 10px;
            letter-spacing: 8px;
            box-shadow: 0 4px 15px rgba(220, 20, 60, 0.3);
        }
        .expiry {
            text-align: center;
            color: #999;
            font-size: 14px;
            margin-top: 20px;
        }
        .footer {
            background-color: #f8f8f8;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #856404;
        }
        .important {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Cauris</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Réinitialisation de mot de passe</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Bonjour {{ $userName }} 👋
            </div>
            
            <div class="message">
                Vous avez demandé à réinitialiser votre mot de passe sur <strong>Cauris</strong>. Utilisez le code ci-dessous pour procéder à la réinitialisation.
            </div>
            
            <div class="code-container">
                <div class="code">{{ $code }}</div>
            </div>
            
            <div class="expiry">
                ⏰ Ce code expirera dans {{ $expiresIn }} heure(s)
            </div>
            
            <div class="important">
                ⚠️ Si vous n'avez pas demandé cette réinitialisation, ignorez cet email. Votre compte reste sécurisé.
            </div>
            
            <div class="warning">
                🛡️ Pour votre sécurité, ne partagez jamais ce code avec qui que ce soit. L'équipe Cauris ne vous demandera jamais votre code de réinitialisation par email ou téléphone.
            </div>
            
            <div class="message">
                Si vous rencontrez des problèmes, contactez notre support à support@cauris.com
            </div>
        </div>
        
        <div class="footer">
            <p><strong>L'équipe Cauris</strong></p>
            <p style="margin: 5px 0; color: #999;">© {{ date('Y') }} Cauris. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>

