<style>
    .sta-lib-bar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; margin-bottom: .9rem; }
    .sta-lib-search { flex: 1; min-width: 14rem; }
    .sta-lib-folder { min-width: 11rem; }
    .sta-lib-count { font-size: .8rem; color: rgb(var(--gray-500)); }
    .sta-lib-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: .6rem; max-height: 58vh; overflow: auto; padding: 2px; }
    .sta-lib-item { position: relative; display: block; aspect-ratio: 1; overflow: hidden; border-radius: .6rem; border: 2px solid transparent; background: rgb(var(--gray-100)); cursor: pointer; padding: 0; }
    .dark .sta-lib-item { background: rgb(var(--gray-800)); }
    .sta-lib-item img { width: 100%; height: 100%; object-fit: cover; transition: transform .25s; }
    .sta-lib-item:hover img { transform: scale(1.04); }
    .sta-lib-item.is-on { border-color: rgb(var(--primary-500)); box-shadow: 0 0 0 3px rgba(var(--primary-500), .25); }
    .sta-lib-check { position: absolute; top: .35rem; right: .35rem; display: grid; place-items: center; width: 1.5rem; height: 1.5rem; border-radius: 50%; background: rgba(255,255,255,.9); color: transparent; box-shadow: 0 1px 3px rgba(0,0,0,.3); }
    .sta-lib-check svg { width: 1rem; height: 1rem; }
    .sta-lib-item.is-on .sta-lib-check { background: rgb(var(--primary-500)); color: #fff; }
    .sta-lib-meta { position: absolute; left: 0; right: 0; bottom: 0; display: grid; padding: .9rem .45rem .35rem; background: linear-gradient(transparent, rgba(0,0,0,.65)); color: #fff; text-align: left; line-height: 1.2; opacity: 0; transition: opacity .2s; }
    .sta-lib-item:hover .sta-lib-meta, .sta-lib-item.is-on .sta-lib-meta { opacity: 1; }
    .sta-lib-meta b { font-size: .72rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sta-lib-meta small { font-size: .65rem; opacity: .85; }
    .sta-lib-empty { padding: 2rem; text-align: center; color: rgb(var(--gray-500)); }
    .sta-lib-more, .sta-lib-loading { display: flex; justify-content: center; margin-top: .8rem; }
    .sta-lib-card { display: grid; gap: .15rem; width: 100%; min-width: 0; }
    .sta-lib-card-img { display: block; aspect-ratio: 4/3; overflow: hidden; border-radius: .6rem; background: rgb(var(--gray-100)); margin-bottom: .35rem; }
    .sta-lib-card-img img { width: 100%; height: 100%; object-fit: cover; }
    .sta-lib-card b { font-size: .85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sta-lib-card small { font-size: .72rem; color: rgb(var(--gray-500)); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
<script>
    window.staLibraryPicker = function (o) {
        return {
            state: o.state, multiple: o.multiple, items: [], folders: [], q: '', folder: '', page: 1, more: false, total: 0, loading: false,
            selected() { return Array.isArray(this.state) ? this.state : (this.state ? Object.values(this.state) : []); },
            isOn(p) { return this.selected().indexOf(p) >= 0; },
            toggle(p) {
                var s = this.selected().slice(), i = s.indexOf(p);
                if (!this.multiple) { this.state = i >= 0 ? [] : [p]; return; }
                if (i >= 0) s.splice(i, 1); else s.push(p);
                this.state = s;
            },
            load(reset) {
                if (reset) { this.page = 1; this.items = []; } else { this.page++; }
                this.loading = true;
                var u = new URL(o.url, location.href);
                u.searchParams.set('page', this.page); if (this.q) u.searchParams.set('q', this.q); if (this.folder) u.searchParams.set('folder', this.folder); if (o.agency) u.searchParams.set('agency', o.agency);
                fetch(u, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then((d) => {
                    this.items = this.items.concat(d.items || []); this.more = !!d.more; this.total = d.total || 0;
                    if (reset && d.folders) this.folders = d.folders;
                }).finally(() => { this.loading = false; });
            },
        };
    };
</script>
