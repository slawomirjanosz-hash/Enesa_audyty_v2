export function convertAmount(value, rate, source) {
    if (value === '' || rate === '') return '';
    const amount = Number(String(value).replace(',', '.'));
    const factor = 1 + Number(rate) / 100;
    if (!Number.isFinite(amount) || amount < 0 || !Number.isFinite(factor) || factor <= 0) return '';
    return (Math.round((source === 'net_amount' ? amount * factor : amount / factor) * 100 + 1e-7) / 100).toFixed(2);
}

if (typeof document !== 'undefined') {
    const form = document.getElementById('expense-form');
    if (form) {
        const field = name => form.elements.namedItem(name);
        let source = null;
        const recalculate = () => {
            if (!source || field('vat_rate').value === '') return;
            const target = source === 'net_amount' ? 'gross_amount' : 'net_amount';
            field(target).value = convertAmount(field(source).value, field('vat_rate').value, source);
        };
        for (const name of ['net_amount', 'gross_amount']) {
            field(name).addEventListener('input', () => { source = name; recalculate(); });
        }
        field('vat_rate').addEventListener('change', recalculate);
        document.getElementById('expense-supplier').addEventListener('change', event => {
            const option = event.target.selectedOptions[0];
            if (!option?.value) return;
            field('supplier_name').value = option.dataset.name;
            field('supplier_nip').value = option.dataset.nip;
        });
        const mode = field('owner_mode');
        const updateOwner = () => {
            form.querySelector('[data-owner-user]').hidden = mode.value !== 'user';
            form.querySelector('[data-owner-manual]').hidden = mode.value !== 'manual';
            field('owner_id').disabled = mode.value !== 'user';
            field('owner_name').disabled = mode.value !== 'manual';
        };
        if (mode) { mode.addEventListener('change', updateOwner); updateOwner(); }
    }
}
