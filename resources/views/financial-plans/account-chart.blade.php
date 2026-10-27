@php $account = $calculation['account']; @endphp
@if($account['opening'] !== null && $account['months'])
    @include('financial-plans.trend-chart', [
        'chartId' => 'fp-account-chart', 'chartTitle' => 'Stan konta działu · płatności rzeczywiste',
        'chartMeta' => 'Na dzień '.\Carbon\CarbonImmutable::parse($account['asOf'])->format('d.m.Y').' · '.$plan->periodLabel(),
        'chartSummaryNote' => $account['complete'] ? 'saldo wg wpisanych płatności' : 'saldo częściowe · sprawdź brakujące dane',
        'chartDescription' => 'Saldo początkowe '.$money($account['opening']).' + faktycznie otrzymane wpłaty − zapłacone wydatki. Faktury projektu: tylko status „Opłacona”, faktyczna data i kwota przelewu (z VAT, jeśli dotyczy). Pozostałe płatności działu pochodzą z rejestru poniżej. Kwoty prognozowane, oferty i niezapłacone faktury nie wchodzą do salda. Brakujących płatności nie szacujemy.',
        'chartMonths' => $account['months'],
        'chartSeries' => ['received' => ['label' => 'Otrzymane wpłaty', 'color' => '#245b45'], 'spent' => ['label' => 'Zapłacone wydatki', 'color' => '#bd493c'], 'balance' => ['label' => 'Stan konta', 'color' => '#346caa']],
        'chartColumns' => ['monthlyIn' => 'Wpłaty w miesiącu', 'monthlyOut' => 'Wydatki w miesiącu', 'received' => 'Wpłaty narastająco', 'spent' => 'Wydatki narastająco', 'balance' => 'Stan konta'],
    ])
@else
    <details class="fp-card fp-trend-chart" data-fp-details="fp-account-chart" open><summary class="fp-trend-summary"><span class="fp-trend-title">Stan konta działu · płatności rzeczywiste</span></summary><div class="fp-trend-body">
        @if($account['opening'] === null)<p class="fp-note fp-warning">Uzupełnij saldo początkowe na {{ $plan->periodStart()->format('d.m.Y') }} w ustawieniach konta poniżej. Brak salda nie jest traktowany jako 0 zł.</p>@endif
        @if(!$account['months'])<p class="fp-muted">Wybrany okres jeszcze się nie rozpoczął. Wykres rzeczywistych płatności pojawi się od pierwszego miesiąca tego okresu.</p>@endif
    </div></details>
@endif
@if($account['warnings'])<details class="fp-note fp-warning" data-fp-details="account-warnings"><summary>Niepełne dane płatności ({{ count($account['warnings']) }})</summary><ul>@foreach($account['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></details>@endif
@if(!$calculation['complete'])<p class="fp-note fp-warning">Stan konta jest częściowy: nie masz dostępu do wszystkich projektów planu.</p>@endif
<details class="fp-card" data-fp-details="account-transactions"><summary>Wpłaty i wydatki uwzględnione w stanie konta ({{ count($account['transactions']) }})</summary>
    <p class="fp-muted">Faktury koryguj w finansach projektu. Pozostałe płatności dotyczą całego działu i są wspólne dla jego planów; każda wchodzi do salda tylko raz.</p>
    <div class="fp-scroll" data-fp-scroll="account-transactions"><table data-fp-table="account-transactions"><thead><tr><th>Data zapłaty</th><th>Opis</th><th>Źródło</th><th>Wpłata (PLN)</th><th>Wydatek (PLN)</th>@if($canManage)<th data-sortable="false">Akcje</th>@endif</tr></thead><tbody>
        @foreach($account['transactions'] as $transaction)<tr><td data-sort-value="{{ $transaction['date'] }}">{{ \Carbon\CarbonImmutable::parse($transaction['date'])->format('d.m.Y') }}</td><td class="fp-payment-description">{{ $transaction['description'] }}</td><td>@if($transaction['project_id'])<a href="{{ route('projects.show', [$transaction['project_id'], 'tab' => 'finances']) }}">{{ $transaction['source'] }}</a>@else{{ $transaction['source'] }}@endif</td><td class="number" data-sort-value="{{ $transaction['direction'] === 'in' ? $transaction['amount'] / 100 : '' }}">{{ $transaction['direction'] === 'in' ? $money($transaction['amount']) : '—' }}</td><td class="number {{ $transaction['direction'] === 'out' ? 'negative' : '' }}" data-sort-value="{{ $transaction['direction'] === 'out' ? $transaction['amount'] / 100 : '' }}">{{ $transaction['direction'] === 'out' ? $money($transaction['amount']) : '—' }}</td>@if($canManage)<td>@if($transaction['manual_id'])<form method="POST" action="{{ route('financial-plans.payments.destroy', [$plan, $transaction['manual_id']]) }}" onsubmit="return confirm('Usunąć tę płatność z konta działu?')">@csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $plan->revision }}"><button class="fp-btn danger">Usuń</button></form>@else — @endif</td>@endif</tr>@endforeach
    </tbody></table></div>
    @if(!$account['transactions'])<p class="fp-muted">Brak zarejestrowanych rzeczywistych płatności w tym okresie.</p>@endif
</details>
