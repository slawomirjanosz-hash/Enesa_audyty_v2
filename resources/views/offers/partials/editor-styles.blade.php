@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<style>
.offer-section-collapsed > :not(.ed-card-header) { display:none !important; }
.offer-section-photos{padding:10px 14px;border-top:1px solid #e5e1d8}.offer-section-photos [role=status]{margin-left:8px;font-size:12px}.offer-section-photo-list{display:flex;flex-wrap:wrap;gap:8px}.offer-section-photo-list figure{margin:8px 0 0;display:grid;gap:4px}.offer-section-photo-list img{width:104px;height:74px;object-fit:contain;background:#f4f1ea;border-radius:6px}
#offer-form:has(input[name="show_total"][type="checkbox"]:not(:checked)) .section-price-total { display: none; }
/* ── Editor topbar ─────────────────────────────────── */
#editor-topbar {
    position: sticky;
    top: var(--topbar, 60px);
    z-index: 50;
    background: #fff;
    border-bottom: 1px solid #E5E1D8;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 20px;
    gap: 14px;
    flex-wrap: wrap;
    margin: -20px -20px 20px -20px;
}
.etb-left  { display: flex; align-items: center; gap: 10px; }
.etb-right { display: flex; align-items: center; gap: 10px; }
.toggle-wrap { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.toggle-wrap input { display: none; }
.toggle-track {
    width: 40px; height: 22px; background: #D0CCC0;
    border-radius: 11px; position: relative; transition: background .2s;
}
.toggle-track::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 16px; height: 16px; background: #fff; border-radius: 50%;
    transition: left .2s; box-shadow: 0 1px 3px rgba(0,0,0,.25);
}
.toggle-wrap input:checked + .toggle-track { background: var(--green); }
.toggle-wrap input:checked + .toggle-track::after { left: 21px; }
.toggle-label { font-family: 'Manrope', sans-serif; font-size: 12px; font-weight: 600; color: #555; }
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--green); color: #F5F0E8; border: none; border-radius: 8px;
    padding: 8px 16px; font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 700;
    text-decoration: none; cursor: pointer; transition: background .15s; white-space: nowrap;
}
.btn-primary:hover { background: #143d2d; color: #F5F0E8; }
.btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    background: #fff; color: #333; border: 1px solid #D0CCC0; border-radius: 8px;
    padding: 7px 14px; font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 600;
    text-decoration: none; cursor: pointer; transition: background .15s;
}
.btn-secondary:hover { background: #F4F1EA; }
.badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; }
.badge-blue { background:#DBEAFE; color:#1D4ED8; }
.ed-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; margin-bottom:16px; }
.ed-card-header { padding:13px 20px; border-bottom:1px solid #F0EDE6; background:#FAFAF6; display:flex; align-items:center; gap:10px; }
.ed-card.type-text { border-left: 4px solid var(--green); }
.ed-card.type-text .ed-card-header { background: #F0F7F3; }
.ed-card.type-price { border-left: 4px solid #D97706; }
.ed-card.type-price .ed-card-header { background: #FFF8E8; }
.ed-card.type-deleg { border-left: 4px solid #2563EB; }
.ed-card.type-deleg .ed-card-header { background: #EFF6FF; }
.ed-card-header > i { font-size:17px; color:var(--green); }
.ed-card-title { font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; color:#1A1A1A; }
.ed-card-body { padding:20px; }
.doc-header-bar { background:var(--green); color:#fff; padding:14px 22px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.doc-header-bar .offer-num { font-family:'Lato',sans-serif; font-size:15px; font-weight:900; letter-spacing:.04em; }
.doc-header-bar .doc-date  { font-size:12px; opacity:.8; }
.doc-parties { display:grid; grid-template-columns:1fr 1fr; border-bottom:1px solid #F0EDE6; }
.doc-party { padding:18px 22px; }
.doc-party:first-child { border-right:1px solid #F0EDE6; }
.doc-party-label { font-family:'Manrope',sans-serif; font-size:10px; font-weight:700; color:#999; text-transform:uppercase; letter-spacing:.07em; margin-bottom:8px; }
.doc-party-name  { font-family:'Manrope',sans-serif; font-size:15px; font-weight:700; color:#1A1A1A; margin-bottom:4px; }
.doc-party-line  { font-family:'Lato',sans-serif; font-size:12px; color:#555; line-height:1.7; }
.doc-title-wrap  { padding:16px 22px; }
.doc-title-input { width:100%; border:none; outline:none; font-family:'Manrope',sans-serif; font-size:18px; font-weight:700; color:var(--green); background:transparent; border-bottom:2px dashed #94C4B0; padding:4px 0; transition:border-color .15s; }
.doc-title-input:focus { border-color:var(--green); }
.doc-title-input::placeholder { color:#bbb; font-weight:400; }
.rte-toolbar { display:flex; gap:4px; padding:8px 16px; border-bottom:1px solid #F0EDE6; background:#FAFAF6; flex-wrap:wrap; }
.rte-btn { min-width:30px; height:28px; padding:0 8px; background:#fff; border:1px solid #D0CCC0; border-radius:5px; font-size:13px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-family:'Lato',sans-serif; color:#333; transition:background .12s; }
.rte-btn:hover { background:#F0EDE6; }
.rich-editor { min-height:90px; padding:14px 20px; font-family:'Lato',sans-serif; font-size:14px; color:#1A1A1A; line-height:1.8; outline:none; }
.rich-editor:empty::before { content:attr(data-placeholder); color:#bbb; pointer-events:none; }
.rich-editor ul, .rich-editor ol { padding-left:20px; }
.ql-editor table { width:100%; table-layout:fixed; border-collapse:collapse; }
.ql-editor th, .ql-editor td { min-width:40px; }
body.table-column-resizing, body.table-column-resizing * { cursor:col-resize !important; user-select:none !important; }
.btn-section-action { width:30px; height:28px; padding:0; border:1px solid #D0CCC0; border-radius:6px; background:#fff; color:var(--green); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; }
.btn-section-action:hover { background:#F0F7F3; }
.btn-section-action:disabled { opacity:.35; cursor:not-allowed; }
.btn-section-clear { width:auto; padding:0 9px; gap:4px; color:#8A5A00; border-color:#E8C77B; }
.section-name-input { flex:1; border:none; outline:none; background:transparent; font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; color:#1A1A1A; border-bottom:1px dashed #bbb; padding:2px 4px; }
.section-price-total { margin-left:auto; padding:5px 10px; border-radius:7px; background:#EAF4EF; color:var(--green); font-size:11px; font-weight:800; white-space:nowrap; }
.price-table { width:100%; border-collapse:collapse; font-size:13px; table-layout:fixed; }
.price-table td, .price-table th { overflow:hidden; }
.price-table th { font-family:'Manrope',sans-serif; font-size:10px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.05em; padding:8px 10px; border-bottom:2px solid #E5E1D8; background:#FAFAF6; white-space:nowrap; text-align:left; }
.price-table td { padding:6px 6px; border-bottom:1px solid #F0EDE6; vertical-align:middle; }
.price-table tr:last-child td { border-bottom:none; }
.col-drag  { width:52px; }
.row-order-controls { display:flex; align-items:center; justify-content:center; gap:2px; }
.btn-row-move { width:21px; height:24px; padding:0; border:1px solid #D8D4C8; border-radius:4px; background:#fff; color:var(--green); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
.btn-row-move:hover { background:#EAF4EF; border-color:#94C4B0; }
.col-opis  { width:auto; min-width:180px; }
.col-jedn  { width:100px; }
.col-ilosc { width:80px; }
.col-cena  { width:110px; }
.col-netto { width:110px; }
.col-narzut{ width:120px; }
.col-del   { width:40px; }
.cell-input { width:100%; border:1px solid #D8D4C8; border-radius:5px; padding:6px 9px; font-size:13px; font-family:'Lato',sans-serif; color:#1A1A1A; background:#fff; outline:none; transition:border-color .12s, box-shadow .12s; box-sizing:border-box; }
.cell-input::placeholder { color: #B0AA9E; }
.cell-input:hover { border-color:#94C4B0; }
.cell-input:focus { border-color:var(--green); box-shadow: 0 0 0 2px rgba(26,77,58,0.08); }
.cell-readonly { font-family:'Lato',sans-serif; font-size:13px; color:#333; font-weight:700; padding:5px 8px; }
.btn-add-row { background:none; border:1px dashed #94C4B0; color:var(--green); border-radius:6px; padding:6px 14px; font-size:12px; font-family:'Manrope',sans-serif; font-weight:700; cursor:pointer; transition:background .12s; }
.btn-add-row:hover { background:#F0F7F3; }
.btn-del-row { background:none; border:none; color:#DC2626; cursor:pointer; font-size:16px; padding:4px; border-radius:4px; display:flex; align-items:center; justify-content:center; }
.btn-del-row:hover { background:#FEE2E2; }
.btn-del-section { margin-left:auto; background:none; border:1px solid #FCA5A5; color:#B91C1C; border-radius:6px; padding:4px 10px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; cursor:pointer; transition:background .12s; }
.btn-del-section:hover { background:#FEE2E2; }
.btn-add-section { display:inline-flex; align-items:center; gap:6px; border:1px dashed #94C4B0; color:var(--green); background:none; border-radius:8px; padding:8px 16px; font-size:12px; font-weight:700; font-family:'Manrope',sans-serif; cursor:pointer; transition:background .12s; }
.btn-add-section:hover { background:#F0F7F3; }

.deleg-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
.field-label { display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:4px; font-family:'Manrope',sans-serif; }
.field-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:8px 10px; font-size:13px; font-family:'Lato',sans-serif; color:#1A1A1A; outline:none; transition:border-color .15s; box-sizing:border-box; }
.field-input:focus { border-color:var(--green); background:#fff; }
.input-group { display:flex; }
.input-group .field-input { border-radius:7px 0 0 7px; border-right:none; }
.input-suffix { background:#F0EDE6; border:1px solid #D0CCC0; border-radius:0 7px 7px 0; padding:8px 10px; font-size:12px; color:#666; white-space:nowrap; }
.summary-row { display:flex; justify-content:space-between; padding:9px 16px; font-size:13px; }
.summary-row.sub { background:#fff; border-bottom:1px solid #F0EDE6; }
.summary-row.markup { background:#FFFBEB; border-bottom:1px solid #FDE68A; }
.summary-row.total { background:var(--green); color:#fff; border-radius:0 0 10px 10px; }
.summary-label { font-family:'Manrope',sans-serif; font-weight:600; }
.summary-value { font-family:'Lato',sans-serif; font-weight:900; font-size:15px; }
.summary-row.total .summary-value { font-size:20px; }
.markup-bar { background:#FFFBEB; border:1px solid #FDE68A; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.markup-bar .field-label { color:#92400E; }
</style>
@endpush