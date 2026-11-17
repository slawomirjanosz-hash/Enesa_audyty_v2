<div style="display:flex;gap:6px">
    @if($internal)
        <select @if($inputId) id="{{ $inputId }}" @endif name="{{ $key }}[]" aria-label="{{ $label }}">
            <option value="">Wybierz osobę lub pozostaw puste</option>
            @foreach($options as $option)<option value="{{ $option }}" @selected($name === $option)>{{ $option }}</option>@endforeach
        </select>
    @else
        <input @if($inputId) id="{{ $inputId }}" @endif name="{{ $key }}[]" value="{{ $name }}" maxlength="255" aria-label="{{ $label }}" placeholder="Imię i nazwisko">
    @endif
    <button type="button" data-remove-person title="Wyczyść lub usuń osobę" aria-label="Wyczyść lub usuń osobę"><i class="ti ti-x" aria-hidden="true"></i></button>
</div>
