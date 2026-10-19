// Shared browser projection of IsoPlantCalculations. Factors are supplied by PHP.
(function (root) {
    const number = value => {
        if (value === null || value === undefined || typeof value === 'boolean' || typeof value === 'object') return null;
        const text = String(value).trim().replace(/[ \u00a0\u202f]/g, '').replace(',', '.');
        if (!/^[+]?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/.test(text)) return null;
        const result = Number(text);
        return Number.isFinite(result) && result >= 0 && result <= 1e12 ? result : null;
    };
    const calculate = (rows, factors, unknown = false) => {
        if (unknown) return {value: null, errors: {}, detail: 'Zużycie oznaczono jako nieznane. Nie podajemy sumy częściowej.'};
        let total = 0, count = 0; const errors = {};
        (Array.isArray(rows) ? rows : []).forEach((row, index) => {
            if (!row || typeof row !== 'object' || Array.isArray(row)) { errors[index] = `Wiersz ${index + 1} — nieprawidłowe dane nośnika.`; return; }
            if (!Object.entries(row).some(([key, value]) => key !== 'id' && value !== null && value !== undefined && String(value).trim() !== '')) return;
            count++;
            const carrier = typeof row.c0 === 'string' ? row.c0.trim() : '', unit = typeof row.c2 === 'string' ? row.c2.trim() : '';
            const quantity = number(row.c1), factor = factors[carrier]?.[unit];
            const reason = quantity === null ? 'brak poprawnej ilości (liczba nieujemna, maks. 1 000 000 000 000)' : factor === undefined ? 'nieobsługiwane przeliczenie; wybierz obsługiwaną jednostkę energii lub zweryfikuj nośnik i jednostkę' : null;
            if (reason) errors[index] = `Wiersz ${index + 1} — ${carrier || 'brak nośnika'} / ${unit || 'brak jednostki'}: ${reason}.`;
            else total += quantity * factor;
        });
        const value = !unknown && count > 0 && !Object.keys(errors).length ? Number((total / 1000).toPrecision(15)) : null;
        const detail = value !== null ? 'Suma wszystkich pozycji. Przeliczenie jednostek energii jest niezależne od nośnika. Wartości opałowe masy i objętości są orientacyjne i wymagają weryfikacji.' : (Object.values(errors).join(' ') || (unknown ? 'Zużycie oznaczono jako nieznane.' : 'Uzupełnij tabelę nośników.')) + ' Nie podajemy sumy częściowej.';
        return {value, errors, detail};
    };
    const fossil = (heat, fuels, unknown = false) => {
        if (unknown) fuels = null;
        if (heat === 'brak') return 'nie';
        if (heat === 'siec') return 'do potwierdzenia';
        if (!['wlasne','oba'].includes(heat)) return null;
        if (Array.isArray(fuels) && fuels.some(value => ['gaz ziemny','olej','węgiel'].includes(value))) return 'tak';
        if (heat === 'oba') return 'do potwierdzenia';
        return Array.isArray(fuels) && fuels.length && !unknown ? 'nie' : null;
    };
    root.EnesaPlantEnergy = {number, calculate, fossil};
})(typeof window === 'undefined' ? globalThis : window);
