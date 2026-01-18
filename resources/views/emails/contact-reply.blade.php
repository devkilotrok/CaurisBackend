<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réponse à votre message</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            margin: -30px -30px 20px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
        }
        .original-message {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .original-message h3 {
            margin-top: 0;
            color: #667eea;
            font-size: 14px;
            text-transform: uppercase;
        }
        .reply-message {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            white-space: pre-wrap;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #666;
        }
        .signature {
            margin-top: 20px;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CAURIS DEGUE Callbreak</h1>
        </div>
        
        <div class="greeting">
            <p>Bonjour <strong>{{ $clientName }}</strong>,</p>
        </div>

        <p>Nous vous remercions d'avoir contacté notre équipe. Voici notre réponse à votre message :</p>

        <div class="original-message">
            <h3>Votre message original :</h3>
            <p>{{ $originalMessage }}</p>
        </div>

        <div class="reply-message">
            <p>{{ $replyMessage }}</p>
        </div>

        <div class="signature">
            <p>Cordialement,<br>
            <strong>{{ $managerName }}</strong><br>
            Service Client - CAURIS DEGUE Callbreak</p>
        </div>

        <div class="footer">
            <p>Cet email a été envoyé le {{ $date }} en réponse à votre message.</p>
            <p>Si vous avez d'autres questions, n'hésitez pas à nous contacter à nouveau.</p>
        </div>
    </div>
</body>
</html>

