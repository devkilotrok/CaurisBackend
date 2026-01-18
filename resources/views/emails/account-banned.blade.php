<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte Suspendu</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); padding: 40px; text-align: center;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                                <div style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; font-size: 50px; color: white;">
                                    ♠
                                </div>
                                <h1 style="margin: 0; color: white; font-size: 28px;">CAURIS DEGUE<br><small style="font-size: 14px; display: block; margin-top: 5px; opacity: 0.9;">Callbreak</small></h1>
                            </div>
                            <p style="margin: 20px 0 0 0; color: white; opacity: 0.9;">Notification de Compte</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; color: #333; font-size: 16px;">
                                Salut <strong>{{ $pseudo }}</strong> ! 👋
                            </p>

                            <p style="margin: 0 0 20px 0; color: #555; font-size: 14px; line-height: 1.6;">
                                Nous vous informons que votre compte <strong style="color: #ff9800;">CAURIS DEGUE Callbreak</strong> a été <strong style="color: #d32f2f;">suspendu</strong> par notre équipe de modération.
                            </p>

                            <div style="margin: 20px 0; padding: 20px; background-color: #fff3e0; border-left: 4px solid #ff9800; border-radius: 4px;">
                                <p style="margin: 0 0 10px 0; color: #e65100; font-size: 13px; font-weight: bold;">
                                    ⚠️ Compte Suspendu
                                </p>
                                <p style="margin: 0; color: #555; font-size: 13px; line-height: 1.6;">
                                    Votre compte est temporairement désactivé. Vous ne pouvez plus vous connecter ni utiliser les fonctionnalités de la plateforme.
                                </p>
                            </div>

                            @if($reason)
                            <div style="margin: 20px 0; padding: 15px; background-color: #ffebee; border-left: 4px solid #d32f2f; border-radius: 4px;">
                                <p style="margin: 0 0 10px 0; color: #d32f2f; font-size: 13px; font-weight: bold;">
                                    Raison de la suspension :
                                </p>
                                <p style="margin: 0; color: #555; font-size: 13px; line-height: 1.6;">
                                    {{ $reason }}
                                </p>
                            </div>
                            @endif

                            <p style="margin: 20px 0 0 0; color: #555; font-size: 14px; line-height: 1.6;">
                                Si vous pensez qu'il s'agit d'une erreur ou si vous souhaitez contester cette décision, n'hésitez pas à nous contacter. L'équipe <strong>CAURIS DEGUE Callbreak</strong> est à votre disposition pour vous aider.
                            </p>

                            <div style="margin: 30px 0 0 0; padding: 20px; background-color: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px;">
                                <p style="margin: 0; color: #1565c0; font-size: 13px;">
                                    💡 <strong>Besoin d'aide ?</strong> Contactez-nous pour plus d'informations ou pour faire appel de cette décision.
                                </p>
                            </div>

                            <p style="margin: 10px 0 0 0; color: #333; font-size: 14px;">
                                Cordialement,<br>
                                <strong>L'équipe CAURIS DEGUE Callbreak</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f5f5f5; padding: 30px 40px; text-align: center;">
                            <p style="margin: 0; color: #999; font-size: 12px;">
                                © {{ date('Y') }} CAURIS DEGUE Callbreak. Tous droits réservés.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

