<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Validée</title>
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
                            <p style="margin: 20px 0 0 0; color: white; opacity: 0.9;">Notification de Transaction</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; color: #333; font-size: 16px;">
                                Salut <strong>{{ $pseudo }}</strong> ! 👋
                            </p>

                            <p style="margin: 0 0 20px 0; color: #555; font-size: 14px; line-height: 1.6;">
                                Votre transaction a été <strong style="color: #228B22;">validée avec succès</strong> !
                            </p>

                            <!-- Transaction Details -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f7ff; border-radius: 8px; padding: 20px; margin: 20px 0;">
                                <tr>
                                    <td style="color: #333; font-size: 14px; padding: 5px 0;">
                                        <strong>Type :</strong> {{ ucfirst($type) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #333; font-size: 14px; padding: 5px 0;">
                                        <strong>Montant :</strong> {{ $caurisAmount }} cauris ({{ number_format($fcfaAmount, 0, ',', ' ') }} FCFA)
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #333; font-size: 14px; padding: 5px 0;">
                                        <strong>Date :</strong> {{ date('d/m/Y à H:i') }}
                                    </td>
                                </tr>
                            </table>

                            @if($type === 'depot')
                            <p style="margin: 20px 0 0 0; color: #555; font-size: 14px; line-height: 1.6;">
                                Votre compte a été crédité de <strong>{{ $caurisAmount }} cauris</strong>. Vous pouvez maintenant profiter de toutes les fonctionnalités de <strong>CAURIS DEGUE Callbreak</strong> !
                            </p>
                            @else
                                <p style="margin: 20px 0 0 0; color: #555; font-size: 14px; line-height: 1.6;">
                                    Votre demande de retrait a été traitée avec succès. Les fonds seront transférés selon les modalités convenues.
                                </p>
                            @endif

                            <div style="margin: 30px 0 0 0; padding: 20px; background-color: #e8f5e9; border-left: 4px solid #228B22; border-radius: 4px;">
                                <p style="margin: 0; color: #2e7d32; font-size: 13px;">
                                    💡 <strong>Conseil :</strong> Conservez cet email comme reçu de transaction.
                                </p>
                            </div>

                            <p style="margin: 40px 0 20px 0; color: #555; font-size: 14px; line-height: 1.6;">
                                Merci de votre confiance et de choisir <strong>CAURIS DEGUE Callbreak</strong> pour vos transactions.
                            </p>

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

