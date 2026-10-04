@once<link rel="stylesheet" href="{{asset('css/company-reliability.css')}}">@endonce
<section class="rel-card rel-documents">
    <h3 style="margin:0 0 12px">Poufne dokumenty — raporty wiarygodności</h3>
    <p>Wyłącznie dla upoważnionych pracowników. Raporty nie są udostępniane w strefie klienta.</p>
    @if($reports->isEmpty())<p>Brak raportów.</p>@else
    <div class="rel-table-scroll"><table style="width:100%;border-collapse:collapse" class="reliability-table">
        <thead><tr><th>Dokument</th><th>Data</th><th>Ocena w raporcie</th><th>Autor</th><th data-sortable="false">Akcje</th></tr></thead>
        <tbody>@foreach($reports as $item)<tr>
            <td style="padding:12px 8px;border-top:1px solid #e7ece9">Raport wiarygodności #{{$item->id}}</td>
            <td data-sort-value="{{$item->created_at->timestamp}}">{{$item->created_at->format('d.m.Y H:i')}}</td>
            <td>{{\App\Models\CompanyReliabilityReport::LABELS[$item->status]}}</td>
            <td>{{$item->author?->name ?? 'Usunięty użytkownik'}}</td>
            <td><div class="rel-doc-actions"><a class="rel-action" href="{{route('companies.reliability.download', [$company, $item])}}">Pobierz PDF</a>
                @if(app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'delete', $company))
                <form method="POST" action="{{route('companies.reliability.destroy', [$company, $item])}}" style="display:inline" onsubmit="return confirm('Trwale usunąć raport i plik PDF? Status firmy zostanie przeliczony z pozostałych raportów.')">@csrf @method('DELETE')<button class="rel-action rel-action-danger" type="submit">Usuń</button></form>
                @endif
            </div></td>
        </tr>@endforeach</tbody>
    </table></div>@endif
</section>
@include('companies.reliability.source-files')
