<style>
    .ta-locale { display:flex; align-items:center; gap:.25rem; font-size:.8rem; }
    .ta-locale-link { padding:.25rem .5rem; border-radius:.5rem; font-weight:600; letter-spacing:.04em; color:rgb(var(--gray-500)); transition:background .15s,color .15s; }
    .ta-locale-link:hover { color:rgb(var(--gray-900)); background:rgba(var(--gray-500),.1); }
    .dark .ta-locale-link:hover { color:#fff; }
    .ta-locale-link.is-active { color:rgb(var(--primary-600)); background:rgba(var(--primary-500),.12); }
    .dark .ta-locale-link.is-active { color:rgb(var(--primary-400)); }
    .ta-agency-badge { margin:0 1rem 1rem; padding:.75rem; border-radius:.75rem; border:1px solid rgba(var(--gray-200),1); background:rgba(var(--gray-50),1); font-size:.75rem; }
    .dark .ta-agency-badge { border-color:rgba(255,255,255,.1); background:rgba(255,255,255,.04); }
    .ta-agency-name { font-weight:600; color:rgb(var(--gray-950)); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .dark .ta-agency-name { color:#fff; }
    .ta-agency-role { color:rgb(var(--gray-500)); }
    .fi-simple-main { border-top:4px solid rgb(var(--primary-500)); }
    .fi-sidebar-header { background:linear-gradient(135deg, rgba(var(--primary-500),.10), transparent); }
    .ta-map { width:100%; height:260px; border:0; border-radius:.75rem; }
    .ta-thumb-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:.5rem; }
</style>
