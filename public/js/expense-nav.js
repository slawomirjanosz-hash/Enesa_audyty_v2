const expenseLink = document.querySelector('[data-expense-alert]');
if (expenseLink) {
    const badge = expenseLink.querySelector('[data-expense-count]');
    const label = expenseLink.querySelector('[data-expense-label]');
    const refreshExpenses = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(expenseLink.dataset.expenseAlert, {headers: {Accept: 'application/json'}});
            if (!response.ok || response.redirected) return;
            const {count} = await response.json();
            if (!Number.isInteger(count)) return;
            badge.hidden = !count;
            badge.textContent = count;
            label.classList.toggle('expense-nav-pulse', count > 0 && label.hasAttribute('data-accounting'));
            expenseLink.href = count ? expenseLink.dataset.pendingUrl : expenseLink.dataset.indexUrl;
        } catch {
            // Keep the most recent count until the next refresh.
        }
    };
    setInterval(refreshExpenses, 30000);
    document.addEventListener('visibilitychange', refreshExpenses);
}
