<div class="iso-doc-grid">
    <input name="title" maxlength="255" placeholder="Nazwa dokumentu (opcjonalnie)">
    <input name="document_year" type="number" min="2000" max="2200" value="{{ now()->year }}" aria-label="Rok dokumentu" placeholder="Rok">
    <input name="version_number" value="1.0" maxlength="40" required aria-label="Numer wersji" placeholder="Wersja">
</div>
<textarea name="description" rows="2" maxlength="2000" placeholder="Krótki opis dokumentu lub zmian w tej wersji (opcjonalnie)"></textarea>
<input class="iso-doc-file" type="file" name="files[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.csv,.txt,.jpg,.jpeg,.png,.zip">
<div class="iso-doc-meta">Do 20 plików jednocześnie, maksymalnie 30 MB każdy. Dodanie kolejnej wersji nie usuwa poprzedniej.</div>
