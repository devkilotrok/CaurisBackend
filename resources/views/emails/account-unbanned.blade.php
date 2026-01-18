<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte Réactivé</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #228B22 0%, #32CD32 100%); padding: 40px; text-align: center;">
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
                                Excellente nouvelle ! Votre compte <strong style="color: #4caf50;">CAURIS DEGUE Callbreak</strong> a été <strong style="color: #388e3c;">réactivé</strong> avec succès.
                            </p>

                            <div style="margin: 20px 0; padding: 20px; background-color: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 4px;">
                                <p style="margin: 0 0 10px 0; color: #2e7d32; font-size: 13px; font-weight: bold;">
                                    ✅ Compte Réactivé
                                </p>
                                <p style="margin: 0; color: #555; font-size: 13px; line-height: 1.6;">
                                    Vous pouvez maintenant vous connecter et profiter de toutes les fonctionnalités de la plateforme.
                                </p>
                            </div>

                            <p style="margin: 20px 0 0 0; color: #555; font-size: 14px; line-height: 1.6;">
                                Nous sommes ravis de vous revoir sur <strong>CAURIS DEGUE Callbreak</strong> ! Profitez bien de votre expérience de jeu.
                            </p>

                            <div style="margin: 30px 0 0 0; padding: 20px; background-color: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px;">
                                <p style="margin: 0; color: #1565c0; font-size: 13px;">
                                    🎮 <strong>Prêt à jouer ?</strong> Connectez-vous dès maintenant et rejoignez une partie !
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

