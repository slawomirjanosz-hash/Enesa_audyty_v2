<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENESA - Nowa rejestracja</title>
    <style>
        body { margin: 0; padding: 0; background: #F4F1EA; font-family: Arial, Helvetica, sans-serif; color: #1e1e1e; }
        .wrapper { width: 100%; background: #F4F1EA; padding: 32px 16px; }
        .container { width: 100%; max-width: 600px; margin: 0 auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        .header { background: #1A4D3A; padding: 24px 28px; text-align: center; }
        .header img { width: 150px; max-width: 100%; height: auto; display: inline-block; }
        .content { padding: 32px 30px; }
        h1 { margin: 0 0 12px; font-size: 24px; line-height: 1.25; color: #1A4D3A; }
        p { margin: 0 0 16px; font-size: 15px; line-height: 1.7; color: #3f4a44; }
        .box { background: #FAFAF6; border: 1px solid #E5E1D8; border-radius: 10px; padding: 16px; margin: 20px 0; }
        .btn { display: inline-block; background: #1A4D3A; color: #F5F0E8; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; }
        .footer { padding: 18px 28px 30px; font-size: 12px; color: #7a8a80; text-align: center; }
        @media only screen and (max-width: 640px) { .content { padding: 24px 18px; } .header { padding: 20px; } h1 { font-size: 22px; } }
    </style>
</head>
<body>
    <div class="wrapper">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td align="center">
                    <div class="container">
                        <div class="header">
                            <img src="https://enesa.pl/wp-content/uploads/2026/03/cropped-Logo2.png" alt="ENESA">
                        </div>
                        <div class="content">
                            <h1>Nowa rejestracja w systemie</h1>
                            <p>W systemie ENESA pojawiła się nowa rejestracja firmy wymagająca akceptacji.</p>
                            <div class="box">
                                <p style="margin-bottom:8px;"><strong>Firma:</strong> {{ $company->name }}</p>
                                <p style="margin-bottom:8px;"><strong>NIP:</strong> {{ $company->nip ?? 'Brak' }}</p>
                                <p style="margin-bottom:8px;"><strong>Osoba zgłaszająca:</strong> {{ $user->name }}</p>
                                <p style="margin-bottom:0;"><strong>Email:</strong> {{ $user->email }}</p>
                            </div>
                            <p>
                                <a href="{{ route('dashboard') }}" class="btn">Przejdź do dashboardu</a>
                            </p>
                        </div>
                        <div class="footer">© {{ date('Y') }} ENESA. System zarządzania audytami energetycznymi.</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
