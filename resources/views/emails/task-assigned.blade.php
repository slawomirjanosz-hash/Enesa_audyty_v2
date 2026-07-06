<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: 'Lato', Arial, sans-serif; background:#F4F1EA; margin:0; padding:30px;">
    <div style="max-width:520px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden;">
        <div style="background:#1A4D3A; padding:20px 28px;">
            <span style="color:#F5F0E8; font-family:'Manrope',sans-serif; font-size:18px; font-weight:700;">
                Nowe zadanie w ENESA CRM
            </span>
        </div>
        <div style="padding:28px;">
            <h2 style="font-family:'Manrope',sans-serif; color:#1A1A1A; font-size:17px; margin:0 0 12px;">
                {{ $task->title }}
            </h2>
            @if($task->description)
                <p style="color:#555; font-size:14px; line-height:1.5;">{{ $task->description }}</p>
            @endif
            <table style="width:100%; font-size:13px; color:#333; margin-top:16px;">
                @if($task->company)
                <tr><td style="padding:6px 0; color:#888;">Firma:</td><td style="font-weight:600;">{{ $task->company->name }}</td></tr>
                @endif
                @if($task->due_date)
                <tr><td style="padding:6px 0; color:#888;">Termin:</td><td style="font-weight:600;">{{ $task->due_date->format('d.m.Y') }}</td></tr>
                @endif
                @if($task->priority)
                <tr><td style="padding:6px 0; color:#888;">Priorytet:</td><td style="font-weight:600;">{{ $task->priority }}</td></tr>
                @endif
                @if($task->createdBy)
                <tr><td style="padding:6px 0; color:#888;">Zlecił:</td><td style="font-weight:600;">{{ $task->createdBy->name }}</td></tr>
                @endif
            </table>
            <a href="{{ route('crm.index', ['tab' => 'tasks']) }}"
               style="display:inline-block; margin-top:20px; background:#1A4D3A; color:#F5F0E8; text-decoration:none; padding:10px 20px; border-radius:8px; font-family:'Manrope',sans-serif; font-size:13px; font-weight:700;">
                Zobacz zadanie w CRM
            </a>
        </div>
    </div>
</body>
</html>
