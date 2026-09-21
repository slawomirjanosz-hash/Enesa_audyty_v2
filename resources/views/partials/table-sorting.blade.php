<style>
.app-table-sort{display:inline-flex;align-items:center;gap:6px;padding:0;border:0;background:transparent;color:inherit;font:inherit;text-transform:inherit;text-align:inherit;cursor:pointer}
.app-table-sort:hover{color:var(--green,#1a4d3a)}.app-table-sort:focus-visible{outline:2px solid var(--green,#1a4d3a);outline-offset:4px;border-radius:2px}
.app-table-sort-marker{opacity:.6;font-size:12px;white-space:nowrap}th[aria-sort] .app-table-sort-marker{opacity:1}
@media print{.app-table-sort-marker{display:none}}
</style>
<script type="module" src="{{asset('js/table-sort.js')}}?v={{filemtime(public_path('js/table-sort.js'))}}"></script>
