<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: 'Lato', Arial, sans-serif; background:#F4F1EA; margin:0; padding:30px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden;">
        <div style="background:#B91C1C; padding:20px 28px;">
            <span style="color:#fff; font-family:'Manrope',sans-serif; font-size:18px; font-weight:700;">
                ⚠ Zaległe zadania ({{ $tasks->count() }})
            </span>
        </div>
        <div style="padding:28px;">
            <p style="color:#555; font-size:14px; margin:0 0 18px;">
                Poniższe zadania mają termin wykonania w przeszłości i wciąż nie są oznaczone jako zakończone:
            </p>
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="background:#FAFAF6;">
                        <th style="text-align:left; padding:8px 10px; color:#888; font-family:'Manrope',sans-serif; font-size:11px; text-transform:uppercase;">Zadanie</th>
                        <th style="text-align:left; padding:8px 10px; color:#888; font-family:'Manrope',sans-serif; font-size:11px; text-transform:uppercase;">Termin</th>
                        <th style="text-align:left; padding:8px 10px; color:#888; font-family:'Manrope',sans-serif; font-size:11px; text-transform:uppercase;">Firma</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $task)
                    <tr style="border-bottom:1px solid #F0EDE6;">
                        <td style="padding:10px; font-weight:600; color:#1A1A1A;">{{ $task->title }}</td>
                        <td style="padding:10px; color:#B91C1C; font-weight:600;">{{ $task->due_date->format('d.m.Y') }}</td>
                        <td style="padding:10px; color:#555;">{{ $task->company?->name ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <a href="{{ route('crm.index', ['tab' => 'tasks']) }}"
               style="display:inline-block; margin-top:20px; background:#1A4D3A; color:#F5F0E8; text-decoration:none; padding:10px 20px; border-radius:8px; font-family:'Manrope',sans-serif; font-size:13px; font-weight:700;">
                Przejdź do zadań w CRM
            </a>
        </div>
    </div>
</body>
</html>
