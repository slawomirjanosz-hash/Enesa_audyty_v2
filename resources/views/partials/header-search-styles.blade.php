@once
<style>
.app-search-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}
.app-search-toolbar{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:12px;padding-bottom:19px;max-width:100%}
.app-header-search{position:relative;flex:0 1 300px;min-width:0;width:300px;max-width:100%}
.app-header-search input[type=search]{box-sizing:border-box;width:100%;height:38px;font-size:13px;padding:8px 12px 8px 34px;border-radius:7px;border:1px solid #D0CCC0;outline:none;background:#fff;font-family:'Lato',sans-serif}
.app-header-search input:focus{border-color:var(--green)}
.app-header-search>i{position:absolute;left:10px;top:19px;transform:translateY(-50%);color:#aaa;font-size:16px;pointer-events:none}
.app-header-search-result{position:absolute;top:42px;left:0;min-height:15px;color:#777;font-size:11px;white-space:nowrap}
.app-search-toolbar>.btn-add,.app-search-toolbar>.btn-primary,.app-search-toolbar>.view-toggle{box-sizing:border-box;height:38px;margin:0;display:inline-flex;align-items:center;flex-shrink:0}
@media(max-width:700px){.app-search-toolbar{width:100%;justify-content:flex-start;padding-bottom:0}.app-header-search{order:-1;flex-basis:100%;width:100%;margin-bottom:19px}}
</style>
<style>
.app-header-search{margin:0}.app-view-toggle{display:inline-flex;gap:4px;padding:4px;border-radius:7px;background:#F4F1EA;height:38px;box-sizing:border-box;flex-shrink:0}.app-view-toggle button{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border:0;border-radius:5px;padding:0;background:transparent;color:#6b7a70;cursor:pointer}.app-view-toggle button.active{background:var(--green);color:#fff}
.app-search-toolbar>button,.app-search-toolbar>a{box-sizing:border-box;height:38px;display:inline-flex;align-items:center;justify-content:center}
</style>
<script type="module" src="{{ asset('js/list-search.js') }}?v=1"></script>
@endonce
