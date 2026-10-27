@extends('layouts.app')
@section('title', 'Plan finansowy · '.$plan->department->name.' · '.$plan->year)
@section('content')
@include('financial-plans.styles')
@php
    $forecast = $calculation['forecast'];
    $approved = $canViewBaseline ? $plan->approved_snapshot['rows'] : null;
    $money = fn ($cents) => number_format($cents / 100, 2, ',', ' ').' zł';
@endphp
<div class="fp">
    <div class="fp-head"><div><a href="{{ route('financial-plans.index') }}" class="fp-muted">← Działy i lata</a><h1>{{ $plan->department->name }} · {{ $plan->year }}</h1><div class="fp-muted">Plan finansowy działu · kwoty przychodów i kosztów projektów netto · pełny koszt zatrudnienia</div></div><span class="fp-badge {{ $plan->approved_at ? '' : 'warn' }}">{{ $plan->approved_at ? 'Plan zatwierdzony '.$plan->approved_at->format('d.m.Y') : 'Plan roczny: wersja robocza' }}</span></div>
    @if(session('success'))<div class="fp-note" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="fp-note fp-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="fp-tabs">@foreach(\App\Services\FinancialPlanCalculator::SCENARIOS as $key => $label)<a class="{{ $scenario === $key ? 'active' : '' }}" href="{{ route('financial-plans.show', [$plan, 'scenario' => $key]) }}">{{ $label }}</a>@endforeach</div>
    <p class="fp-muted">Ostrożny: projekty i potwierdzone oferty. Bazowy: dodatkowo realne oferty ważone szansą. Optymistyczny: także rezerwowe oferty, w pełnej wartości. Oferty wykluczone i przegrane nie wchodzą do żadnego scenariusza. Ręczna sprzedaż jest założeniem wspólnym scenariuszy.</p>
    <div class="fp-grid">
        <div class="fp-card"><div class="fp-metric-title">Przychody prognozowane</div><div class="fp-kpi">{{ $money(array_sum($forecast['contracted']) + array_sum($forecast['pipeline']) + array_sum($forecast['manual_revenue'])) }}</div></div>
        <div class="fp-card"><div class="fp-metric-title">Koszty wydziałowe · ludzie i samochody</div><div class="fp-kpi">{{ $money(array_sum($forecast['salary']) + array_sum($forecast['car'])) }}</div></div>
        <div class="fp-card"><div class="fp-metric-title">Wynik działu · {{ \App\Services\FinancialPlanCalculator::SCENARIOS[$scenario] }}</div><div class="fp-kpi {{ array_sum($forecast['result']) < 0 ? 'negative' : '' }}">{{ $money(array_sum($forecast['result'])) }}</div></div>
    </div>
    @if($calculation['warnings'])<details class="fp-note fp-warning" open><summary>Dane wymagające uwagi ({{ count($calculation['warnings']) }})</summary><ul>@foreach($calculation['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></details>@endif
    <section class="fp-card"><div class="fp-head"><h2>Aktualna prognoza miesięczna</h2>@if($canManage)<a href="#line-form" class="fp-btn primary">Dodaj pozycję</a>@endif</div>
        @include('financial-plans.months', ['rows' => $forecast, 'labels' => \App\Services\FinancialPlanCalculator::ROWS])
        <p class="fp-muted">Przychód umowy zastępuje się wystawionymi fakturami, a pozostała wartość jest rozkładana na okres pozycji. Koszty poniesione są dodawane raz; ręczna wycena pozostałego kosztu zastępuje koszty planowane ze źródła. Kwoty wynagrodzeń i samochodów są miesięczne, liczone za każdy wskazany miesiąc.</p>
    </section>
    <section class="fp-card"><h2>Plan roczny i odchylenie prognozy</h2>
        @if($approved)
            @php $comparison = ['plan' => $approved['result'], 'forecast' => $forecast['result'], 'deviation' => []]; foreach(range(1, 12) as $month) { $comparison['deviation'][$month] = $forecast['result'][$month] - $approved['result'][$month]; } @endphp
            @include('financial-plans.months', ['rows' => $comparison, 'labels' => ['plan' => 'Wynik zatwierdzonego planu', 'forecast' => 'Wynik aktualnej prognozy', 'deviation' => 'Odchylenie od planu']])
            <details style="margin-top:18px"><summary>Pełny zatwierdzony plan roczny · scenariusz bazowy</summary>@include('financial-plans.months', ['rows' => $approved, 'labels' => \App\Services\FinancialPlanCalculator::ROWS])</details>
        @elseif($plan->approved_at)<p>Podgląd zatwierdzonego planu wymaga dostępu do wszystkich jego źródeł.</p>
        @else<p class="fp-muted">Zatwierdzenie zapisze niezmienny plan bazowy. Późniejsze zmiany ofert, projektów i założeń będą aktualizowały prognozę.</p>
            @if($canManage)<form method="POST" action="{{ route('financial-plans.approve', $plan) }}">@csrf<input type="hidden" name="revision" value="{{ $plan->revision }}"><button class="fp-btn primary">Zatwierdź plan roczny · scenariusz bazowy</button></form>@endif
        @endif
    </section>
    <section class="fp-card"><h2>Wykonanie</h2>
        <p class="fp-muted">Przychody i koszty bezpośrednie: zapisy projektowe ze statusem „wystawiona” lub „opłacona”, według daty zapisu. To wykonanie fakturowe. Koszty wydziałowe i pozostałe: ręcznie wpisane kwoty wykonania. Brakujące kwoty są sygnalizowane powyżej; wynik wykonania pozostaje wtedy częściowy.</p>
        @include('financial-plans.months', ['rows' => $calculation['actual'], 'labels' => ['revenue' => 'Przychody zafakturowane', 'direct_cost' => 'Koszty bezpośrednie', 'salary' => 'Wynagrodzenia · wykonanie', 'car' => 'Samochody · wykonanie', 'overhead' => 'Pozostałe koszty · wykonanie', 'result' => 'Wynik wykonania (dostępne dane)']])
    </section>
    <details class="fp-card"><summary>Przepływy pieniężne · harmonogram zapisów projektowych</summary><p class="fp-muted">Wpływy i wydatki według zapisanych terminów płatności, łącznie z pozycjami planowanymi. Widok obejmuje tylko zapisy finansowe projektów; nie obejmuje niewprowadzonych płatności z ofert, pozostałej wartości umów ani kosztów wydziałowych. Kwoty netto — nie jest to pełny stan gotówki ani rozliczenie VAT.</p>
        @include('financial-plans.months', ['rows' => $calculation['cash'], 'labels' => ['in' => 'Wpływy wg terminów płatności', 'out' => 'Wydatki wg terminów płatności', 'balance' => 'Saldo harmonogramu projektów']])
    </details>
    <section class="fp-card"><h2>Źródła i założenia prognozy</h2><div class="fp-scroll"><table><thead><tr><th>Pozycja</th><th>Rodzaj</th><th>Okres</th><th>Kwalifikacja / dokładność</th><th>Szansa</th><th>Przychód źródłowy</th><th>Pozostały koszt źródłowy</th><th>Przychód prognozy w roku</th><th>Koszt prognozy w roku</th><th>Uwagi</th>@if($canManage)<th data-sortable="false">Działania</th>@endif</tr></thead><tbody>
        @foreach($lines as $line)@php $detail = $calculation['details'][$line->id]; @endphp
            <tr><td>@if($line->kind === 'project')<a href="{{ route('projects.show', $line->project_id) }}">{{ $line->name }}</a>@elseif($line->kind === 'offer')<a href="{{ route('offers.show', $line->offer_id) }}">{{ $line->name }}</a>@else{{ $line->name }}@endif</td>
                <td>{{ \App\Models\FinancialPlanLine::KINDS[$line->kind] }}</td><td data-sort-value="{{ $line->start_date->format('Y-m-d') }}">{{ $line->start_date->format('m.Y') }} – {{ $line->end_date->format('m.Y') }}</td>
                <td>{{ $line->kind === 'offer' ? \App\Models\FinancialPlanLine::QUALIFICATIONS[$line->qualification] : ($line->kind === 'project' ? \App\Models\FinancialPlanLine::ACCURACY[$line->budget_accuracy] : '—') }}</td>
                <td data-sort-value="{{ $line->kind === 'offer' ? $line->probability : '' }}">{{ $line->kind === 'offer' ? $line->probability.'%' : '—' }}</td>
                <td class="number" data-sort-value="{{ $detail['sourceRevenue'] }}">{{ $detail['sourceRevenue'] !== null ? $money((int) round($detail['sourceRevenue'] * 100)) : '—' }}</td>
                <td class="number" data-sort-value="{{ $detail['sourceCost'] }}">{{ $detail['sourceCost'] !== null ? $money((int) round($detail['sourceCost'] * 100)) : '—' }}</td>
                <td class="number" data-sort-value="{{ $detail['lineRevenue'] / 100 }}">{{ $money($detail['lineRevenue']) }}</td><td class="number" data-sort-value="{{ $detail['lineCost'] / 100 }}">{{ $money($detail['lineCost']) }}</td>
                <td class="fp-line-note">{{ $line->notes }}@foreach($detail['lineWarnings'] as $warning)<div>{{ $warning }}</div>@endforeach</td>
                @if($canManage)<td><a class="fp-btn" href="{{ route('financial-plans.show', [$plan, 'edit' => $line->id, 'scenario' => $scenario]) }}#line-form">Edytuj</a><details style="margin-top:8px"><summary>Usuń</summary><form method="POST" action="{{ route('financial-plans.lines.destroy', [$plan, $line]) }}">@csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $plan->revision }}"><label>Powód usunięcia<input name="reason" required maxlength="1000"></label><button class="fp-btn danger">Usuń z prognozy</button></form></details></td>@endif
            </tr>
        @endforeach
    </tbody></table></div>
    @if($lines->isEmpty())<p class="fp-muted">Dodaj projekty, realne oferty oraz koszty osób z działu.</p>@endif</section>
    <section class="fp-card"><h2>Koszty wydziałowe · osoby i samochody</h2><div class="fp-scroll"><table><thead><tr><th>Osoba</th><th>Okres</th><th>Wynagrodzenie / mies.</th><th>Samochód</th><th>Samochód / mies.</th><th>Razem w roku</th></tr></thead><tbody>
        @foreach($lines->where('kind', 'person') as $line)<tr><td>{{ $line->person?->name ?? 'Osoba archiwalna' }}</td><td data-sort-value="{{ $line->start_date->format('Y-m-d') }}">{{ $line->start_date->format('m.Y') }} – {{ $line->end_date->format('m.Y') }}</td><td class="number" data-sort-value="{{ $line->salary_cost }}">{{ $money((int) round($line->salary_cost * 100)) }}</td><td>{{ $line->car_name ?: '—' }}</td><td class="number" data-sort-value="{{ $line->car_cost }}">{{ $money((int) round(($line->car_cost ?? 0) * 100)) }}</td><td class="number" data-sort-value="{{ $calculation['details'][$line->id]['lineCost'] / 100 }}">{{ $money($calculation['details'][$line->id]['lineCost']) }}</td></tr>@endforeach
    </tbody><tfoot><tr class="total-row"><td>Razem koszty wydziałowe</td><td colspan="4"></td><td class="number">{{ $money(array_sum($forecast['salary']) + array_sum($forecast['car'])) }}</td></tr></tfoot></table></div></section>
    @if($canManage)@include('financial-plans.line-form')@endif
    @if($calculation['complete'])<details class="fp-card"><summary>Historia zmian i decyzji</summary><div style="margin-top:16px">
        @foreach($changes as $change)<details><summary>{{ $change->created_at->timezone('Europe/Warsaw')->format('d.m.Y H:i') }} · {{ $change->author?->name }} · {{ ['created' => 'Dodanie pozycji', 'updated' => 'Zmiana pozycji', 'deleted' => 'Usunięcie pozycji', 'approved' => 'Zatwierdzenie planu'][$change->action] ?? $change->action }}</summary><p class="fp-muted">{{ $change->payload['after']['name'] ?? $change->payload['before']['name'] ?? 'Plan roczny' }} · {{ $change->payload['after']['notes'] ?? $change->payload['reason'] ?? 'Zapisano plan bazowy' }}</p></details>@endforeach
        {{ $changes->links() }}
    </div></details>@endif
</div>
<script type="module" src="{{ asset('js/financial-plan.js') }}"></script>
@endsection
