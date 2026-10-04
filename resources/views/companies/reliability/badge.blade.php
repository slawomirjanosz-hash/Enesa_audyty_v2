@php
    $rating = $report?->displayStatus() ?? 'unassessed';
    $palette = ['green'=>['#e8f5e9','#23743b'], 'yellow'=>['#fff7d6','#805f00'], 'red'=>['#ffeded','#b42318'], 'unassessed'=>['#eef1f3','#52606d']];
@endphp
<span style="display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:20px;font-size:12px;background:{{$palette[$rating][0]}};color:{{$palette[$rating][1]}}" title="{{$report ? 'Ocena z '.$report->created_at->format('d.m.Y').' — nie jest gwarancją wypłacalności' : 'Firma nie ma zapisanej oceny'}}">
    <span aria-hidden="true">●</span> {{\App\Models\CompanyReliabilityReport::LABELS[$rating]}}
    @if($report && $rating !== $report->status) · odśwież ocenę @endif
</span>
