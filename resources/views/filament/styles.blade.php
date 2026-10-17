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
    .ta-lm-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:1rem; }
    .ta-lm-card { display:block; border-radius:.9rem; overflow:hidden; border:1px solid rgba(var(--gray-200),1); background:#fff; transition:transform .15s, box-shadow .15s; }
    .dark .ta-lm-card { background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.08); }
    .ta-lm-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.08); }
    .ta-lm-img { position:relative; height:130px; background:rgba(var(--gray-100),1) center/cover no-repeat; }
    .ta-lm-badge { position:absolute; top:.6rem; left:.6rem; background:rgb(var(--danger-600)); color:#fff; font-size:.65rem; font-weight:700; letter-spacing:.06em; padding:.2rem .5rem; border-radius:.4rem; }
    .ta-lm-body { padding:.8rem .9rem; }
    .ta-lm-title { font-weight:600; line-height:1.3; }
    .ta-lm-meta { font-size:.8rem; color:rgb(var(--gray-500)); }
    .ta-lm-foot { display:flex; justify-content:space-between; align-items:baseline; margin-top:.5rem; }
    .ta-lm-price { font-weight:700; color:rgb(var(--danger-600)); }
</style>
