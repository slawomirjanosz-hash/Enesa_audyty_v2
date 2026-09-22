<div aria-label="Status ankiety" style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 18px">
@foreach(['W trakcie opracowania'=>!$profile->client_approval, 'Zaakceptowane przez klienta'=>(bool)$profile->client_approval, 'Zaakceptowane przez audytora'=>(bool)$profile->auditor_approval, 'Wygenerowano dokument'=>(bool)$profile->document_id] as $label=>$reached)
<span style="padding:5px 10px;border-radius:7px;font-size:13px;background:{{$reached?'#e3f2e8':'#f0f1ef'}};color:{{$reached?'#1a4d3a':'#647169'}}">{{$reached?'✓':'○'}} {{$label}}</span>
@endforeach
</div>
