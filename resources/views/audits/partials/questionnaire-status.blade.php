@php
    $statusCurrent = !($statusStale ?? false);
    $clientAccepted = $statusCurrent && (bool) $statusRecord?->client_approval;
    $auditorAccepted = $clientAccepted && (bool) $statusRecord?->auditor_approval;
    $documentCurrent = $auditorAccepted && (bool) $statusRecord?->document_id;
@endphp
<div aria-label="Status ankiety" style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 18px">
@foreach(['W trakcie opracowania'=>!$clientAccepted, 'Zaakceptowane przez klienta'=>$clientAccepted, 'Zaakceptowane przez audytora'=>$auditorAccepted, 'Wygenerowano dokument'=>$documentCurrent] as $label=>$reached)
<span data-questionnaire-status="{{ $loop->index }}" data-reached="{{ $reached ? 'true' : 'false' }}" style="padding:6px 11px;border-radius:7px;font-size:13px;font-weight:{{$reached?'600':'400'}};background:{{$reached?'#e3f2e8':'#f0f1ef'}};color:{{$reached?'#1a4d3a':'#647169'}}">{{$reached?'✓':'○'}} {{$label}}</span>
@endforeach
</div>
