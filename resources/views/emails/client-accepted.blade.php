<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ENESA — Konto zaakceptowane</title>
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
    <h1 style="font-size:22px;font-weight:700;color:#1A1A1A;margin:0 0 16px;">Twoje konto zostało zaakceptowane</h1>
    <p style="font-size:15px;color:#444;line-height:1.7;margin:0 0 16px;">Mamy dobrą wiadomość — konto firmowe w systemie ENESA zostało zaakceptowane przez nasz zespół. Możesz teraz zalogować się do portalu klienta.</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#E8F5E9;border-left:4px solid #1A4D3A;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:24px;">
      <tr><td>
        <p style="margin:0 0 6px;font-size:14px;color:#1A4D3A;font-weight:700;">Twoja firma: <span style="font-weight:400;">{{ $company->name }}</span></p>
        <p style="margin:0;font-size:14px;color:#1A4D3A;font-weight:700;">NIP: <span style="font-weight:400;">{{ $company->nip }}</span></p>
      </td></tr>
    </table>
    <p style="font-size:15px;color:#444;line-height:2;margin:0 0 24px;">Co możesz teraz zrobić:<br>
    • Przeglądać dostępne typy audytów<br>
    • Wysłać zapytanie o audyt energetyczny<br>
    • Śledzić postępy audytów<br>
    • Komunikować się z audytorem</p>
    <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
      <a href="{{ route('login') }}" style="display:inline-block;padding:14px 32px;background:#1A4D3A;color:#F5F0E8;text-decoration:none;border-radius:8px;font-size:15px;font-weight:700;">Zaloguj się do portalu →</a>
    </td></tr></table>
    <hr style="border:none;border-top:1px solid #F0EDE6;margin:28px 0;">
    <p style="font-size:13px;color:#888;margin:0;">Masz pytania? Napisz do nas: <a href="mailto:biuro@enesa.pl" style="color:#1A4D3A;">biuro@enesa.pl</a></p>
  </td></tr>
  <tr><td style="background:#F9F7F4;padding:20px 40px;text-align:center;">
    <p style="font-size:12px;color:#888;margin:0;line-height:1.8;"><strong>ENESA — Energy Audit & Solutions</strong><br>system@enesa.pl &nbsp;|&nbsp; +48 516 500 729 &nbsp;|&nbsp; <a href="https://enesa.pl" style="color:#1A4D3A;">www.enesa.pl</a><br>Ten email został wysłany automatycznie.</p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
