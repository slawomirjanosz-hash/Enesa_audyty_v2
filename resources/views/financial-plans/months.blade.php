<div class="fp-scroll"><table>
    <thead><tr><th>Pozycja</th>@foreach($calculation['months'] as $month)<th>{{ $month->format('m.Y') }}</th>@endforeach<th>Razem w okresie</th></tr></thead>
    <tbody>@foreach($labels as $key => $label)
        @php $annualValue = $key === 'gap' && isset($rows['result']) ? max(0, -array_sum($rows['result'])) : array_sum($rows[$key]); @endphp
        <tr @class(['total-row' => in_array($key, ['margin', 'result', 'gap', 'balance', 'deviation'])]) @if(in_array($key, ['margin', 'result', 'gap', 'balance', 'deviation'])) data-sort-fixed @endif>
            <td>{{ $label }}</td>@foreach($rows[$key] as $value)<td class="number {{ $value < 0 ? 'negative' : '' }}" data-sort-value="{{ $value / 100 }}">{{ number_format($value / 100, 2, ',', ' ') }}</td>@endforeach
            <td class="number {{ $annualValue < 0 ? 'negative' : '' }}" data-sort-value="{{ $annualValue / 100 }}">{{ number_format($annualValue / 100, 2, ',', ' ') }}</td>
        </tr>
    @endforeach</tbody>
</table></div>
