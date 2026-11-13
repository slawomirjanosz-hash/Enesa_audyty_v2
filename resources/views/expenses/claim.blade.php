<form method="POST" action="{{ route('expenses.claim', $invoice) }}" data-action-icons="false" onsubmit="return confirm('Przypisać tę fakturę do Ciebie?')">
    @csrf
    <input type="hidden" name="confirm" value="1">
    <button class="exp-btn primary" type="submit" title="Przypisz fakturę do siebie">Moja</button>
</form>
