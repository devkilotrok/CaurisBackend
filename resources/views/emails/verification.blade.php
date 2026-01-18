<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de votre compte Cauris</title>
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
            background: linear-gradient(135deg, #228B22 0%, #32CD32 100%);
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
            background: linear-gradient(135deg, #228B22 0%, #32CD32 100%);
            color: white;
            font-size: 42px;
            font-weight: bold;
            padding: 20px 40px;
            border-radius: 10px;
            letter-spacing: 8px;
            box-shadow: 0 4px 15px rgba(34, 139, 34, 0.3);
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                <div style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; font-size: 50px; color: white;">
                    ♠
                </div>
                <h1 style="margin: 0;">CAURIS DEGUE<br><small style="font-size: 14px; display: block; margin-top: 5px;">Callbreak</small></h1>
            </div>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Vérification de votre compte</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Bonjour {{ $firstName ?? $userName }} 👋
            </div>
            
            <div class="message">
                Merci de vous être inscrit sur <strong>CAURIS DEGUE Callbreak</strong> ! Pour finaliser votre inscription et activer votre compte, veuillez utiliser le code de vérification ci-dessous.
            </div>
            
            <div class="code-container">
                <div class="code">{{ $code }}</div>
            </div>
            
            <div class="expiry">
                ⏰ Ce code expirera dans {{ $expiresIn }} heures
            </div>
            
            <div class="warning">
                ⚠️ Pour votre sécurité, ne partagez jamais ce code avec qui que ce soit. L'équipe CAURIS DEGUE Callbreak ne vous demandera jamais votre code de vérification.
            </div>
            
            <div class="message">
                Si vous n'avez pas créé de compte sur CAURIS DEGUE Callbreak, vous pouvez ignorer cet email en toute sécurité.
            </div>
        </div>
        
        <div class="footer">
            <p><strong>L'équipe CAURIS DEGUE Callbreak</strong></p>
            <p style="margin: 5px 0; color: #999;">© {{ date('Y') }} CAURIS DEGUE Callbreak. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>

