<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau message de contact</title>
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
        }
        .header {
            background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .content {
            padding: 30px 20px;
            background: #f9f9f9;
        }
        .field {
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #6366F1;
        }
        .label {
            font-weight: 700;
            color: #6366F1;
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            text-transform: uppercase;
        }
        .value {
            color: #1E293B;
            font-size: 16px;
        }
        .value a {
            color: #6366F1;
            text-decoration: none;
        }
        .message-box {
            background: white;
            padding: 20px;
            border-left: 4px solid #6366F1;
            margin-top: 10px;
            border-radius: 8px;
            white-space: pre-wrap;
            color: #1E293B;
            font-size: 16px;
            line-height: 1.6;
        }
        .footer {
            background: #1E293B;
            color: #94A3B8;
            padding: 20px;
            text-align: center;
            font-size: 12px;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📧 Nouveau message de contact</h2>
            <p>CAURIS DEGUE Callbreak</p>
        </div>
        <div class="content">
            <div class="field">
                <span class="label">👤 Nom complet</span>
                <div class="value">{{ $name }}</div>
            </div>
            <div class="field">
                <span class="label">📧 Adresse email</span>
                <div class="value">
                    <a href="mailto:{{ $email }}">{{ $email }}</a>
                </div>
            </div>
            <div class="field">
                <span class="label">💬 Message</span>
                <div class="message-box">{{ $message }}</div>
            </div>
        </div>
        <div class="footer">
            <p>📅 Date: {{ $date }}</p>
            <p>🌐 IP: {{ $ipAddress }}</p>
        </div>
    </div>
</body>
</html>

