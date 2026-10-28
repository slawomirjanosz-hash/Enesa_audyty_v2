@php
    $costMonths = \App\Services\FinancialPlanChartData::costs($calculation);
    $runningCost = $costMonths[array_key_last($costMonths)]['cumulative'];
    $chartWidth = max(400, count($costMonths) * 64 + 90);
    $plotWidth = $chartWidth - 100;
    $step = $plotWidth / count($costMonths);
@endphp
<section class="fp-card fp-cost-chart" aria-labelledby="fp-cost-chart-title">
    <div class="fp-head"><div><h2 id="fp-cost-chart-title">Koszty utrzymania działu w czasie @include('financial-plans.hint', ['hintId' => 'fp-cost-chart-description-help', 'hintLabel' => '?', 'hintTitle' => 'Koszty utrzymania działu', 'hintText' => 'Wynagrodzenia + samochody + pozostałe koszty działu. Bez kosztów realizacji projektów.', 'hintItems' => [], 'hintClass' => 'fp-info'])</h2></div><strong class="fp-chart-total">{{ $money($runningCost) }} <small>w całym okresie</small></strong></div>
    <div class="fp-chart-legend"><span><i style="background:#245b45"></i>Wynagrodzenia</span><span><i style="background:#56a394"></i>Samochody</span><span><i style="background:#d7ad53"></i>Pozostałe koszty działu</span></div>
    <div class="fp-chart-grid">
        @foreach(['monthly' => 'Poszczególne miesiące', 'cumulative' => 'Narastająco od początku okresu'] as $chartKind => $chartTitle)
            @php
                $maximum = max(100, max(array_column($costMonths, $chartKind)));
                $scale = 180 / $maximum;
                $points = [];
                foreach ($costMonths as $i => $costMonth) $points[] = (75 + $step * ($i + .5)).','.round(210 - $costMonth[$chartKind] * $scale, 2);
            @endphp
            <details class="fp-chart-panel" data-fp-details="chart-cost-{{ $chartKind }}" open><summary>{{ $chartTitle }}</summary>@include('financial-plans.export-link', ['exportSection' => 'cost-'.$chartKind])<div class="fp-chart-scroll" data-fp-scroll="chart-{{ $chartKind }}">
                <svg viewBox="0 0 {{ $chartWidth }} 265" style="min-width:{{ $chartWidth }}px" role="img" aria-labelledby="fp-chart-{{ $chartKind }}-title fp-chart-{{ $chartKind }}-desc">
                    <title id="fp-chart-{{ $chartKind }}-title">{{ $chartTitle }} · koszty utrzymania działu</title>
                    <desc id="fp-chart-{{ $chartKind }}-desc">Kwoty w PLN. Wartości każdego miesiąca są dostępne po najechaniu lub przejściu klawiszem Tab oraz w tabeli pod wykresami.</desc>
                    @foreach(range(0, 3) as $tick)
                        @php $y = 210 - $tick * 60; @endphp
                        <line x1="75" x2="{{ $chartWidth - 25 }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#e1e9e3" />
                        <text x="65" y="{{ $y + 4 }}" text-anchor="end" class="fp-chart-axis">{{ number_format($maximum * $tick / 3 / 100, 0, ',', ' ') }}</text>
                    @endforeach
                    <text x="12" y="15" class="fp-chart-axis">PLN</text>
                    @if($chartKind === 'cumulative')<polyline points="{{ implode(' ', $points) }}" fill="none" stroke="#245b45" stroke-width="3" stroke-linejoin="round" />@endif
                    @foreach($costMonths as $i => $costMonth)
                        @php $x = 75 + $step * ($i + .5); $barWidth = min(32, $step * .55); $yBottom = 210; @endphp
                        <g tabindex="0" data-fp-tip="fp-chart-tip-{{ $chartKind }}-{{ $i }}" role="button" aria-describedby="fp-chart-tip-{{ $chartKind }}-{{ $i }}" aria-label="{{ $costMonth['label'] }}: {{ $money($costMonth[$chartKind]) }}">
                            @if($chartKind === 'monthly')
                                @foreach(['salary' => '#245b45', 'car' => '#56a394', 'overhead' => '#d7ad53'] as $component => $color)
                                    @php $height = $costMonth[$component] * $scale; $yBottom -= $height; @endphp
                                    <rect x="{{ $x - $barWidth / 2 }}" y="{{ $yBottom }}" width="{{ $barWidth }}" height="{{ $height }}" fill="{{ $color }}" />
                                @endforeach
                                <rect x="{{ $x - $barWidth / 2 - 6 }}" y="20" width="{{ $barWidth + 12 }}" height="200" fill="transparent" />
                            @else
                                <circle cx="{{ $x }}" cy="{{ 210 - $costMonth['cumulative'] * $scale }}" r="5" fill="#245b45" stroke="#fff" stroke-width="2" />
                                <circle cx="{{ $x }}" cy="{{ 210 - $costMonth['cumulative'] * $scale }}" r="15" fill="transparent" />
                            @endif
                        </g>
                        <text x="{{ $x }}" y="240" text-anchor="middle" class="fp-chart-axis">{{ $costMonth['label'] }}</text>
                    @endforeach
                </svg>
            </div></details>
            @foreach($costMonths as $i => $costMonth)
                <div id="fp-chart-tip-{{ $chartKind }}-{{ $i }}" popover="manual" role="tooltip" class="fp-tooltip"><strong>{{ $chartTitle }} · {{ $costMonth['label'] }}</strong><ul><li>Wynagrodzenia: {{ $money($costMonth['salary']) }}</li><li>Samochody: {{ $money($costMonth['car']) }}</li><li>Pozostałe koszty: {{ $money($costMonth['overhead']) }}</li><li>Razem w miesiącu: {{ $money($costMonth['monthly']) }}</li><li>Narastająco: {{ $money($costMonth['cumulative']) }}</li></ul></div>
            @endforeach
        @endforeach
    </div>
    <details data-fp-details="chart-values"><summary>Pokaż wartości wykresu</summary><div class="fp-scroll" data-fp-scroll="chart-values"><table data-fp-table="chart-values"><thead><tr><th>Miesiąc</th><th>Wynagrodzenia</th><th>Samochody</th><th>Pozostałe koszty</th><th>Razem w miesiącu</th><th>Narastająco</th></tr></thead><tbody>
        @foreach($costMonths as $i => $costMonth)<tr><td data-sort-value="{{ $calculation['months'][$i + 1]->format('Y-m') }}">{{ $costMonth['label'] }}</td>@foreach(['salary', 'car', 'overhead', 'monthly', 'cumulative'] as $component)<td class="number negative" data-sort-value="{{ $costMonth[$component] / 100 }}">{{ $money($costMonth[$component]) }}</td>@endforeach</tr>@endforeach
    </tbody></table></div></details>
</section>
