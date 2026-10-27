<section data-fp-kinds="offer" class="fp-offer-schedule">
    <h3>Harmonogram wpływów i kosztów @include('financial-plans.hint', ['hintId' => 'fp-offer-schedule-help', 'hintLabel' => '?', 'hintTitle' => 'Płatności oferty', 'hintText' => 'Podziel pełną wartość netto oferty na wpływy z datami płatności. Osobno dodaj koszty z datami; ich suma uzupełni puste pole kosztu. Kwoty wpisuj w 100%, bez mnożenia przez szansę. Plan i przepływy uwzględnią scenariusz. Daty mogą wykraczać poza okres planu — widoczne będą tylko płatności w wybranych miesiącach. Rezerwa ryzyka wpływa na wynik, ale bez terminu nie jest płatnością.', 'hintItems' => [], 'hintClass' => 'fp-info'])</h3>
    <div data-offer-schedule-rows>
        @foreach(old('offer_schedule', $editing?->offer_schedule ?? []) as $index => $payment)
            @include('financial-plans.offer-schedule-row', ['rowIndex' => $index, 'payment' => $payment])
        @endforeach
    </div>
    <div class="fp-actions"><button type="button" class="fp-btn" data-offer-payment-add="in">+ Wpływ</button><button type="button" class="fp-btn" data-offer-payment-add="out">+ Koszt</button><span class="fp-muted" data-offer-payment-totals></span></div>
    <template data-offer-payment-template>@include('financial-plans.offer-schedule-row', ['rowIndex' => '__INDEX__', 'payment' => []])</template>
</section>
