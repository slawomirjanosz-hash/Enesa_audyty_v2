<section id="pane-work-orders" class="pane">
<div class="card">
    <div class="requirements-head">
        <div><h2>Zlecenia realizacji prac</h2></div>
        @if($canManageWorkOrders)<a class="btn" href="{{route('projects.work-orders.create',$project)}}"><i class="ti ti-plus"></i> Dodaj zlecenie</a>@endif
    </div>
    <div style="overflow-x:auto"><table>
        <thead><tr><th>Numer</th><th>Tytuł</th><th>Wykonawca</th><th>Data</th><th>Status</th><th>Netto</th><th>Brutto</th><th data-sortable="false">Akcje</th></tr></thead>
        <tbody>@forelse($workOrders as $order)
        <tr>
            <td><a href="{{route('projects.work-orders.show',[$project,$order])}}">{{$order->number}}</a></td>
            <td>{{$order->title}} @if($order->is_template)<span class="badge">Wzór</span>@endif</td>
            <td>{{$order->supplier_snapshot['name'] ?? 'Nie wybrano'}}</td>
            <td data-sort-value="{{$order->issued_on?->toDateString()}}">{{$order->issued_on?->format('d.m.Y') ?? '—'}}</td>
            <td>{{\App\Models\ProjectWorkOrder::STATUSES[$order->status]}}</td>
            <td data-sort-value="{{$order->net_cents}}">{{number_format($order->net_cents/100,2,',',' ')}} zł</td>
            <td data-sort-value="{{$order->net_cents+$order->vat_cents}}">{{number_format(($order->net_cents+$order->vat_cents)/100,2,',',' ')}} zł</td>
            <td><div class="mini-actions">
                <a class="btn btn-soft" title="Podgląd zlecenia" href="{{route('projects.work-orders.show',[$project,$order])}}">Podgląd</a>
                <a class="btn btn-soft" title="Podgląd PDF" target="_blank" rel="noopener" href="{{route('projects.work-orders.document',[$project,$order])}}">PDF</a>
                @if($canManageWorkOrders)<a class="btn btn-soft" title="Kopiuj zlecenie" href="{{route('projects.work-orders.create',[$project,'source_id'=>$order->id])}}">Kopiuj</a>@endif
            </div></td>
        </tr>
        @empty<tr><td colspan="8">Brak zleceń. Dodaj puste zlecenie albo utwórz je z materiałów i usług.</td></tr>@endforelse</tbody>
        <tfoot><tr><th colspan="5">Zobowiązania z wystawionych zleceń (bez wzorów i anulowanych)</th><td>{{number_format($workOrders->where('is_template',false)->whereNotIn('status',['draft','cancelled'])->sum('net_cents')/100,2,',',' ')}} zł</td><td>{{number_format($workOrders->where('is_template',false)->whereNotIn('status',['draft','cancelled'])->sum(fn($o)=>$o->net_cents+$o->vat_cents)/100,2,',',' ')}} zł</td><td></td></tr></tfoot>
    </table></div>
</div>
</section>
