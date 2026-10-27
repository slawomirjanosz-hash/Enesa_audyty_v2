<div class="fp-payment-row" data-offer-payment-row>
    <label>Rodzaj<select name="offer_schedule[{{ $rowIndex }}][direction]"><option value="in" @selected(($payment['direction'] ?? 'in') === 'in')>Wpływ</option><option value="out" @selected(($payment['direction'] ?? '') === 'out')>Koszt</option></select></label>
    <label>Termin płatności<input type="date" name="offer_schedule[{{ $rowIndex }}][date]" value="{{ $payment['date'] ?? '' }}" min="2020-01-01" max="2100-12-31" required></label>
    <label>Kwota netto (PLN)<input type="number" name="offer_schedule[{{ $rowIndex }}][amount]" value="{{ $payment['amount'] ?? '' }}" min="0" max="999999999.99" step="0.01" required></label>
    <button type="button" class="fp-btn danger" data-offer-payment-remove title="Usuń płatność" aria-label="Usuń płatność">@include('financial-plans.action-icon', ['icon' => 'trash'])</button>
</div>
