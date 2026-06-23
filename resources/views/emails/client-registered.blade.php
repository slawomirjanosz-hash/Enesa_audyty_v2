<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ENESA — Nowa rejestracja</title>
</head>
<body style="margin:0;padding:0;background:#F4F1EA;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F4F1EA;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);max-width:600px;">
  <tr><td style="background:#0D3B12;padding:28px 40px;text-align:center;">
    <img src="https://enesa.pl/wp-content/uploads/2026/03/cropped-Logo2.png" width="56" height="56" style="display:block;margin:0 auto 8px;" alt="ENESA">
    <div style="color:#F5F0E8;font-size:20px;font-weight:700;">ENESA — Panel Admina</div>
    <div style="color:rgba(245,240,232,0.6);font-size:12px;margin-top:4px;">Powiadomienie systemowe</div>
  </td></tr>
  <tr><td style="padding:40px;">
    <h1 style="font-size:22px;font-weight:700;color:#1A1A1A;margin:0 0 16px;">Nowa rejestracja firmy</h1>
    <p style="font-size:15px;color:#444;line-height:1.7;margin:0 0 20px;">W systemie ENESA pojawiła się nowa rejestracja wymagająca Twojej akceptacji.</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#E8F5E9;border-left:4px solid #1A4D3A;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:20px;">
      <tr><td><p style="margin:0;font-size:14px;color:#1A4D3A;font-weight:700;">Nowa firma do akceptacji:</p></td></tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:24px;">
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;border-bottom:1px solid #F0EDE6;width:40%;">Nazwa firmy</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;border-bottom:1px solid #F0EDE6;">{{ $company->name }}</td></tr>
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;border-bottom:1px solid #F0EDE6;">NIP</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;border-bottom:1px solid #F0EDE6;">{{ $company->nip }}</td></tr>
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;border-bottom:1px solid #F0EDE6;">Adres</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;border-bottom:1px solid #F0EDE6;">{{ $company->address ?? 'Nie podano' }}, {{ $company->city ?? '' }}</td></tr>
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;border-bottom:1px solid #F0EDE6;">Kontakt</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;border-bottom:1px solid #F0EDE6;">{{ $user->name }}</td></tr>
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;border-bottom:1px solid #F0EDE6;">Email</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;border-bottom:1px solid #F0EDE6;"><a href="mailto:{{ $user->email }}" style="color:#1A4D3A;">{{ $user->email }}</a></td></tr>
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;border-bottom:1px solid #F0EDE6;">Telefon</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;border-bottom:1px solid #F0EDE6;">{{ $user->phone ?? 'Nie podano' }}</td></tr>
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;">Data rejestracji</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;">{{ $company->created_at->format('d.m.Y, H:i') }}</td></tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
      <a href="{{ config('app.url') }}/dashboard" style="display:inline-block;padding:14px 32px;background:#1A4D3A;color:#F5F0E8;text-decoration:none;border-radius:8px;font-size:15px;font-weight:700;">Przejdź do dashboardu →</a>
    </td></tr></table>
    <hr style="border:none;border-top:1px solid #F0EDE6;margin:28px 0;">
    <p style="font-size:13px;color:#888;margin:0;">Zaloguj się i zaakceptuj lub odrzuć rejestrację w karcie klienta.</p>
  </td></tr>
  <tr><td style="background:#F9F7F4;padding:20px 40px;text-align:center;">
    <p style="font-size:12px;color:#888;margin:0;line-height:1.8;"><strong>ENESA System</strong> — powiadomienie automatyczne<br>system@enesa.pl &nbsp;|&nbsp; <a href="https://app.enesa.pl" style="color:#1A4D3A;">app.enesa.pl</a></p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>