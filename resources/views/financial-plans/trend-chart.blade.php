@php
    $chartWidth = max(650, count($chartMonths) * 72 + 110);
    $plotWidth = $chartWidth - 120;
    $step = $plotWidth / count($chartMonths);
    $values = [0];
    foreach ($chartMonths as $chartMonth) foreach ($chartSeries as $key => $series) $values[] = $chartMonth[$key];
    $lower = min($values);
    $upper = max($values);
    if ($upper === $lower) $upper = $lower + 10000;
    $range = $upper - $lower;
    $toY = fn ($value) => round(220 - ($value - $lower) / $range * 180, 2);
    $zeroY = $toY(0);
    $lastMonth = $chartMonths[array_key_last($chartMonths)];
@endphp
<details class="fp-card fp-trend-chart" data-fp-details="{{ $chartId }}" open>
    <summary class="fp-trend-summary"><span><span class="fp-trend-title">{{ $chartTitle }} @include('financial-plans.hint', ['hintId' => $chartId.'-description-help', 'hintLabel' => '?', 'hintTitle' => $chartTitle, 'hintText' => $chartDescription, 'hintItems' => [], 'hintClass' => 'fp-info'])</span><span class="fp-muted">{{ $chartMeta ?? $plan->periodLabel().' · '.\App\Services\FinancialPlanCalculator::SCENARIOS[$scenario] }}</span></span><strong class="{{ $lastMonth['balance'] < 0 ? 'negative' : '' }}">{{ $money($lastMonth['balance']) }}@if(!empty($chartSummaryNote))<small class="fp-muted">{{ $chartSummaryNote }}</small>@endif</strong></summary>
    <div class="fp-trend-body">
        <div class="fp-chart-legend">@foreach($chartSeries as $series)<span><i style="background:{{ $series['color'] }}"></i>{{ $series['label'] }}</span>@endforeach</div>
        <div class="fp-chart-scroll" data-fp-scroll="{{ $chartId }}">
            <svg viewBox="0 0 {{ $chartWidth }} 280" style="min-width:{{ $chartWidth }}px" role="img" aria-labelledby="{{ $chartId }}-title {{ $chartId }}-desc">
                <title id="{{ $chartId }}-title">{{ $chartTitle }}</title><desc id="{{ $chartId }}-desc">{{ $chartDescription }} Kwoty w PLN. Wartości dostępne w chmurkach i tabeli pod wykresem.</desc>
                @foreach(range(0, 4) as $tick)
                    @php $tickValue = $lower + $range * $tick / 4; $y = $toY($tickValue); @endphp
                    <line x1="90" x2="{{ $chartWidth - 30 }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#e1e9e3" />
                    <text x="80" y="{{ $y + 4 }}" text-anchor="end" class="fp-chart-axis">{{ number_format($tickValue / 100, 0, ',', ' ') }}</text>
                @endforeach
                <text x="15" y="20" class="fp-chart-axis">PLN</text>
                <line x1="90" x2="{{ $chartWidth - 30 }}" y1="{{ $zeroY }}" y2="{{ $zeroY }}" stroke="#74877a" stroke-width="1.5" />
                @foreach($chartSeries as $key => $series)
                    @php $points = []; foreach ($chartMonths as $index => $chartMonth) $points[] = round(90 + $step * ($index + .5), 2).','.$toY($chartMonth[$key]); @endphp
                    <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="{{ $key === 'balance' ? 3.5 : 2.5 }}" @if($key === 'balance') stroke-dasharray="7 4" @endif stroke-linejoin="round" />
                    @foreach($chartMonths as $index => $chartMonth)<circle cx="{{ 90 + $step * ($index + .5) }}" cy="{{ $toY($chartMonth[$key]) }}" r="4" fill="{{ $series['color'] }}" />@endforeach
                @endforeach
                @foreach($chartMonths as $index => $chartMonth)
                    @php $x = 90 + $step * ($index + .5); @endphp
                    <g tabindex="0" data-fp-tip="{{ $chartId }}-tip-{{ $index }}" role="button" aria-describedby="{{ $chartId }}-tip-{{ $index }}" aria-label="{{ $chartMonth['label'] }} · saldo {{ $money($chartMonth['balance']) }}"><rect x="{{ $x - $step / 2 }}" y="30" width="{{ $step }}" height="200" fill="transparent" /></g>
                    <text x="{{ $x }}" y="250" text-anchor="middle" class="fp-chart-axis">{{ $chartMonth['label'] }}</text>
                @endforeach
            </svg>
        </div>
        @foreach($chartMonths as $index => $chartMonth)<div id="{{ $chartId }}-tip-{{ $index }}" popover="manual" role="tooltip" class="fp-tooltip"><strong>{{ $chartTitle }} · {{ $chartMonth['label'] }}</strong><ul>@foreach($chartColumns as $key => $label)<li>{{ $label }}: {{ $money($chartMonth[$key]) }}</li>@endforeach</ul></div>@endforeach
        <details data-fp-details="{{ $chartId }}-values"><summary>Pokaż wartości wykresu</summary><div class="fp-scroll" data-fp-scroll="{{ $chartId }}-values"><table data-fp-table="{{ $chartId }}-values"><thead><tr><th>Miesiąc</th>@foreach($chartColumns as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>
            @foreach($chartMonths as $chartMonth)<tr><td data-sort-value="{{ $chartMonth['date'] }}">{{ $chartMonth['label'] }}</td>@foreach($chartColumns as $key => $label)<td class="number {{ $chartMonth[$key] < 0 ? 'negative' : '' }}" data-sort-value="{{ $chartMonth[$key] / 100 }}">{{ $money($chartMonth[$key]) }}</td>@endforeach</tr>@endforeach
        </tbody></table></div></details>
    </div>
</details>
