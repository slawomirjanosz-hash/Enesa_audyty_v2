<div class="plant-excel-actions" aria-label="Ankieta Excel">
    <a class="plant-profile-icon" href="{{ route($prefix.'excel', [$audit, $profile]) }}" title="Wypełnij Excela offline" aria-label="Wypełnij Excela offline"><i class="ti ti-file-type-xls" aria-hidden="true"></i></a>
    @if($editable)
        <form method="post" enctype="multipart/form-data" action="{{ route($prefix.'excel-import', [$audit, $profile]) }}" data-plant-excel-import>
            @csrf
            <input type="file" name="excel" accept=".xlsx" hidden aria-label="Wypełniona ankieta Excel" required>
            <button type="button" class="plant-profile-icon" title="Załaduj Excela" aria-label="Załaduj Excela" data-plant-excel-open><i class="ti ti-upload" aria-hidden="true"></i></button>
        </form>
    @endif
</div>
