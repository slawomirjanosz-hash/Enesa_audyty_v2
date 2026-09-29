@php
    $line = $line ?? [];
    $itemId = $rowItem?->id ?? ($line['item_id'] ?? '');
    $suggested = $defaults->get($itemId, []);
    $prefix = $chosen ? 'lines['.$index.']' : '';
    $supplierId = array_key_exists('supplier_id', $line) ? $line['supplier_id'] : ($suggested['supplier_id'] ?? '');
@endphp
<tr class="wh-pick-row" data-item-id="{{$itemId}}">
    <td>{{$rowItem?->sku ?? '—'}}<input type="hidden" data-field="item_id" @if($chosen) name="{{$prefix}}[item_id]" @endif value="{{$itemId}}"><input type="hidden" data-field="revision" @if($chosen) name="{{$prefix}}[revision]" @endif value="{{$line['revision'] ?? $rowItem?->revision}}"></td>
    <td>{{$rowItem?->name ?? 'Pozycja niedostępna — usuń ją i wybierz ponownie'}}</td>
    <td>{{$rowItem?->unit ?? '—'}}</td>
    <td data-sort-value="{{$rowItem?->quantity}}">{{number_format((float)$rowItem?->quantity,3,',',' ')}}</td>
    <td><input data-field="quantity" aria-label="Ilość — {{$rowItem?->name}}" type="number" min="{{$type==='adjustment'?0:0.001}}" max="1000000" step="0.001" @if($chosen) name="{{$prefix}}[quantity]" required @endif value="{{$line['quantity'] ?? ($type==='adjustment' ? $rowItem?->quantity : 1)}}" aria-invalid="{{$chosen && $errors->has('lines.'.$index.'.quantity')?'true':'false'}}"></td>
    @if($type==='receipt')
    <td><input data-field="unit_cost" aria-label="Cena netto — {{$rowItem?->name}}" type="number" min="0" max="1000000" step="0.01" @if($chosen) name="{{$prefix}}[unit_cost]" required @endif value="{{$line['unit_cost'] ?? $suggested['unit_cost'] ?? 0}}" aria-invalid="{{$chosen && $errors->has('lines.'.$index.'.unit_cost')?'true':'false'}}"></td>
    <td><select data-field="supplier_id" aria-label="Dostawca — {{$rowItem?->name}}" @if($chosen) name="{{$prefix}}[supplier_id]" @endif aria-invalid="{{$chosen && $errors->has('lines.'.$index.'.supplier_id')?'true':'false'}}"><option value="">Bez przypisania</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}" @selected((string)$supplierId===(string)$supplier->id)>{{$supplier->name}}</option>@endforeach</select></td>
    @endif
    <td><button type="button" class="wh-btn {{$chosen?'danger wh-remove':'primary wh-pick'}}">{{$chosen?'Usuń':'Dodaj'}}</button></td>
</tr>
