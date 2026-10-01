(() => {
const config=JSON.parse(document.getElementById('audit-finance-config').textContent);
const projectFinanceItems=config.items,projectContractValue=config.contract;
let projectCashflowChart=null,projectCashflowOverview=null;
const localDate=date=>[date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-');
const cashflowState = {mode: 'month', cumulative: true, offset: 0};
function financePeriodKey(date, mode) {
    if (mode === 'day') return date;
    if (mode === 'month') return date.slice(0, 7);
    if (mode === 'year') return date.slice(0, 4);
    const value = new Date(date + 'T12:00:00');
    value.setDate(value.getDate() - ((value.getDay() + 6) % 7));
    return localDate(value);
}
function financePeriodLabel(key, mode) {
    if (mode === 'year') return key;
    if (mode === 'month') return new Date(key + '-01T12:00:00').toLocaleDateString('pl-PL', {month:'short', year:'numeric'});
    if (mode === 'week') return 'Tydz. ' + new Date(key + 'T12:00:00').toLocaleDateString('pl-PL');
    return new Date(key + 'T12:00:00').toLocaleDateString('pl-PL');
}
function groupedFinanceData() {
    const grouped = new Map();
    projectFinanceItems.forEach(item => {
        const key = financePeriodKey(item.date, cashflowState.mode);
        if (!grouped.has(key)) grouped.set(key, {invoice:0, plannedInvoice:0, cost:0, plannedCost:0});
        const row = grouped.get(key);
        if (item.type === 'invoice' && item.status === 'planned') row.plannedInvoice += Number(item.amount);
        else if (item.type === 'cost' && item.status === 'planned') row.plannedCost += Number(item.amount);
        else row[item.type] += Number(item.amount);
    });
    return [...grouped.entries()].sort((a, b) => a[0].localeCompare(b[0]));
}
function renderProjectCashflow() {
    if (typeof Chart === 'undefined') return;
    const emptyState = document.getElementById('project-cashflow-empty');
    const chartContent = document.getElementById('project-cashflow-content');
    if (!projectFinanceItems.length) {
        projectCashflowChart?.destroy(); projectCashflowChart = null;
        projectCashflowOverview?.destroy(); projectCashflowOverview = null;
        if (emptyState) emptyState.hidden = false;
        if (chartContent) chartContent.hidden = true;
        return;
    }
    if (emptyState) emptyState.hidden = true;
    if (chartContent) chartContent.hidden = false;
    const allRows = groupedFinanceData();
    const pageSize = {day:31, week:16, month:12, year:6}[cashflowState.mode];
    const maxOffset = Math.max(0, allRows.length - pageSize);
    cashflowState.offset = Math.max(0, Math.min(cashflowState.offset, maxOffset));
    const rows = allRows.slice(cashflowState.offset, cashflowState.offset + pageSize);
    let invoiceTotal = 0, costTotal = 0, plannedInvoiceTotal = 0, plannedCostTotal = 0;
    if (cashflowState.cumulative) {
        allRows.slice(0, cashflowState.offset).forEach(([, row]) => {invoiceTotal += row.invoice; costTotal += row.cost; plannedInvoiceTotal += row.plannedInvoice; plannedCostTotal += row.plannedCost;});
    }
    const invoice = [], cost = [], plannedInvoice = [], plannedCost = [], result = [], forecast = [], contract = [];
    rows.forEach(([, row]) => {
        if (cashflowState.cumulative) {invoiceTotal += row.invoice; costTotal += row.cost; plannedInvoiceTotal += row.plannedInvoice; plannedCostTotal += row.plannedCost;} else {invoiceTotal = row.invoice; costTotal = row.cost; plannedInvoiceTotal = row.plannedInvoice; plannedCostTotal = row.plannedCost;}
        invoice.push(invoiceTotal); cost.push(costTotal); plannedInvoice.push(plannedInvoiceTotal); plannedCost.push(plannedCostTotal); result.push(invoiceTotal - costTotal); forecast.push(invoiceTotal + plannedInvoiceTotal - costTotal - plannedCostTotal); contract.push(projectContractValue);
    });
    const context = document.getElementById('project-cashflow-chart');
    const range = document.getElementById('cashflow-range');
    if (!context || !range) return;
    projectCashflowChart?.destroy();
    projectCashflowChart = new Chart(context, {
        data: {labels: rows.map(([key]) => financePeriodLabel(key, cashflowState.mode)), datasets: [
            {type:'bar', label:'Faktury', data:invoice, backgroundColor:'rgba(22,163,74,.72)', borderColor:'#15803d', borderWidth:1},
            {type:'bar', label:'Koszty', data:cost, backgroundColor:'rgba(220,38,38,.66)', borderColor:'#b91c1c', borderWidth:1},
            {type:'bar', label:'Planowane faktury', data:plannedInvoice, backgroundColor:'rgba(124,58,237,.35)', borderColor:'#7c3aed', borderWidth:1},
            {type:'bar', label:'Planowane koszty', data:plannedCost, backgroundColor:'rgba(245,158,11,.35)', borderColor:'#d97706', borderWidth:1},
            {type:'line', label:'Zysk / strata', data:result, borderColor:'#2563eb', backgroundColor:'#2563eb', borderWidth:3, pointRadius:4, tension:.25},
            {type:'line', label:'Prognozowany wynik', data:forecast, borderColor:'#7c3aed', backgroundColor:'#7c3aed', borderDash:[7,5], borderWidth:2, pointRadius:2, tension:.25},
            {type:'line', label:'Wartość kontraktu', data:contract, borderColor:'#64748b', borderDash:[3,5], borderWidth:1, pointRadius:0},
        ]},
        options: {responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false}, plugins:{tooltip:{callbacks:{label:context => context.dataset.label + ': ' + Number(context.raw).toLocaleString('pl-PL',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' zł'}}}, scales:{y:{beginAtZero:true,ticks:{callback:value => Number(value).toLocaleString('pl-PL') + ' zł'}},x:{grid:{display:false}}}},
    });
    renderCashflowOverview(allRows, pageSize);
    range.textContent = rows.length ? financePeriodLabel(rows[0][0], cashflowState.mode) + ' – ' + financePeriodLabel(rows.at(-1)[0], cashflowState.mode) : 'Brak danych';
}
function renderCashflowOverview(allRows, pageSize) {
    const canvas = document.getElementById('project-cashflow-overview');
    if (!canvas) return;
    let balance = 0;
    const values = allRows.map(([, row]) => balance += row.invoice + row.plannedInvoice - row.cost - row.plannedCost);
    projectCashflowOverview?.destroy();
    projectCashflowOverview = new Chart(canvas, {
        type:'line',
        data:{labels:allRows.map(([key])=>financePeriodLabel(key,cashflowState.mode)),datasets:[{data:values,borderColor:'#64748b',backgroundColor:'rgba(100,116,139,.12)',fill:true,pointRadius:0,borderWidth:1.5,tension:.2}]},
        options:{responsive:true,maintainAspectRatio:false,onClick:(_,points)=>{if(points[0]){cashflowState.offset=Math.max(0,Math.min(points[0].index-Math.floor(pageSize/2),Math.max(0,allRows.length-pageSize)));renderProjectCashflow();}},plugins:{legend:{display:false},tooltip:{enabled:false}},scales:{x:{display:false},y:{display:false}}}
    });
}
function initProjectCashflow() { if (!projectCashflowChart) renderProjectCashflow(); }
document.querySelectorAll('.cashflow-mode').forEach(button => button.addEventListener('click', () => {
    cashflowState.mode = button.dataset.mode; cashflowState.offset = Number.MAX_SAFE_INTEGER;
    document.querySelectorAll('.cashflow-mode').forEach(item => item.classList.toggle('active', item === button)); renderProjectCashflow();
}));
document.getElementById('cashflow-prev')?.addEventListener('click', () => {cashflowState.offset -= {day:31,week:16,month:12,year:6}[cashflowState.mode];renderProjectCashflow();});
document.getElementById('cashflow-next')?.addEventListener('click', () => {cashflowState.offset += {day:31,week:16,month:12,year:6}[cashflowState.mode];renderProjectCashflow();});
document.getElementById('cashflow-reset')?.addEventListener('click', () => {cashflowState.offset = Number.MAX_SAFE_INTEGER;renderProjectCashflow();});
document.getElementById('cashflow-cumulative')?.addEventListener('click', event => {cashflowState.cumulative = !cashflowState.cumulative;event.currentTarget.classList.toggle('active',cashflowState.cumulative);event.currentTarget.textContent = cashflowState.cumulative ? 'Narastająco' : 'W okresie';renderProjectCashflow();});
function syncFinanceEntryForm(form) {
    const isInvoice = form.querySelector('.finance-entry-type')?.value === 'invoice';
    form.querySelectorAll('[data-finance-supplier-field],[data-finance-cost-group]').forEach(field => {
        field.hidden = isInvoice;
        field.querySelectorAll('input,select').forEach(control => control.disabled = isInvoice);
    });
    form.querySelectorAll('[data-finance-invoice-note]').forEach(note => note.hidden = !isInvoice);
}
document.querySelectorAll('.finance-entry-form').forEach(form => {
    syncFinanceEntryForm(form);
    form.querySelector('.finance-entry-type')?.addEventListener('change', () => syncFinanceEntryForm(form));
});
const financeSearch = document.getElementById('finance-live-search');
const financeRows = [...document.querySelectorAll('#finance-register-table [data-finance-type]')];
let activeFinanceFilter = 'all';
function normalizeFinanceSearch(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}
function applyFinanceFilters() {
    const query = normalizeFinanceSearch(financeSearch?.value);
    let visible = 0;
    let visibleAmount = 0;
    financeRows.forEach(row => {
        const matchesType = activeFinanceFilter === 'all' || row.dataset.financeType === activeFinanceFilter;
        const searchableText = normalizeFinanceSearch((row.dataset.financeSearch || '') + ' ' + (row.dataset.financeStatusSearch || ''));
        const matchesSearch = !query || searchableText.includes(query);
        row.classList.toggle('finance-row-hidden', !matchesType || !matchesSearch);
        if (matchesType && matchesSearch) {
            visible++;
            visibleAmount += Number(row.dataset.financeSortAmount || 0);
        }
    });
    const counter = document.getElementById('finance-search-count');
    const empty = document.getElementById('finance-search-empty');
    if (counter) {
        const amount = new Intl.NumberFormat('pl-PL', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(visibleAmount);
        counter.textContent = visible + ' z ' + financeRows.length + ' poz. · Suma: ' + amount + ' zł';
    }
    if (empty) empty.hidden = visible !== 0;
}
document.querySelectorAll('.register-tab').forEach(button => button.addEventListener('click', () => {
    activeFinanceFilter = button.dataset.financeFilter;
    document.querySelectorAll('.register-tab').forEach(item => item.classList.toggle('active', item === button));
    applyFinanceFilters();
}));
financeSearch?.addEventListener('input', applyFinanceFilters);
document.getElementById('finance-select-all')?.addEventListener('change', event => {
    financeRows.filter(row => ! row.classList.contains('finance-row-hidden')).forEach(row => {
        const checkbox = row.querySelector('.finance-entry-check');
        if (checkbox) checkbox.checked = event.currentTarget.checked;
    });
});

document.querySelectorAll('.project-async-status[data-kind="finance"]').forEach(select=>select.addEventListener('change',async()=>{
    select.disabled=true;
    try{
        const response=await fetch(select.dataset.url,{method:'PATCH',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({status:select.value})});
        if(!response.ok)throw new Error('Nie udało się zapisać statusu.');
        location.reload();
    }catch(error){select.value=select.dataset.current;alert(error.message);select.disabled=false;}
}));
new MutationObserver(()=>{if(document.getElementById('aw-finances').classList.contains('active'))initProjectCashflow();}).observe(document.getElementById('aw-finances'),{attributes:true,attributeFilter:['class']});
if(document.getElementById('aw-finances').classList.contains('active'))initProjectCashflow();
})();
