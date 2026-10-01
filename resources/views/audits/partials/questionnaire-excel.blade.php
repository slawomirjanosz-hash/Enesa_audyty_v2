<details class="plant-card questionnaire-excel">
    <summary style="cursor:pointer;font-weight:700">Wypełnianie poza systemem — Excel</summary>
    <p>Pobierz ankietę i przekaż plik klientowi. Po wypełnieniu wgraj ten sam plik XLSX. Odpowiedzi trafią do właściwych pytań, bez automatycznego zatwierdzania.</p>
    @if($excelReady ?? true)
    <a href="{{route($prefix.'excel',[$audit,$profile])}}">Pobierz ankietę Excel</a>
    @if($editable)
    <form method="post" enctype="multipart/form-data" action="{{route($prefix.'excel-import',[$audit,$profile])}}" onsubmit="return confirm('Import zastąpi zapisane odpowiedzi zawartością pliku, także pustymi polami. Niezapisane zmiany w formularzu zostaną utracone. Kontynuować?')">
        @csrf
        <label style="display:block;margin-top:14px">Wypełniona ankieta XLSX (maks. 10 MB)<input type="file" name="excel" accept=".xlsx" required></label>
        <button type="submit">Importuj odpowiedzi z Excela</button>
    </form>
    @endif
    <p class="plant-help">Przed eksportem zapisz zmiany w formularzu. Nie zmieniaj kodów pytań. Plik nie może służyć do importu w innym zakładzie lub audycie. Po zmianie danych w systemie pobierz nowy plik.</p>
    @else<p>Eksport będzie dostępny po zatwierdzeniu profilu zakładu przez klienta i audytora.</p>@endif
</details>
