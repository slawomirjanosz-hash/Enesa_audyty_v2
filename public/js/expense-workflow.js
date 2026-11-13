document.addEventListener('change', event => {
    const method = event.target.closest('[data-settlement-method]');
    if (!method) return;

    const amount = method.form.elements.namedItem('settlement_amount');
    amount.value = method.value === 'none' ? '0' : (method.value ? method.dataset.gross : '');
});
