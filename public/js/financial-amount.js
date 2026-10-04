export function formatAmount(value) {
    const text = String(value).trim().replace(/[\s\u00a0\u202f]/g, '').replace(/zł$/i, '').replace(',', '.');
    if (!text) return '';
    const match = text.match(/^(-?)(\d+)(?:\.(\d{1,2}))?$/);
    if (!match) return value;
    const integer = match[2].replace(/^0+(?=\d)/, '');
    return `${match[1]}${integer.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')},${(match[3] || '').padEnd(2, '0')} zł`;
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-financial-amount]').forEach(input => {
        input.addEventListener('blur', () => { input.value = formatAmount(input.value); });
        input.addEventListener('focus', () => {
            input.value = input.value.replace(/\s*zł$/i, '').replace(/[\s\u00a0\u202f]/g, '');
        });
    });
}
