<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ENESA — Dostęp do portalu</title>
</head>
<body style="margin:0;padding:0;background:#F4F1EA;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F4F1EA;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);max-width:600px;">
  <tr><td style="background:#1A4D3A;padding:28px 40px;text-align:center;">
    <img src="https://enesa.pl/wp-content/uploads/2026/03/cropped-Logo2.png" width="56" height="56" style="display:block;margin:0 auto 8px;" alt="ENESA">
    <div style="color:#F5F0E8;font-size:20px;font-weight:700;">ENESA</div>
    <div style="color:rgba(245,240,232,0.6);font-size:12px;margin-top:4px;">Energy Audit & Solutions</div>
  </td></tr>
  <tr><td style="padding:40px;">
    <h1 style="font-size:22px;font-weight:700;color:#1A1A1A;margin:0 0 16px;">Zostałeś dodany do systemu ENESA</h1>
    <p style="font-size:15px;color:#444;line-height:1.7;margin:0 0 20px;">Witaj, <strong>{{ $user->name }}</strong>!<br>Zostałeś(aś) dodany(a) jako użytkownik firmy <strong>{{ $company->name }}</strong> w systemie ENESA. Poniżej znajdziesz dane dostępowe.</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#E8F5E9;border-left:4px solid #1A4D3A;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:20px;">
      <tr><td><p style="margin:0;font-size:14px;color:#1A4D3A;font-weight:700;">Twoje dane logowania:</p></td></tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:20px;">
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;border-bottom:1px solid #F0EDE6;width:40%;">Adres portalu</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;border-bottom:1px solid #F0EDE6;"><a href="{{ config('app.url') }}" style="color:#1A4D3A;">{{ config('app.url') }}</a></td></tr>
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;border-bottom:1px solid #F0EDE6;">Email</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;border-bottom:1px solid #F0EDE6;">{{ $user->email }}</td></tr>
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;border-bottom:1px solid #F0EDE6;">Hasło tymczasowe</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;border-bottom:1px solid #F0EDE6;">{{ $temporaryPassword }}</td></tr>
      <tr><td style="padding:10px 14px;font-size:14px;color:#888;">Firma</td><td style="padding:10px 14px;font-size:14px;color:#1A1A1A;font-weight:600;">{{ $company->name }}</td></tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#FFF3E0;border-left:4px solid #EF6C00;border-radius:0 8px 8px 0;padding:14px 18px;margin-bottom:24px;">
      <tr><td><p style="margin:0;font-size:13px;color:#BF360C;"><strong>Ważne:</strong> Zmień hasło tymczasowe po pierwszym zalogowaniu.</p></td></tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
      <a href="{{ route('login') }}" style="display:inline-block;padding:14px 32px;background:#1A4D3A;color:#F5F0E8;text-decoration:none;border-radius:8px;font-size:15px;font-weight:700;">Zaloguj się teraz →</a>
    </td></tr></table>
    <hr style="border:none;border-top:1px solid #F0EDE6;margin:28px 0;">
    <p style="font-size:13px;color:#888;margin:0;">Jeśli nie spodziewałeś(aś) się tego emaila, skontaktuj się: <a href="mailto:biuro@enesa.pl" style="color:#1A4D3A;">biuro@enesa.pl</a></p>
  </td></tr>
  <tr><td style="background:#F9F7F4;padding:20px 40px;text-align:center;">
    <p style="font-size:12px;color:#888;margin:0;line-height:1.8;"><strong>ENESA — Energy Audit & Solutions</strong><br>system@enesa.pl &nbsp;|&nbsp; +48 516 500 729 &nbsp;|&nbsp; <a href="https://enesa.pl" style="color:#1A4D3A;">www.enesa.pl</a><br>Ten email został wysłany automatycznie.</p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>