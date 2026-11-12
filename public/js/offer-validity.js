const offerDates = document.getElementById('offer-form');
const issuedOn = offerDates?.elements.created_at;
const validUntil = offerDates?.elements.valid_until;

if (issuedOn && validUntil) {
    function defaultExpiry() {
        const date = new Date(issuedOn.value + 'T00:00:00Z');
        if (Number.isNaN(date.getTime())) return '';
        date.setUTCDate(date.getUTCDate() + 30);
        return date.toISOString().slice(0, 10);
    }

    let previousDefault = defaultExpiry();
    issuedOn.addEventListener('change', () => {
        const nextDefault = defaultExpiry();
        if (nextDefault && (!validUntil.value || validUntil.value === previousDefault)) {
            validUntil.value = nextDefault;
        }
        previousDefault = nextDefault;
        validUntil.min = issuedOn.value;
    });
    validUntil.min = issuedOn.value;
}
