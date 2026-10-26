<aside role="status" style="position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#fff4cc;color:#654500;border-top:1px solid #dfc96b;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;font:13px/1.4 sans-serif;box-shadow:0 -2px 8px #0001;">
    <span><strong>Podgląd serwisowy: {{ $previewUser->name }}</strong> · {{ $previewUser->email }}<br><small>Tylko do odczytu. Widzisz uprawnienia tego użytkownika. Limit: 30 minut.</small></span>
    <form method="POST" action="{{ route('user-preview.stop') }}" style="margin:0;">@csrf<button type="submit" style="background:#654500;color:white;padding:9px 14px;border:0;border-radius:6px;cursor:pointer;">Wróć do superadministratora</button></form>
</aside>
<style>body{padding-bottom:85px!important}</style>
