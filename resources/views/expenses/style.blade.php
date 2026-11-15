@push('styles')
<style>
.exp-head,.exp-tabs,.exp-actions,.exp-filters{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px}
.exp-head{justify-content:space-between}
.exp-head h1{margin:0}
.exp-muted{color:#64748b;font-size:13px}
.exp-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 16px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:var(--green,#1f3a5f);text-decoration:none;cursor:pointer;font:inherit}
.exp-btn.primary,.exp-tabs .active{background:var(--green,#1f3a5f);color:#fff}
.exp-btn.danger{color:#b91c1c;border-color:#fecaca;background:#fff1f2}
.exp-card{box-sizing:border-box;max-width:100%;margin-bottom:20px;padding:20px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
.exp-table-wrap.exp-card{padding:0;overflow-x:auto}
.exp-table{width:100%;table-layout:fixed;border-collapse:collapse;font-size:12px}
.exp-table th,.exp-table td{padding:9px 6px;text-align:left;vertical-align:top;overflow-wrap:anywhere;border-bottom:1px solid #e2e8f0}
.exp-table th{background:#f8fafc}
.exp-table th:last-child{width:24%}
.exp-number{font-variant-numeric:tabular-nums}
.exp-table .app-table-sort{display:inline-flex;align-items:center;gap:6px;max-width:100%;padding:0;border:0;background:transparent;color:inherit;font:inherit;text-align:left;overflow-wrap:anywhere}
.exp-table .app-table-sort-marker{opacity:.6;font-size:12px;white-space:nowrap}
.exp-table .exp-btn{min-height:32px;max-width:100%;padding:5px;font-size:11px}
.exp-table .exp-icon-btn{width:32px;flex:none}
.exp-table .exp-btn i{display:inline-block;width:16px;flex:0 0 16px;text-align:center;font-size:16px}
.exp-badge{display:inline-block;padding:4px 8px;border-radius:6px;background:#eef2ff}
.exp-badge.exp-badge-success,.exp-message{background:#ecfdf5;color:#166534}
.exp-row-actions,.exp-operation{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.exp-operation{min-width:0}
.exp-delete-form{display:inline-block}
.exp-filters{align-items:flex-end}
.exp-filters>label:not(:nth-of-type(2)){flex:1;min-width:min(180px,100%)}
.exp-filters .exp-btn{min-height:42px}
.exp-field{display:flex;flex-direction:column;gap:6px;font-size:13px}
.exp-field[hidden]{display:none}
.exp-field input,.exp-field select,.exp-field textarea,.exp-filters input,.exp-filters select,.exp-operation select{
    box-sizing:border-box;width:100%;min-width:0;padding:10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:inherit;font:inherit
}
.exp-operation select{width:auto;max-width:100%;padding:5px}
.exp-message{margin-bottom:16px;padding:14px;border-radius:8px}
.exp-message.error{background:#fef2f2;color:#991b1b}
#expense-form fieldset{min-width:0}
#expense-form fieldset[disabled]{opacity:.8}
#expense-form [hidden]{display:none!important}
#expense-form .exp-grid{display:grid;gap:16px;max-width:36rem}
#expense-form .exp-field>label{display:flex;flex-direction:column;gap:6px}
#expense-form .exp-grid small{color:#64748b;font-size:12px}
.exp-note-dialog{inset:0;margin:auto;width:min(28rem,calc(100% - 24px));color:inherit}
.exp-note-dialog::backdrop{background:rgba(15,23,42,.45)}
.exp-note-dialog .exp-actions{justify-content:flex-end;margin:12px 0 0}
.exp-note-dialog .exp-btn{font-size:inherit}
@media(max-width:900px){.exp-table th,.exp-table td{padding-inline:4px}.exp-filters .exp-btn{padding:8px 12px}}
@media(max-width:767px){.exp-head h1{font-size:22px}.exp-card{padding:16px}.exp-table th:last-child{width:29%}}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/expense-workflow.js') }}?v=3" defer></script>
@endpush
