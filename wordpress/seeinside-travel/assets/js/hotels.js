/* SeeInside Travel – hotels with live prices: results with filters, hotel page, booking */
(function () {
  'use strict';
  var K = window.staCore, H = window.STA_HOTELS || { i18n: {} };
  if (!K) return;
  var $ = K.$, $$ = K.$$, esc = K.esc, money = K.money, api = K.api, L = H.i18n || {};
  var tr = function (v) { return K.t(v); };
  function stars(n) { return n ? '<span class="sta-ht-stars" aria-label="' + n + '★">' + new Array(n + 1).join('★') + '</span>' : ''; }
  function fmt(s, v) { return String(s || '').replace('%s', v).replace('%d', v); }
  function dmy(iso) { if (!iso) return ''; var p = String(iso).slice(0, 10).split('-'); return p[2] + '.' + p[1] + '.' + p[0]; }
  function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

  $$('[data-sta-hotels]').forEach(function (root) {
    var body = $('[data-sta-hotels-body]', root), perPage = +root.dataset.perPage || 20;
    var state = { list: [], res: null, shown: perPage, filter: { name: '', min: null, max: null, stars: [], boards: [], free: false, sort: 'rec' } };

    /* ---------- what was searched ---------- */
    function query() {
      var u = new URLSearchParams(location.search), rooms = Math.max(1, Math.min(5, +u.get('room_count') || 1)), ad = Math.max(1, Math.min(8, +u.get('adults') || 2));
      var ages = (u.get('ages') || '').split(',').filter(function (x) { return x !== ''; }).map(Number).slice(0, 4);
      var q = { from: u.get('from') || '', to: u.get('to') || '', destination: u.get('destination') || '', hotel: u.get('hotel') || '', room_count: rooms, adults: ad, ages: ages };
      q.rooms = []; for (var i = 0; i < rooms; i++) q.rooms.push({ adults: ad, children_ages: ages });
      q.nights = q.from && q.to ? Math.round((new Date(q.to) - new Date(q.from)) / 864e5) : 0;
      q.open = u.get('h') || ''; q.searchId = u.get('sid') || '';
      state.filter.stars = u.getAll('h_stars[]').map(Number); state.filter.boards = u.getAll('h_board[]');
      return q;
    }
    function qs(q) {
      var p = new URLSearchParams({ from: q.from, to: q.to, room_count: q.room_count, adults: q.adults });
      if (q.ages.length) p.set('ages', q.ages.join(','));
      if (q.destination) p.set('destination', q.destination);
      if (q.hotel) p.set('hotel', q.hotel);
      return p.toString();
    }
    function who(q) {
      var ch = q.ages.length * q.room_count;
      return plural(q.nights, L.night1, L.nights) + ' · ' + plural(q.room_count, L.room1, L.roomN) + ' · ' + (q.adults * q.room_count) + ' ' + L.adults + (ch ? ' · ' + ch + ' ' + L.children : '');
    }
    function loading(text) { body.innerHTML = '<div class="sta-ht-loading"><span class="sta-spin"></span>' + esc(text) + '</div>'; }

    /* ---------- results ---------- */
    function search() {
      var q = query();
      if (q.open) { hotel(q.open, q.searchId); return; }
      // no dates yet: the list of all hotels (without prices)
      if (!q.from || !q.to || (!q.destination && !q.hotel)) { catalog(q); return; }
      loading(L.searching);
      api('hotels/search?' + qs(q)).then(function (r) {
        if (!r.ok) { body.innerHTML = '<div class="sta-ht-empty is-error">' + K.errors(r.body) + '</div>'; return; }
        state.res = r.body.data; state.list = state.res.hotels || []; state.shown = perPage; state.q = q; state.at = Date.now();
        if (!state.list.length) { body.innerHTML = '<div class="sta-ht-empty">' + esc(state.res.error || L.none) + '</div>'; return; }
        // a single hotel searched by name → straight to its page
        if (q.hotel && state.list.length === 1) { open(state.list[0].id); return; }
        var prices = state.list.map(function (h) { return h.price; });
        state.lo = Math.floor(Math.min.apply(null, prices)); state.hi = Math.ceil(Math.max.apply(null, prices));
        layout(); list();
      });
    }

    function layout() {
      var q = state.q, f = state.filter, cur = state.res.currency;
      var boards = {}; state.list.forEach(function (h) { (h.boards || []).forEach(function (b) { boards[b] = (boards[b] || 0) + 1; }); });
      var names = { RO: 'Room only', BB: 'BB', HB: 'HB', FB: 'FB', AI: 'All inclusive' };
      var boardNames = window.STA_BOARDS || { RO: tr({ mk: 'Само ноќевање', en: 'Room only' }), BB: tr({ mk: 'Ноќевање со појадок', en: 'Bed & breakfast' }), HB: tr({ mk: 'Полупансион', en: 'Half board' }), FB: tr({ mk: 'Полн пансион', en: 'Full board' }), AI: 'All inclusive' };
      var starCount = {}; state.list.forEach(function (h) { if (h.stars) starCount[h.stars] = (starCount[h.stars] || 0) + 1; });
      var side = '<aside class="sta-ht-side"><button type="button" class="sta-ht-close-side" data-ht-side-close aria-label="' + esc(L.close) + '">×</button>'
        + '<div class="sta-ff"><h4>' + esc(L.name) + '</h4><input type="search" data-ht-f="name" value="' + esc(f.name) + '"></div>'
        + (state.hi > state.lo ? '<div class="sta-ff"><h4>' + esc(L.price) + ' <output data-ht-price-out></output></h4><div class="sta-sb-range"><div class="sta-sb-range-fill" data-ht-fill></div>'
          + '<input type="range" min="' + state.lo + '" max="' + state.hi + '" step="1" value="' + (f.min || state.lo) + '" data-ht-lo><input type="range" min="' + state.lo + '" max="' + state.hi + '" step="1" value="' + (f.max || state.hi) + '" data-ht-hi></div></div>' : '')
        + '<div class="sta-ff"><h4>' + esc(L.stars) + '</h4>' + [5, 4, 3, 2, 1].filter(function (s) { return starCount[s]; }).map(function (s) {
          return '<label class="sta-ff-opt"><input type="checkbox" data-ht-star value="' + s + '"' + (f.stars.indexOf(s) > -1 ? ' checked' : '') + '> <span>' + new Array(s + 1).join('★') + '</span><small>' + starCount[s] + '</small></label>';
        }).join('') + '</div>'
        + '<div class="sta-ff"><h4>' + esc(L.board) + '</h4>' + ['RO', 'BB', 'HB', 'FB', 'AI'].filter(function (b) { return boards[b]; }).map(function (b) {
          return '<label class="sta-ff-opt"><input type="checkbox" data-ht-board value="' + b + '"' + (f.boards.indexOf(b) > -1 ? ' checked' : '') + '> <span>' + esc(boardNames[b] || names[b]) + '</span><small>' + boards[b] + '</small></label>';
        }).join('') + '</div>'
        + '<div class="sta-ff"><label class="sta-ff-opt"><input type="checkbox" data-ht-free' + (f.free ? ' checked' : '') + '> <span>' + esc(L.onlyFree) + '</span></label></div>'
        + '</aside>';
      body.innerHTML = '<div class="sta-ht-head"><div><b data-ht-count></b><span class="sta-muted"> · ' + esc(who(q)) + '</span></div>'
        + '<div class="sta-ht-tools"><button type="button" class="sta-ht-filters-btn" data-ht-side-open>' + esc(L.filters) + '</button><label>' + esc(L.sort) + ' <select data-ht-sort>'
        + [['rec', L.sortRec], ['low', L.sortLow], ['high', L.sortHigh], ['stars', L.sortStars]].map(function (o) { return '<option value="' + o[0] + '"' + (f.sort === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('')
        + '</select></label></div></div><div class="sta-ht-wrap">' + side + '<div class="sta-ht-main"><div class="sta-ht-list" data-ht-list></div><div class="sta-ht-more" data-ht-more></div></div></div>';
      var side2 = $('.sta-ht-side', body);
      side2.addEventListener('input', onFilter); side2.addEventListener('change', onFilter);
      $('[data-ht-sort]', body).addEventListener('change', function (e) { f.sort = e.target.value; state.shown = perPage; list(); });
      $('[data-ht-side-open]', body).addEventListener('click', function () { body.classList.add('is-side'); });
      $('[data-ht-side-close]', body).addEventListener('click', function () { body.classList.remove('is-side'); });
      priceOut();
    }
    function priceOut() {
      var lo = $('[data-ht-lo]', body), hi = $('[data-ht-hi]', body); if (!lo) return;
      if (+lo.value > +hi.value) { var t = lo.value; lo.value = hi.value; hi.value = t; }
      $('[data-ht-price-out]', body).textContent = money(lo.value, state.res.currency) + ' – ' + money(hi.value, state.res.currency);
      var fill = $('[data-ht-fill]', body); fill.style.left = ((lo.value - state.lo) / (state.hi - state.lo) * 100) + '%'; fill.style.right = (100 - (hi.value - state.lo) / (state.hi - state.lo) * 100) + '%';
    }
    function onFilter() {
      var f = state.filter, lo = $('[data-ht-lo]', body), hi = $('[data-ht-hi]', body);
      f.name = $('[data-ht-f="name"]', body).value.trim().toLowerCase();
      if (lo) { priceOut(); f.min = +lo.value > state.lo ? +lo.value : null; f.max = +hi.value < state.hi ? +hi.value : null; }
      f.stars = $$('[data-ht-star]:checked', body).map(function (c) { return +c.value; });
      f.boards = $$('[data-ht-board]:checked', body).map(function (c) { return c.value; });
      f.free = !!($('[data-ht-free]', body) || {}).checked;
      state.shown = perPage; list();
    }
    function filtered() {
      var f = state.filter;
      var out = state.list.filter(function (h) {
        if (f.name && h.name.toLowerCase().indexOf(f.name) < 0) return false;
        if (f.min !== null && h.price < f.min) return false;
        if (f.max !== null && h.price > f.max) return false;
        if (f.stars.length && f.stars.indexOf(h.stars || 0) < 0) return false;
        if (f.boards.length && !(h.boards || []).some(function (b) { return f.boards.indexOf(b) > -1; })) return false;
        if (f.free && !h.refundable) return false;
        return true;
      });
      var by = { rec: function (a, b) { return (b.featured - a.featured) || (a.price - b.price); }, low: function (a, b) { return a.price - b.price; }, high: function (a, b) { return b.price - a.price; }, stars: function (a, b) { return (b.stars || 0) - (a.stars || 0) || a.price - b.price; } };
      return out.sort(by[f.sort] || by.rec);
    }
    function card(h) {
      var q = state.q;
      return '<article class="sta-ht-card' + (h.featured ? ' is-featured' : '') + '">'
        + '<a class="sta-ht-img" href="#" data-ht-open="' + esc(h.id) + '">' + (h.image ? '<img src="' + esc(h.image) + '" alt="" loading="lazy">' : '<span class="sta-ht-noimg"></span>') + '</a>'
        + '<div class="sta-ht-info"><h3><a href="#" data-ht-open="' + esc(h.id) + '">' + esc(h.name) + '</a> ' + stars(h.stars) + '</h3>'
        + (h.place ? '<p class="sta-ht-place">' + esc(h.place) + '</p>' : '')
        + (h.facilities && h.facilities.length ? '<ul class="sta-ht-fac">' + h.facilities.slice(0, 4).map(function (x) { return '<li>' + esc(tr(x)) + '</li>'; }).join('') + '</ul>' : '')
        + '<p class="sta-ht-board">' + esc(tr(h.board)) + '</p>'
        + (h.refundable ? '<p class="sta-ht-ok">✓ ' + esc(L.freeCancel) + '</p>' : '')
        + (h.left ? '<p class="sta-ht-left">' + esc(fmt(L.left, h.left)) + '</p>' : '')
        + '</div><div class="sta-ht-price"><small>' + esc(plural(q.nights, L.night1, L.nights)) + ', ' + esc(who(q).split(' · ').slice(1).join(', ')) + '</small>'
        + '<b>' + money(h.price, h.currency) + '</b><button type="button" class="sta-btn" data-ht-open="' + esc(h.id) + '">' + esc(L.seeRooms) + '</button></div></article>';
    }
    function list() {
      var items = filtered(), box = $('[data-ht-list]', body);
      $('[data-ht-count]', body).textContent = plural(items.length, L.hotel1, L.hotels);
      box.innerHTML = items.length ? items.slice(0, state.shown).map(card).join('') : '<div class="sta-ht-empty">' + esc(L.noneFilter) + '</div>';
      var more = $('[data-ht-more]', body);
      more.innerHTML = items.length > state.shown ? '<button type="button" class="sta-btn sta-btn-ghost" data-ht-more-btn>' + esc(L.more) + ' (' + (items.length - state.shown) + ')</button>' : '';
    }
    body.addEventListener('click', function (e) {
      var o = e.target.closest('[data-ht-open]');
      if (o) { e.preventDefault(); open(o.dataset.htOpen); return; }
      if (e.target.closest('[data-ht-more-btn]')) { state.shown += perPage; list(); return; }
      if (e.target.closest('[data-ht-back]')) { e.preventDefault(); history.back(); }
    });

    /* ---------- all hotels without dates (no prices) ---------- */
    var cat = null, catTimer = null;
    function catalog(q) {
      cat = { dest: q.destination || '', stars: state.filter.stars.slice(), name: '', page: 1, pages: 1, total: 0, items: [] };
      var dests = H.destinations || [], byCountry = {};
      dests.forEach(function (d) { (byCountry[d.country || ''] = byCountry[d.country || ''] || []).push(d); });
      var opts = '<option value="">' + esc(L.allDest) + '</option>' + Object.keys(byCountry).map(function (c) {
        var o = byCountry[c].map(function (d) { return '<option value="' + esc(d.id) + '"' + (d.id === cat.dest ? ' selected' : '') + '>' + esc(d.name) + '</option>'; }).join('');
        return c ? '<optgroup label="' + esc(c) + '">' + o + '</optgroup>' : o;
      }).join('');
      var side = '<aside class="sta-ht-side"><button type="button" class="sta-ht-close-side" data-ht-side-close aria-label="' + esc(L.close) + '">×</button>'
        + (dests.length ? '<div class="sta-ff"><h4>' + esc(L.destination) + '</h4><select data-hc-dest>' + opts + '</select></div>' : '')
        + '<div class="sta-ff"><h4>' + esc(L.name) + '</h4><input type="search" data-hc-name></div>'
        + '<div class="sta-ff"><h4>' + esc(L.stars) + '</h4>' + [5, 4, 3, 2].map(function (n) {
          return '<label class="sta-ff-opt"><input type="checkbox" data-hc-star value="' + n + '"' + (cat.stars.indexOf(n) > -1 ? ' checked' : '') + '> <span>' + new Array(n + 1).join('★') + '</span></label>';
        }).join('') + '</div></aside>';
      body.innerHTML = '<div class="sta-ht-head"><div><b data-hc-count></b><span class="sta-muted"> · ' + esc(L.catalogNote) + '</span></div>'
        + '<div class="sta-ht-tools"><button type="button" class="sta-ht-filters-btn" data-ht-side-open>' + esc(L.filters) + '</button></div></div>'
        + '<div class="sta-ht-wrap">' + side + '<div class="sta-ht-main"><div class="sta-ht-list" data-hc-list></div><div class="sta-ht-more" data-hc-more></div></div></div>';
      var sd = $('.sta-ht-side', body);
      sd.addEventListener('change', function (e) { if (!e.target.matches('[data-hc-name]')) catFilter(); });
      $('[data-hc-name]', body).addEventListener('input', function () { clearTimeout(catTimer); catTimer = setTimeout(catFilter, 350); });
      $('[data-ht-side-open]', body).addEventListener('click', function () { body.classList.add('is-side'); });
      $('[data-ht-side-close]', body).addEventListener('click', function () { body.classList.remove('is-side'); });
      catLoad();
    }
    function catFilter() {
      var d = $('[data-hc-dest]', body);
      cat.dest = d ? d.value : ''; cat.name = $('[data-hc-name]', body).value.trim();
      cat.stars = $$('[data-hc-star]:checked', body).map(function (c) { return +c.value; });
      cat.page = 1; cat.items = []; catLoad();
    }
    function catLoad() {
      var box = $('[data-hc-list]', body), more = $('[data-hc-more]', body);
      if (cat.page === 1) box.innerHTML = '<div class="sta-ht-loading"><span class="sta-spin"></span>' + esc(L.loading) + '</div>';
      else more.innerHTML = '<span class="sta-spin"></span>';
      var p = new URLSearchParams({ page: cat.page, per_page: perPage });
      if (cat.dest) p.set('destination', cat.dest);
      if (cat.name) p.set('q', cat.name);
      if (cat.stars.length) p.set('stars', cat.stars.join(','));
      var mine = cat;
      api('hotels/catalog?' + p).then(function (r) {
        if (mine !== cat || !$('[data-hc-list]', body)) return;
        if (!r.ok) { box.innerHTML = '<div class="sta-ht-empty is-error">' + K.errors(r.body) + '</div>'; more.innerHTML = ''; return; }
        var d = r.body.data; cat.items = cat.items.concat(d.hotels || []); cat.pages = d.pages; cat.total = d.total;
        $('[data-hc-count]', body).textContent = plural(d.total, L.hotel1, L.hotels);
        box.innerHTML = cat.items.length ? cat.items.map(catCard).join('') : '<div class="sta-ht-empty">' + esc(L.noneFilter) + '</div>';
        more.innerHTML = cat.page < cat.pages ? '<button type="button" class="sta-btn sta-btn-ghost" data-hc-more-btn>' + esc(L.more) + ' (' + (cat.total - cat.items.length) + ')</button>' : '';
      });
    }
    function catCard(h) {
      return '<article class="sta-ht-card' + (h.featured ? ' is-featured' : '') + '">'
        + '<a class="sta-ht-img" href="#" data-ht-open="' + esc(h.id) + '">' + (h.image ? '<img src="' + esc(h.image) + '" alt="" loading="lazy">' : '<span class="sta-ht-noimg"></span>') + '</a>'
        + '<div class="sta-ht-info"><h3><a href="#" data-ht-open="' + esc(h.id) + '">' + esc(h.name) + '</a> ' + stars(h.stars) + '</h3>'
        + (h.place ? '<p class="sta-ht-place">' + esc(h.place) + '</p>' : '')
        + (h.facilities && h.facilities.length ? '<ul class="sta-ht-fac">' + h.facilities.slice(0, 4).map(function (x) { return '<li>' + esc(tr(x)) + '</li>'; }).join('') + '</ul>' : '')
        + '</div><div class="sta-ht-price"><small>' + esc(L.pricesFor) + '</small>'
        + '<button type="button" class="sta-btn" data-hc-dates="' + esc(h.id) + '">' + esc(L.checkPrices) + '</button>'
        + '<button type="button" class="sta-btn sta-btn-ghost sta-btn-sm" data-ht-open="' + esc(h.id) + '">' + esc(L.details) + '</button></div></article>';
    }
    // "Check prices": the hotel goes into the search above and the calendar opens
    function pickDates(id, name) {
      var form = $('[data-sb-hotels]', root);
      if (!form) { open(id); return; }
      var dq = $('[data-sb-q]', form), hh = $('[data-sb-h="hotel"]', form), hd = $('[data-sb-h="destination"]', form);
      if (dq) dq.value = name || '';
      if (hh) hh.value = id;
      if (hd) hd.value = '';
      var hn = $('input[name="hn"]', form) || form.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name: 'hn' }));
      hn.value = name || '';
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
      var from = $('[data-sb-from]', form);
      if (from && from.value) { (form.requestSubmit ? form.requestSubmit() : form.submit()); return; }
      setTimeout(function () { var b = $('.sta-sb-dates [data-sb-open]', form); if (b) b.click(); }, 350);
    }
    body.addEventListener('click', function (e) {
      var m = e.target.closest('[data-hc-more-btn]');
      if (m) { cat.page++; catLoad(); return; }
      var d = e.target.closest('[data-hc-dates]');
      if (d) {
        var id = d.dataset.hcDates, h = (cat && cat.items || []).filter(function (x) { return x.id === id; })[0] || state.hotel || {};
        pickDates(id, h.name);
      }
    });

    /* ---------- one hotel ---------- */
    function open(id) {
      var u = new URL(location.href); u.searchParams.set('h', id); if (state.res) u.searchParams.set('sid', state.res.search_id);
      history.pushState({ h: id }, '', u); hotel(id, state.res ? state.res.search_id : '');
      root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    window.addEventListener('popstate', function () { var q = query(); if (q.open) hotel(q.open, q.searchId); else if (state.list.length && state.q && state.q.from) { layout(); list(); } else search(); });

    function hotel(id, sid) {
      var q = query();
      loading(L.searching);
      api('hotels/hotel?id=' + encodeURIComponent(id) + '&' + qs(q) + (sid ? '&search_id=' + encodeURIComponent(sid) : '')).then(function (r) {
        if (!r.ok) { body.innerHTML = '<div class="sta-ht-empty is-error">' + K.errors(r.body) + '</div>'; return; }
        state.hotel = r.body.data; state.q = q; state.at = state.at || Date.now();
        // the search above shows the hotel name, not its code
        var f = $('[data-sb-hotels]', root), dq = f && $('[data-sb-q]', f);
        if (dq && (dq.value === id || /^\d+-\d+$/.test(dq.value))) dq.value = state.hotel.name;
        renderHotel();
      });
    }
    function cancelText(rate) {
      if (!rate.refundable) return '<span class="sta-ht-nr">' + esc(L.nonRef) + '</span>';
      return rate.free_cancel_until ? '<span class="sta-ht-ok">✓ ' + esc(fmt(L.freeUntil, dmy(rate.free_cancel_until) + ' ' + rate.free_cancel_until.slice(11, 16))) + '</span>' : '<span class="sta-ht-ok">✓ ' + esc(L.freeCancel) + '</span>';
    }
    function renderHotel() {
      var h = state.hotel, q = state.q, imgs = h.images || [];
      var gal = imgs.length ? '<div class="sta-ht-gal" data-sta-lightbox>' + imgs.slice(0, 5).map(function (im, i) {
        return '<a href="' + esc(im.url) + '" class="sta-ht-gi' + (i === 0 ? ' is-main' : '') + '"><img src="' + esc(i === 0 ? im.url : im.thumb) + '" alt="" loading="' + (i ? 'lazy' : 'eager') + '">' + (i === 4 && imgs.length > 5 ? '<span class="sta-more">+' + (imgs.length - 5) + '</span>' : '') + '</a>';
      }).join('') + imgs.slice(5).map(function (im) { return '<a href="' + esc(im.url) + '" hidden></a>'; }).join('') + '</div>' : '';
      var fac = (h.facilities || []).map(function (f) { return '<li>' + esc(tr(f.name)) + (f.fee ? ' <small>(' + esc(L.fee) + ')</small>' : '') + '</li>'; }).join('');
      var rooms = (h.rooms || []).map(function (rm, ri) {
        return '<div class="sta-ht-room"><div class="sta-ht-room-h">' + (rm.image ? '<img src="' + esc(rm.image) + '" alt="" loading="lazy">' : '') + '<h4>' + esc(rm.name) + '</h4></div>'
          + '<table class="sta-ht-rates"><tbody>' + rm.rates.map(function (rt, i) {
            return '<tr><td><b>' + esc(tr(rt.board)) + '</b>' + (rt.offers && rt.offers.length ? '<br><small class="sta-ht-offer">' + esc(rt.offers.join(', ')) + '</small>' : '') + '</td>'
              + '<td>' + cancelText(rt) + (rt.pay_at_hotel ? '<br><small>' + esc(L.payHotel) + '</small>' : '') + (rt.taxes_extra && rt.taxes_extra.length ? '<br><small>' + esc(L.taxes) + '</small>' : '') + (rt.left ? '<br><small class="sta-ht-left">' + esc(fmt(L.left, rt.left)) + '</small>' : '') + '</td>'
              + '<td class="sta-ht-rp"><b>' + money(rt.price, h.currency) + '</b><small>' + esc(plural(q.nights, L.night1, L.nights)) + '</small></td>'
              + '<td><button type="button" class="sta-btn sta-btn-sm" data-ht-book="' + ri + ':' + i + '">' + esc(L.book) + '</button></td></tr>';
          }).join('') + '</tbody></table></div>';
      }).join('');
      var desc = h.description || '';
      body.innerHTML = '<div class="sta-ht-page"><a href="#" class="sta-ht-back" data-ht-back>← ' + esc(L.back) + '</a>'
        + '<header class="sta-ht-ph"><h2>' + esc(h.name) + ' ' + stars(h.stars) + '</h2><p class="sta-ht-place">' + esc([h.address, h.place].filter(Boolean).join(' · '))
        + (h.latitude ? ' · <a href="https://www.google.com/maps/search/?api=1&query=' + h.latitude + ',' + h.longitude + '" target="_blank" rel="noopener">' + esc(L.map) + '</a>' : '') + '</p></header>'
        + gal
        + '<div class="sta-ht-cols"><div>' + (desc ? '<div class="sta-ht-desc' + (desc.length > 700 ? ' is-cut' : '') + '"><p>' + esc(desc).replace(/\n+/g, '</p><p>') + '</p></div>' + (desc.length > 700 ? '<button type="button" class="sta-ht-link" data-ht-more-desc>' + esc(L.readMore) + '</button>' : '') : '')
        + (fac ? '<h3>' + esc(L.facilities) + '</h3><ul class="sta-ht-facs">' + fac + '</ul>' : '') + '</div>'
        + (q.from ? '<aside class="sta-ht-sum"><b>' + esc(dmy(q.from)) + ' – ' + esc(dmy(q.to)) + '</b><br>' + esc(who(q)) + '</aside></div>'
          : '<aside class="sta-ht-sum"><p>' + esc(L.pickDates) + '</p><button type="button" class="sta-btn" data-hc-dates="' + esc(h.id || q.open) + '">' + esc(L.checkPrices) + '</button></aside></div>')
        + '<h3 class="sta-ht-rt" id="sta-ht-rooms">' + esc(L.roomsTitle) + '</h3>'
        + (q.from ? (rooms || '<div class="sta-ht-empty">' + esc(L.none) + '</div>') : '<div class="sta-ht-empty">' + esc(L.pickDates) + ' <button type="button" class="sta-btn sta-btn-sm" data-hc-dates="' + esc(h.id || q.open) + '">' + esc(L.checkPrices) + '</button></div>') + '</div>';
      bindLightbox($('[data-sta-lightbox]', body));
      var md = $('[data-ht-more-desc]', body); if (md) md.addEventListener('click', function () { $('.sta-ht-desc', body).classList.remove('is-cut'); md.remove(); });
      $$('[data-ht-book]', body).forEach(function (b) { b.addEventListener('click', function () { var p = b.dataset.htBook.split(':'); book(h.rooms[+p[0]], h.rooms[+p[0]].rates[+p[1]]); }); });
    }
    function bindLightbox(g) {
      if (!g) return;
      var links = $$('a', g), i = 0, box;
      function show() {
        if (!box) {
          box = document.createElement('div'); box.className = 'sta-lightbox';
          box.innerHTML = '<img alt=""><button class="sta-lb-close" aria-label="×">×</button><button class="sta-lb-prev" aria-label="‹">‹</button><button class="sta-lb-next" aria-label="›">›</button>';
          box.addEventListener('click', function (e) {
            if (e.target.classList.contains('sta-lb-next')) { i = (i + 1) % links.length; show(); }
            else if (e.target.classList.contains('sta-lb-prev')) { i = (i - 1 + links.length) % links.length; show(); }
            else if (e.target.tagName !== 'IMG') { box.remove(); box = null; }
          });
          document.body.appendChild(box);
        }
        $('img', box).src = links[i].href;
      }
      links.forEach(function (a, n) { a.addEventListener('click', function (e) { e.preventDefault(); i = n; show(); }); });
    }

    /* ---------- booking ---------- */
    function book(room, rate) {
      var h = state.hotel, q = state.q;
      if (state.at && Date.now() - state.at > 14 * 60 * 1000) {
        if (window.confirm(L.expired)) { location.reload(); }
        return;
      }
      var guests = '', n = 0;
      q.rooms.forEach(function (r, ri) {
        guests += q.rooms.length > 1 ? '<div class="sta-ht-gr">' + esc(L.room) + ' ' + (ri + 1) + '</div>' : '';
        for (var a = 0; a < r.adults; a++, n++) guests += row(n, L.adult + ' ' + (a + 1), true);
        r.children_ages.forEach(function (age, ci) { guests += row(n++, L.child + ' ' + (ci + 1) + ' (' + age + ' ' + L.years + ')', false); });
      });
      function row(i, label, req) {
        return '<div class="sta-row sta-ht-guest"><span class="sta-ht-gl">' + esc(label) + '</span>'
          + '<label class="sta-field"><span>' + esc(L.firstName) + (req && i === 0 ? ' *' : '') + '</span><input name="passengers[' + i + '][first_name]"' + (i === 0 ? ' required' : '') + ' autocomplete="' + (i === 0 ? 'given-name' : 'off') + '"></label>'
          + '<label class="sta-field"><span>' + esc(L.lastName) + (req && i === 0 ? ' *' : '') + '</span><input name="passengers[' + i + '][last_name]"' + (i === 0 ? ' required' : '') + ' autocomplete="' + (i === 0 ? 'family-name' : 'off') + '"></label></div>';
      }
      var dlg = document.createElement('dialog'); dlg.className = 'sta-dialog sta-ht-dlg';
      dlg.innerHTML = '<button type="button" class="sta-dialog-close" data-close aria-label="' + esc(L.close) + '">×</button>'
        + '<form class="sta-form sta-ht-form" novalidate><h3>' + esc(L.yourBooking) + '</h3>'
        + '<div class="sta-ht-recap"><b>' + esc(h.name) + '</b> ' + stars(h.stars) + '<br>' + esc(room.name) + ' · ' + esc(tr(rate.board))
        + '<br>' + esc(L.checkIn) + ' ' + esc(dmy(q.from)) + ' · ' + esc(L.checkOut) + ' ' + esc(dmy(q.to)) + ' · ' + esc(who(q))
        + '<br>' + cancelText(rate) + (rate.pay_at_hotel ? ' · ' + esc(L.payHotel) : '')
        + '<div class="sta-ht-total">' + esc(L.total) + ' <b data-ht-total>' + money(rate.price, h.currency) + '</b></div></div>'
        + '<h4>' + esc(L.guests) + '</h4><p class="sta-muted">' + esc(L.guestsNote) + '</p>' + guests
        + '<h4>' + esc(L.contact) + '</h4><div class="sta-row">'
        + '<label class="sta-field"><span>' + esc(L.fullName) + ' *</span><input name="customer[name]" required autocomplete="name"></label>'
        + '<label class="sta-field"><span>' + esc(L.email) + ' *</span><input type="email" name="customer[email]" required autocomplete="email"></label>'
        + '<label class="sta-field"><span>' + esc(L.phone) + ' *</span><input type="tel" name="customer[phone]" required autocomplete="tel"></label></div>'
        + '<label class="sta-field"><span>' + esc(L.message) + '</span><textarea name="message" rows="2"></textarea></label>'
        + '<input type="text" name="website" class="sta-hp" tabindex="-1" autocomplete="off" aria-hidden="true">'
        + '<label class="sta-check sta-consent"><input type="checkbox" required> <span>' + (H.consent || '') + '</span></label>'
        + '<div class="sta-msg" data-sta-msg></div><button type="submit" class="sta-btn sta-btn-block">' + esc(L.send) + '</button></form>';
      document.body.appendChild(dlg);
      dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-close]')) { dlg.close(); dlg.remove(); } });
      dlg.addEventListener('cancel', function () { setTimeout(function () { dlg.remove(); }, 0); });
      dlg.showModal ? dlg.showModal() : dlg.setAttribute('open', '');
      var form = $('form', dlg), started = Date.now(), accept = null;
      // the holder's name from the first guest
      var fn = $('[name="passengers[0][first_name]"]', form), ln = $('[name="passengers[0][last_name]"]', form), cn = $('[name="customer[name]"]', form);
      function syncName() { if (!cn.dataset.touched) cn.value = (fn.value + ' ' + ln.value).trim(); }
      fn.addEventListener('input', syncName); ln.addEventListener('input', syncName); cn.addEventListener('input', function () { cn.dataset.touched = 1; });
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!K.validate(form)) return;
        var fd = new FormData(form), pax = [];
        for (var i = 0; i < n; i++) pax.push({ first_name: fd.get('passengers[' + i + '][first_name]') || '', last_name: fd.get('passengers[' + i + '][last_name]') || '' });
        var payload = {
          search_id: h.search_id || (state.res && state.res.search_id), hotel: h.id, rate_key: rate.key,
          query: { from: q.from, to: q.to, rooms: q.rooms }, passengers: pax,
          customer: { name: fd.get('customer[name]'), email: fd.get('customer[email]'), phone: fd.get('customer[phone]') },
          message: fd.get('message') || '', website: fd.get('website') || '', sta_ts: started,
        };
        if (accept) payload.accept_price = accept;
        var btn = $('button[type=submit]', form); btn.disabled = true; K.msg(form, '<span class="sta-spin"></span> ' + esc(L.sending), true);
        api('hotels/book', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(payload) }).then(function (r) {
          btn.disabled = false;
          if (r.status === 409 && r.body && r.body.price_changed) {
            if (window.confirm(fmt(L.priceChanged, money(r.body.new_price, r.body.currency)))) { accept = r.body.new_price; $('[data-ht-total]', form).textContent = money(r.body.new_price, r.body.currency); form.requestSubmit ? form.requestSubmit() : btn.click(); }
            else K.msg(form, '', true);
            return;
          }
          if (!r.ok) { K.msg(form, K.errors(r.body), false); return; }
          var d = r.body.data || {};
          form.innerHTML = '<div class="sta-ht-done"><div class="sta-ht-check">✓</div><h3>' + esc(d.booked ? L.booked : L.thanks) + '</h3>'
            + '<p>' + esc(L.reference) + ': <b class="sta-ref">' + esc(d.reference || '') + '</b></p>'
            + (d.total ? '<p>' + esc(L.total) + ': <b>' + money(d.total, d.currency) + '</b></p>' : '')
            + '<p class="sta-muted">' + esc(L.thanksNote) + '</p>'
            + (d.qr_url ? '<img class="sta-qr" src="' + esc(d.qr_url) + '" alt="QR" width="140" height="140">' : '')
            + (d.manage_url ? '<p><a class="sta-btn sta-btn-ghost" href="' + esc(d.manage_url) + '" target="_blank" rel="noopener">' + esc(L.manage) + '</a></p>' : '')
            + '<p><button type="button" class="sta-btn" data-close>' + esc(L.close) + '</button></p></div>';
        }).catch(function () { btn.disabled = false; K.msg(form, esc(L.error), false); });
      });
    }

    search();
  });
})();
