<style>
/* Local SVG styles: the chart must not depend on an external stylesheet. */
#aw-schedule .card{background:#fff;border:1px solid #e3dfd7;border-radius:11px;padding:18px;margin-bottom:14px;min-width:0}
#aw-schedule .card>p{font-size:13px;line-height:1.6;white-space:normal;margin:8px 0 14px;color:#65736b}
#aw-schedule .legend{display:flex;flex-wrap:wrap;gap:12px;font-size:12px;align-items:center}
#aw-schedule .legend>span{display:inline-flex;align-items:center;gap:5px}
#aw-schedule .dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#7c3aed}
#aw-schedule .gantt-list-table{width:100%;border-collapse:collapse;font-size:13px}
#aw-schedule .gantt-list-table th,#aw-schedule .gantt-list-table td{padding:11px 12px;text-align:left;border-bottom:1px solid #e7e9e6}
#aw-schedule .gantt-list-table th{background:#f5f7f5;color:#52635b}
#aw-schedule .linked-audit-task{outline:3px solid #d97706;outline-offset:-3px;background:#fff5d6!important}
#aw-schedule .gantt{background:#fff;font-family:inherit}
#aw-schedule .gantt .grid-background{fill:#fff}
#aw-schedule .gantt .grid-header{fill:#f5f7f5;stroke:#e0e5e1;stroke-width:1}
#aw-schedule .gantt .grid-row{fill:#fff}
#aw-schedule .gantt .grid-row:nth-child(even){fill:#f9faf9}
#aw-schedule .gantt .row-line{stroke:#e8ece8}
#aw-schedule .gantt .tick{stroke:#e0e5e1;stroke-width:.3}
#aw-schedule .gantt .tick.thick{stroke-width:1}
#aw-schedule .gantt .upper-text,#aw-schedule .gantt .lower-text{fill:#52635b;font-size:12px;text-anchor:middle}
#aw-schedule .gantt .bar{stroke-width:0}
#aw-schedule .gantt .bar-label{fill:#fff;dominant-baseline:central;text-anchor:middle;font-size:12px}
#aw-schedule .gantt .bar-label.big{fill:#243f31;text-anchor:start}
#aw-schedule .gantt .handle{fill:#ddd;opacity:0;cursor:ew-resize}
#aw-schedule .gantt .bar-wrapper:hover .handle{opacity:1}
#aw-schedule .gantt .arrow{fill:none;stroke:#718078;stroke-width:1.4}
#aw-schedule .gantt-container{position:relative;background:#fff}
#aw-schedule .popup-wrapper{position:absolute;top:0;left:0;padding:10px;background:#fff;color:#243f31;border:1px solid #ddd;border-radius:7px;box-shadow:0 3px 12px #0002;z-index:20}
#aw-schedule .popup-wrapper:empty{display:none}
</style>
<style>
#gantt-task-modal .grid2,#gantt-import-modal .grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
#gantt-task-modal .field,#gantt-import-modal .field{display:flex;flex-direction:column;gap:6px;min-width:0}
#gantt-task-modal .field.full,#gantt-import-modal .field.full{grid-column:1/-1}
#gantt-task-modal label,#gantt-import-modal label{font-size:13px;font-weight:600;color:#52635b}
#gantt-task-modal input,#gantt-task-modal select,#gantt-task-modal textarea,#gantt-import-modal input{box-sizing:border-box;width:100%;min-width:0;border:1px solid #d6ded8;border-radius:7px;background:#fff;color:#243f31;padding:10px 12px;font:inherit;font-size:14px}
#gantt-task-modal textarea{min-height:90px;resize:vertical}
#gantt-task-modal input:focus,#gantt-task-modal select:focus,#gantt-task-modal textarea:focus{outline:2px solid var(--green);outline-offset:1px}
#gantt-task-modal .btn,#gantt-import-modal .btn{border:1px solid var(--green);border-radius:7px;background:var(--green);color:#fff;font:inherit;font-size:13px;font-weight:700;padding:10px 16px;cursor:pointer}
#gantt-task-modal .btn-soft,#gantt-import-modal .btn-soft{background:#fff;color:var(--green)}
#aw-schedule .gantt-list-table{min-width:850px}
#aw-schedule .gantt-list-table tr.overdue-row{background:#fff!important}
#aw-schedule .gantt-list-table .mini-actions{gap:5px}
#aw-schedule .gantt-list-table .mini-btn{width:32px;height:32px;border-radius:6px}
#aw-schedule .progress-wrap{min-width:110px;gap:8px}
#aw-schedule .progress-wrap input[type=range]{width:90px;accent-color:var(--green);cursor:pointer}
#aw-schedule .gantt-bulk-toolbar{padding:12px 0;align-items:center;flex-wrap:wrap}
@media(max-width:600px){#gantt-task-modal .grid2,#gantt-import-modal .grid2{grid-template-columns:1fr}#gantt-task-modal .project-modal-box{padding:16px}}
</style>
