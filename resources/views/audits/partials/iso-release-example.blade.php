@if(($exampleAllowed ?? false) && auth()->user()?->can('audits.manage'))
<div class="plant-notice">
    <a class="plant-button" href="{{ request()->fullUrlWithQuery(['source_example'=>1]) }}" onclick="return confirm('Wczytać pełny przykład AutoParts z wydania 18.10.2026? Zastąpi formularz na ekranie. Zapisane dane pozostaną bez zmian do kliknięcia Zapisz. Niezapisane zmiany formularza zostaną utracone.')">Wypełnij przykładem AutoParts</a>
    <p>Dane demonstracyjne z wydania 18.10.2026. Zapis i zatwierdzenia wykonujesz osobno. Przykład nie zmienia danych punktów źródłowych.</p>
</div>
@endif
@if(request()->boolean('source_example'))
<div class="plant-notice" role="status"><strong>Wczytano przykład AutoParts do formularza.</strong> Dane nie zostały zapisane ani zatwierdzone. Sprawdź zgodność z zakładem i punktami źródłowymi przed zapisem.</div>
@endif
