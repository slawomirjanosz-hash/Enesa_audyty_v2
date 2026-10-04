<section style="background:#fff;border:1px solid #dde5df;border-radius:12px;padding:20px;margin:18px 0">
    <h3 style="margin:0 0 12px">Poufne dokumenty — raporty wiarygodności</h3>
    <p>Wyłącznie dla upoważnionych pracowników. Raporty nie są udostępniane w strefie klienta.</p>
    @if($reports->isEmpty())<p>Brak raportów.</p>@else
    <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse" class="reliability-table">
        <thead><tr><th>Dokument</th><th>Data</th><th>Ocena w raporcie</th><th>Autor</th><th data-sortable="false">Akcje</th></tr></thead>
        <tbody>@foreach($reports as $item)<tr>
            <td style="padding:12px 8px;border-top:1px solid #e7ece9">Raport wiarygodności #{{$item->id}}</td>
            <td data-sort-value="{{$item->created_at->timestamp}}">{{$item->created_at->format('d.m.Y H:i')}}</td>
            <td>{{\App\Models\CompanyReliabilityReport::LABELS[$item->status]}}</td>
            <td>{{$item->author?->name ?? 'Usunięty użytkownik'}}</td>
            <td><a href="{{route('companies.reliability.download', [$company, $item])}}">Pobierz PDF</a>
                @if(app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'delete', $company))
                <form method="POST" action="{{route('companies.reliability.destroy', [$company, $item])}}" style="display:inline" onsubmit="return confirm('Trwale usunąć raport i plik PDF? Status firmy zostanie przeliczony z pozostałych raportów.')">@csrf @method('DELETE')<button type="submit" style="margin-left:12px;color:#b42318;background:none;border:0;cursor:pointer">Usuń</button></form>
                @endif
            </td>
        </tr>@endforeach</tbody>
    </table></div>@endif
</section>
