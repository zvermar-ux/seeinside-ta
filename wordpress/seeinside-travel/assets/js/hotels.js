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
  // Cyrillic typed in the search → Latin hotel names (нидри → nidri)
  var CYR = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', ѓ: 'gj', е: 'e', ж: 'zh', з: 'z', ѕ: 'dz', и: 'i', ј: 'j', к: 'k', л: 'l', љ: 'lj', м: 'm', н: 'n', њ: 'nj', о: 'o', п: 'p', р: 'r', с: 's', т: 't', ќ: 'kj', у: 'u', ф: 'f', х: 'h', ц: 'c', ч: 'ch', џ: 'dz', ш: 'sh', й: 'j', ю: 'ju', я: 'ja', ы: 'i', э: 'e' };
  function lat(v) { return String(v || '').toLowerCase().replace(/[а-яѓѕјљњќџ]/g, function (c) { return CYR[c] != null ? CYR[c] : c; }); }
  function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

  $$('[data-sta-hotels]').forEach(function (root) {
    var body = $('[data-sta-hotels-body]', root), perPage = +root.dataset.perPage || 20;
    var state = { list: [], res: null, shown: perPage, filter: { name: '', min: null, max: null, stars: [], board: [], type: [], feature: [], zone: '', dest: '', country: '', free: false, sort: 'rec' } };

    /* ---------- what was searched ---------- */
    function query() {
      var u = new URLSearchParams(location.search), rooms = Math.max(1, Math.min(5, +u.get('room_count') || 1)), ad = Math.max(1, Math.min(8, +u.get('adults') || 2));
      var ages = (u.get('ages') || '').split(',').filter(function (x) { return x !== ''; }).map(Number).slice(0, 4);
      var q = { from: u.get('from') || '', to: u.get('to') || '', destination: u.get('destination') || '', hotel: u.get('hotel') || '', room_count: rooms, adults: ad, ages: ages };
      q.rooms = []; for (var i = 0; i < rooms; i++) q.rooms.push({ adults: ad, children_ages: ages });
      q.nights = q.from && q.to ? Math.round((new Date(q.to) - new Date(q.from)) / 864e5) : 0;
      q.open = u.get('h') || ''; q.searchId = u.get('sid') || '';
      state.filter.stars = u.getAll('h_stars[]'); state.filter.board = u.getAll('h_board[]');
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
    // the name search on top of the filters, with a magnifier
    function qbox(attrs) {
      return '<div class="sta-ff sta-ff-q"><label class="sta-ff-search">' + esc(L.searchName || L.name) + '</label><div class="sta-ff-qbox"><input type="search" ' + attrs + ' placeholder="' + esc(L.searchPh || '') + '" autocomplete="off"><button type="button" data-qbox-go aria-label="' + esc(L.searchName || L.name) + '"><svg class="sta-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg></button></div></div>';
    }
    body.addEventListener('click', function (e) { var b = e.target.closest('[data-qbox-go]'); if (b) { var i = b.parentNode.querySelector('input'); i.dispatchEvent(new Event('input', { bubbles: true })); i.focus(); } });
    function loading(text) { body.innerHTML = '<div class="sta-ht-loading"><span class="sta-spin"></span>' + esc(text) + '</div>'; }

    /* ---------- nothing free: closest free dates + free hotels of the same area ---------- */
    function alternatives(q, box) {
      if (!box) return;
      box.innerHTML = '<div class="sta-ht-alt is-loading"><span class="sta-spin"></span>' + esc(L.altLooking) + '</div>';
      var link = function (set, del) {
        var p = new URLSearchParams(location.search);
        (del || []).forEach(function (k) { p.delete(k); });
        Object.keys(set).forEach(function (k) { p.set(k, set[k]); });
        return location.pathname + '?' + p.toString();
      };
      api('hotels/alternatives?' + qs(q)).then(function (r) {
        var d = r.ok && r.body && r.body.data, html = '';
        if (!d) { box.innerHTML = ''; return; }
        if (d.dates && d.dates.length) {
          html += '<h4>' + esc(L.altDates) + '</h4><div class="sta-ht-alt-dates">' + d.dates.map(function (x) {
            return '<a href="' + esc(link({ from: x.from, to: x.to }, ['h', 'sid'])) + '"><b>' + esc(dmy(x.from)) + ' – ' + esc(dmy(x.to)) + '</b><small>'
              + (q.hotel ? '' : esc(plural(x.count, L.hotel1, L.hotels)) + ' · ') + esc(L.from) + ' <strong>' + money(x.price, x.currency) + '</strong></small></a>';
          }).join('') + '</div>';
        }
        var n = d.nearby;
        if (n && n.hotels && n.hotels.length) {
          html += '<h4>' + esc((L.altHotels || '%s').replace('%s', n.place || '')) + '</h4><div class="sta-ht-alt-hotels">' + n.hotels.map(function (h) {
            return '<a href="' + esc(link({ destination: n.destination, h: h.id, sid: n.search_id }, ['hotel', 'hn'])) + '">'
              + (h.image ? '<img src="' + esc(h.image) + '" alt="" loading="lazy">' : '<span class="sta-ht-alt-noimg"></span>')
              + '<span><b>' + esc(h.name) + '</b> ' + stars(h.stars) + '<small>' + esc(h.place || '') + '</small><small>' + esc(tr(h.board) || '') + ' · ' + esc(L.from) + ' <strong>' + money(h.price, h.currency) + '</strong></small></span></a>';
          }).join('') + '</div>'
            + (n.count > n.hotels.length ? '<a class="sta-btn sta-btn-sm" href="' + esc(link({ destination: n.destination }, ['hotel', 'hn', 'h', 'sid'])) + '">' + esc((L.altAll || '%d').replace('%d', n.count)) + '</a>' : '');
        }
        box.innerHTML = html ? '<div class="sta-ht-alt">' + html + '</div>' : '';
      }).catch(function () { box.innerHTML = ''; });
    }

    /* ---------- results ---------- */
    function search() {
      var q = query();
      if (q.open) { hotel(q.open, q.searchId); return; }
      // no dates yet: the list of all hotels (without prices)
      if (!q.from || !q.to) { catalog(q); return; }
      loading(L.searching);
      api('hotels/search?' + qs(q)).then(function (r) {
        if (!r.ok) {
          state.res = { currency: 'EUR', hotels: [] }; state.list = []; state.q = q; state.lo = state.hi = 0; layout();
          $('[data-hf-list]', body).innerHTML = '<div class="sta-ht-empty is-error">' + K.errors(r.body) + ' <button type="button" class="sta-btn sta-btn-sm" data-hf-clear>' + esc(L.showAll) + '</button></div>';
          return;
        }
        state.res = r.body.data; state.list = state.res.hotels || []; state.shown = perPage; state.q = q; state.at = Date.now();
        if (!state.list.length) {
          // nothing free: the filter column stays (other dates, another destination) + all hotels without dates
          state.lo = state.hi = 0; layout(); list();
          $('[data-hf-list]', body).innerHTML = '<div class="sta-ht-empty' + (state.res.error ? ' is-error' : '') + '">' + esc(state.res.error || L.none)
            + ' <button type="button" class="sta-btn sta-btn-sm" data-hf-clear>' + esc(L.showAll) + '</button></div><div data-ht-alt></div>';
          if (!state.res.error) alternatives(q, $('[data-ht-alt]', body));
          return;
        }
        // a single hotel searched by name → straight to its page
        if (q.hotel && state.list.length === 1) { open(state.list[0].id); return; }
        var prices = state.list.map(function (h) { return h.price; });
        state.lo = Math.floor(Math.min.apply(null, prices)); state.hi = Math.ceil(Math.max.apply(null, prices));
        layout(); list();
      });
    }

    /* ---------- the same look as the apartment list: filters on the left, cards in a grid ---------- */
    var CARD = H.card || '', PT = H.priceToken || '', cols = +root.dataset.columns || 3, io = null;
    var today = new Date().toISOString().slice(0, 10);
    function shell(side, count, tools) {
      if (io) { io.disconnect(); io = null; }
      body.innerHTML = '<section class="sta-finder sta-ht-finder">'
        + '<button type="button" class="sta-finder-toggle" data-hf-toggle>' + esc(L.filters) + '</button>'
        + '<div class="sta-finder-wrap"><aside class="sta-finder-side">' + side + '</aside><div class="sta-finder-main">'
        + '<div class="sta-finder-head"><div><b data-hf-count>' + count + '</b><span class="sta-muted" data-hf-note></span></div>' + (tools || '') + '</div>'
        + '<div class="sta-l sta-l--grid" style="--l-cols:' + cols + ';--l-cols-t:2;--l-cols-m:1;--l-gap:22px"><div class="sta-l-items" data-hf-list></div></div>'
        + '<div class="sta-more-loader" data-hf-more hidden><span class="sta-spin"></span></div></div></div></section>';
      $('[data-hf-toggle]', body).addEventListener('click', function () { $('.sta-finder', body).classList.toggle('is-open'); });
    }
    // endless list: more cards when the end of the list comes into view
    function watchMore(more) {
      var el = $('[data-hf-more]', body);
      if (!el || !('IntersectionObserver' in window)) return;
      if (io) io.disconnect();
      io = new IntersectionObserver(function (en) { if (en[0].isIntersecting && !el.hidden) more(); }, { rootMargin: '500px 0px' });
      io.observe(el);
    }
    // a card exactly like the apartment cards (rendered by the plugin with placeholders)
    function stdCard(h, priced) {
      var url = '#h=' + encodeURIComponent(h.id), html;
      if (!CARD) {
        html = '<article class="sta-c"><div class="sta-c-media">' + (h.image ? '<img src="' + esc(h.image) + '" alt="" loading="lazy">' : '') + '</div><div class="sta-c-body"><h3 class="sta-c-title"><a class="sta-c-stretch" href="' + url + '">' + esc(h.name) + '</a></h3><div class="sta-c-sub">' + esc(h.place || '') + '</div><div class="sta-c-foot"><div class="sta-c-price"><b>' + esc(PT) + '</b></div></div></div></article>';
      } else {
        html = CARD;
      }
      html = html.split('https://sta.invalid/__U__').join(url).split('https://sta.invalid/__I__.jpg').join(esc(h.image || ''))
        .split('__T__').join(esc(h.name)).split('__P__').join(esc(h.place || '')).split('__ID__').join(esc(h.id));
      if (!h.image) html = html.replace(/<img src=""[^>]*>/, '<span class="sta-c-noimg"></span>');
      html = html.replace(/(<span class="sta-c-chip sta-c-stars"[^>]*>)★★★★★(<\/span>)/, h.stars ? '$1' + new Array(h.stars + 1).join('★') + '$2' : '');
      if (priced) {
        html = html.split(PT).join(esc(money(h.price, h.currency)));
      } else {
        // no dates yet: no price, only what the button does
        html = html.replace(/<div class="sta-c-price">[\s\S]*?<\/div>/, '<div class="sta-c-price is-ask"><small>' + esc(L.pricesFor) + '</small></div>');
      }
      var tags = [];
      if (priced && h.board) tags.push(esc(tr(h.board)));
      if (priced && h.refundable) tags.push('✓ ' + esc(L.freeCancel));
      if (priced && h.left) tags.push(esc(fmt(L.left, h.left)));
      return '<div class="sta-l-cell sta-finder-cell">' + html + (tags.length ? '<div class="sta-finder-tags"><span>' + tags.join('</span><span>') + '</span></div>' : '') + '</div>';
    }
    function destSelect(sel) {
      var dests = H.destinations || [], byCountry = {};
      if (!dests.length) return '';
      dests.forEach(function (d) { (byCountry[d.country || ''] = byCountry[d.country || ''] || []).push(d); });
      return '<label>' + esc(L.destination) + '<select data-hc-dest><option value="">' + esc(L.allDest) + '</option>' + Object.keys(byCountry).map(function (c) {
        var o = byCountry[c].map(function (d) { return '<option value="' + esc(d.id) + '"' + (d.id === sel ? ' selected' : '') + '>' + esc(d.name) + (d.hotels ? ' (' + d.hotels + ')' : '') + '</option>'; }).join('');
        return c ? '<optgroup label="' + esc(c) + '">' + o + '</optgroup>' : o;
      }).join('') + '</select></label>';
    }
    // "Your dates" – like the apartments: dates, guests, children's ages, then the free hotels with prices
    function ageSelects(n, ages) {
      var h = '';
      for (var i = 0; i < n; i++) {
        var opts = '';
        for (var a = 0; a <= 17; a++) opts += '<option value="' + a + '"' + ((ages[i] != null ? ages[i] : 7) === a ? ' selected' : '') + '>' + a + '</option>';
        h += '<label>' + esc(L.child) + ' ' + (i + 1) + '<select data-hd-age>' + opts + '</select></label>';
      }
      return h;
    }
    function datesBox(q, opt) {
      opt = opt || {};
      var ages = q.ages || [];
      return '<div class="sta-ff sta-ff-dates" data-hd' + (opt.hotel ? ' data-hotel="' + esc(opt.hotel) + '" data-hname="' + esc(opt.name || '') + '"' : '') + '>'
        + '<h4>' + esc(L.yourDates) + '</h4>'
        + (opt.dest ? destSelect(q.destination) : '')
        + '<label>' + esc(L.checkIn) + '<input type="date" data-hd-from value="' + esc(q.from || '') + '" min="' + today + '"></label>'
        + '<label>' + esc(L.checkOut) + '<input type="date" data-hd-to value="' + esc(q.to || '') + '" min="' + today + '"></label>'
        + '<div class="sta-ff-row"><label>' + esc(L.adultsL) + '<input type="number" min="1" max="8" data-hd-ad value="' + (q.adults || 2) + '"></label>'
        + '<label>' + esc(L.childrenL) + '<input type="number" min="0" max="4" data-hd-ch value="' + ages.length + '"></label></div>'
        + '<div class="sta-ff-row sta-ff-ages" data-hd-ages>' + ageSelects(ages.length, ages) + '</div>'
        + '<p class="sta-ff-err" data-hd-err hidden></p>'
        + '<button type="button" class="sta-btn sta-btn-block" data-hd-go>' + esc(opt.hotel ? L.checkPrices : L.showFree) + '</button></div>';
    }
    // Hotelbeds has not every photo in every size: xl → bigger → standard → no photo
    body.addEventListener('error', function (e) {
      var img = e.target;
      if (!img || img.tagName !== 'IMG' || img.src.indexOf('hotelbeds.com/giata/') < 0) return;
      if (img.src.indexOf('/giata/xl/') > -1) { img.src = img.src.replace('/giata/xl/', '/giata/bigger/'); return; }
      if (img.src.indexOf('/giata/bigger/') > -1) { img.src = img.src.replace('/giata/bigger/', '/giata/'); return; }
      var ph = document.createElement('span'); ph.className = 'sta-c-noimg'; img.replaceWith(ph);
    }, true);
    body.addEventListener('input', function (e) {
      if (e.target.matches('[data-hd-ch]')) {
        var box = e.target.closest('[data-hd]'), n = Math.max(0, Math.min(4, +e.target.value || 0));
        var ages = $$('[data-hd-age]', box).map(function (x) { return +x.value; });
        $('[data-hd-ages]', box).innerHTML = ageSelects(n, ages);
      }
      if (e.target.matches('[data-hd-from]')) {
        var b2 = e.target.closest('[data-hd]'), to = $('[data-hd-to]', b2);
        if (e.target.value && (!to.value || to.value <= e.target.value)) { var d = new Date(e.target.value); d.setDate(d.getDate() + 7); to.value = d.toISOString().slice(0, 10); }
        to.min = e.target.value || today;
      }
    });
    function goDates(box) {
      var from = $('[data-hd-from]', box).value, to = $('[data-hd-to]', box).value, err = $('[data-hd-err]', box);
      var sel = $('[data-hf="dest"]', body), q = state.q || query(), hotel = box.dataset.hotel || (!sel || !sel.value ? q.hotel : ''), dest = sel ? sel.value : q.destination;
      var fail = function (m) { err.textContent = m; err.hidden = false; };
      if (!from || !to || to <= from) return fail(L.needDates);
      var p = new URLSearchParams({ from: from, to: to, room_count: 1, adults: Math.max(1, +$('[data-hd-ad]', box).value || 2) });
      var ages = $$('[data-hd-age]', box).map(function (x) { return x.value; });
      if (ages.length) p.set('ages', ages.join(','));
      if (hotel) { p.set('hotel', hotel); p.set('hn', box.dataset.hname || ''); } else if (dest) p.set('destination', dest);   // no destination = all hotels
      location.href = location.pathname + '?' + p.toString();
    }

    /* ---------- filters (same blocks with and without dates) ---------- */
    function nm(x) { return typeof x === 'object' && x ? tr(x) : String(x); }
    function checks(title, key, opts, sel, label) {
      opts = (opts || []).filter(function (o) { return o.n || sel.indexOf(String(o.id)) > -1; });
      if (!opts.length) return '';
      return '<div class="sta-ff"><h4>' + esc(title) + '</h4>' + opts.map(function (o) {
        return '<label class="sta-ff-opt"><input type="checkbox" data-hf="' + key + '" value="' + esc(o.id) + '"' + (sel.indexOf(String(o.id)) > -1 ? ' checked' : '') + '> <span>' + (label ? label(o) : esc(nm(o.name))) + '</span><small>' + (o.n || 0) + '</small></label>';
      }).join('') + '</div>';
    }
    function select(key, all, opts, cur) {
      return '<select data-hf="' + key + '"><option value="">' + esc(all) + '</option>' + opts.map(function (o) {
        return '<option value="' + esc(o.id) + '"' + (String(o.id) === String(cur) ? ' selected' : '') + '>' + esc(nm(o.name)) + ' (' + (o.n || 0) + ')</option>';
      }).join('') + '</select>';
    }
    function blocks(fc, sel, withDates) {
      var loc = '';
      if ((fc.countries || []).length > 1 || sel.country) loc += select('country', L.allCountries, fc.countries || [], sel.country);
      var dests = (fc.destinations || []).filter(function (d) { return !sel.country || d.country === sel.country || d.id === sel.dest; });
      if (dests.length) loc += select('dest', L.allDest, dests, sel.dest);
      if ((fc.zones || []).length) loc += select('zone', L.allZones, fc.zones, sel.zone);
      return (loc ? '<div class="sta-ff sta-ff-loc"><h4>' + esc(L.destination) + '</h4>' + loc + '</div>' : '')
        + checks(L.stars, 'stars', fc.stars, sel.stars, function (o) { return new Array(+o.id + 1).join('★'); })
        + checks(L.typeH, 'type', fc.types, sel.type)
        + checks(L.board, 'board', fc.boards, sel.board)
        + checks(L.facilities, 'feature', fc.features, sel.feature)
        + (withDates ? '' : '<div class="sta-ff sta-ff-later"><h4>' + esc(L.price) + '</h4><p class="sta-muted">' + esc(L.afterDates) + '</p></div>');
    }
    function readSel(scope, base) {
      var v = function (k) { var e = $('[data-hf="' + k + '"]', scope); return e ? e.value : ''; };
      var c = function (k) { return $$('[data-hf="' + k + '"]:checked', scope).map(function (x) { return x.value; }); };
      return Object.assign(base || {}, { country: v('country'), dest: v('dest'), zone: v('zone'), stars: c('stars'), type: c('type'), board: c('board'), feature: c('feature') });
    }

    /* ---------- results with prices (dates chosen) ---------- */
    function facetsOf(list, f) {
      var count = function (skip, get) {
        var n = {};
        list.forEach(function (h) { if (!passes(h, f, skip)) return; [].concat(get(h)).forEach(function (v) { if (v !== '' && v != null && v !== 0) n[v] = (n[v] || 0) + 1; }); });
        return n;
      };
      var opts = function (o, name) { return Object.keys(o).map(function (k) { return { id: k, name: name(k), n: o[k] }; }); };
      var bn = { RO: { mk: 'Само ноќевање', en: 'Room only' }, BB: { mk: 'Ноќевање со појадок', en: 'Bed & breakfast' }, HB: { mk: 'Полупансион', en: 'Half board' }, FB: { mk: 'Полн пансион', en: 'Full board' }, AI: 'All inclusive' };
      return {
        zones: opts(count('zone', function (h) { return h.zone || ''; }), function (k) { var x = list.filter(function (h) { return h.zone === k && h.zone_label; })[0]; return x ? x.zone_label : k; }).sort(function (a, b) { return a.name.localeCompare(b.name); }),
        stars: opts(count('stars', function (h) { return h.stars || 0; }), function (k) { return k; }).sort(function (a, b) { return b.id - a.id; }),
        types: opts(count('type', function (h) { return h.type || ''; }), function (k) { return (H.types || {})[k] || k; }),
        boards: opts(count('board', function (h) { return h.boards || []; }), function (k) { return bn[k] || k; }),
        features: opts(count('feature', function (h) { return h.features || []; }), function (k) { return (H.features || {})[k] || k; }).sort(function (a, b) { return b.n - a.n; })
      };
    }
    function passes(h, f, skip) {
      if (f.name && (h.name + ' ' + (h.zone || '') + ' ' + (h.destination || '')).toLowerCase().indexOf(f.name) < 0) return false;
      if (skip !== 'price' && f.min !== null && h.price < f.min) return false;
      if (skip !== 'price' && f.max !== null && h.price > f.max) return false;
      if (skip !== 'zone' && f.zone && h.zone !== f.zone) return false;
      if (skip !== 'stars' && f.stars.length && f.stars.indexOf(String(h.stars || 0)) < 0) return false;
      if (skip !== 'type' && f.type.length && f.type.indexOf(h.type) < 0) return false;
      if (skip !== 'board' && f.board.length && !(h.boards || []).some(function (b) { return f.board.indexOf(b) > -1; })) return false;
      if (skip !== 'feature' && f.feature.length && f.feature.some(function (x) { return (h.features || []).indexOf(x) < 0; })) return false;
      if (f.free && !h.refundable) return false;
      return true;
    }
    function layout() {
      var q = state.q, f = state.filter;
      f.dest = q.destination || ''; f.country = '';
      var side = qbox('data-ht-f="name" value="' + esc(f.name) + '"') + datesBox(q, {})
        + (state.hi > state.lo ? '<div class="sta-ff"><h4>' + esc(L.price) + ' <output data-ht-price-out></output></h4><div class="sta-sb-range"><div class="sta-sb-range-fill" data-ht-fill></div>'
          + '<input type="range" min="' + state.lo + '" max="' + state.hi + '" step="1" value="' + (f.min || state.lo) + '" data-ht-lo><input type="range" min="' + state.lo + '" max="' + state.hi + '" step="1" value="' + (f.max || state.hi) + '" data-ht-hi></div></div>' : '')
        + '<div class="sta-ff"><label class="sta-ff-opt"><input type="checkbox" data-ht-free' + (f.free ? ' checked' : '') + '> <span>' + esc(L.onlyFree) + '</span></label></div>'
        + '<div data-hf-blocks></div>';
      var sort = '<label>' + esc(L.sort) + ' <select data-ht-sort>'
        + [['rec', L.sortRec], ['low', L.sortLow], ['high', L.sortHigh], ['stars', L.sortStars]].map(function (o) { return '<option value="' + o[0] + '"' + (f.sort === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('')
        + '</select></label>';
      shell(side, '', sort);
      $('[data-hf-note]', body).textContent = ' · ' + who(q);
      var side2 = $('.sta-finder-side', body);
      side2.addEventListener('input', function (e) { if (e.target.matches('[data-ht-f], [data-ht-lo], [data-ht-hi]')) onFilter(); });
      side2.addEventListener('change', function (e) {
        if (e.target.closest('[data-hd]')) return;
        // another destination / country: a new search with the same dates
        if (e.target.matches('[data-hf="dest"], [data-hf="country"]')) {
          var p = new URLSearchParams(location.search);
          p.delete('hotel'); p.delete('hn'); p.delete('h'); p.delete('sid');
          if (e.target.dataset.hf === 'dest' && e.target.value) { p.set('destination', e.target.value); location.href = location.pathname + '?' + p; }
          else if (e.target.dataset.hf === 'dest') { p.delete('destination'); location.href = location.pathname + '?' + p; }   // all destinations, same dates
          else { p.delete('destination'); p.delete('from'); p.delete('to'); if (e.target.value) p.set('country', e.target.value); location.href = location.pathname + '?' + p; }
          return;
        }
        onFilter();
      });
      $('[data-ht-sort]', body).addEventListener('change', function (e) { f.sort = e.target.value; state.shown = perPage; list(); });
      priceOut();
      renderBlocks();
      watchMore(function () { state.shown += perPage; list(true); });
    }
    function renderBlocks() {
      var f = state.filter, fc = facetsOf(state.list, f);
      // the destination of the search stays selectable (another one = a new search)
      fc.destinations = (H.destinations || []).map(function (d) { return { id: d.id, name: d.name, n: d.hotels, country: d.country_code }; });
      $('[data-hf-blocks]', body).innerHTML = blocks(fc, { country: '', dest: f.dest, zone: f.zone, stars: f.stars, type: f.type, board: f.board, feature: f.feature }, true);
    }
    function priceOut() {
      var lo = $('[data-ht-lo]', body), hi = $('[data-ht-hi]', body); if (!lo) return;
      if (+lo.value > +hi.value) { var t = lo.value; lo.value = hi.value; hi.value = t; }
      $('[data-ht-price-out]', body).textContent = money(lo.value, state.res.currency) + ' – ' + money(hi.value, state.res.currency);
      var fill = $('[data-ht-fill]', body); fill.style.left = ((lo.value - state.lo) / (state.hi - state.lo) * 100) + '%'; fill.style.right = (100 - (hi.value - state.lo) / (state.hi - state.lo) * 100) + '%';
    }
    function onFilter() {
      var f = state.filter, lo = $('[data-ht-lo]', body), hi = $('[data-ht-hi]', body);
      f.name = lat($('[data-ht-f="name"]', body).value.trim());
      if (lo) { priceOut(); f.min = +lo.value > state.lo ? +lo.value : null; f.max = +hi.value < state.hi ? +hi.value : null; }
      readSel($('[data-hf-blocks]', body), f);
      f.free = !!($('[data-ht-free]', body) || {}).checked;
      state.shown = perPage; list(); renderBlocks();
    }
    function filtered() {
      var f = state.filter;
      var out = state.list.filter(function (h) { return passes(h, f, ''); });
      var by = { rec: function (a, b) { return (b.featured - a.featured) || (a.price - b.price); }, low: function (a, b) { return a.price - b.price; }, high: function (a, b) { return b.price - a.price; }, stars: function (a, b) { return (b.stars || 0) - (a.stars || 0) || a.price - b.price; } };
      return out.sort(by[f.sort] || by.rec);
    }
    function list() {
      var items = filtered(), box = $('[data-hf-list]', body);
      $('[data-hf-count]', body).textContent = plural(items.length, L.hotel1, L.hotels);
      box.innerHTML = items.length ? items.slice(0, state.shown).map(function (h) { return stdCard(h, true); }).join('') : '<div class="sta-ht-empty">' + esc(L.noneFilter) + '</div>';
      $('[data-hf-more]', body).hidden = items.length <= state.shown;
    }

    /* ---------- all hotels without dates (no prices) ---------- */
    var cat = null, catTimer = null;
    function catalog(q) {
      state.q = q;
      var u = new URLSearchParams(location.search);
      cat = { sel: { country: (u.get('country') || '').toUpperCase(), dest: q.destination || '', zone: u.get('zone') || '', stars: state.filter.stars.slice(), type: [], board: state.filter.board.slice(), feature: [] }, name: '', page: 1, pages: 1, total: 0, items: [], loading: false };
      var side = qbox('data-hc-name') + datesBox(q, {})
        + '<div data-hf-blocks><div class="sta-ht-loading"><span class="sta-spin"></span></div></div>';
      shell(side, '', '');
      $('[data-hf-note]', body).textContent = ' · ' + L.catalogNote;
      $('.sta-finder-side', body).addEventListener('change', function (e) {
        if (!e.target.matches('[data-hf]')) return;
        if (e.target.dataset.hf === 'country') { $$('[data-hf="dest"], [data-hf="zone"]', body).forEach(function (x) { x.value = ''; }); }
        if (e.target.dataset.hf === 'dest') { var z = $('[data-hf="zone"]', body); if (z) z.value = ''; }
        catFilter();
      });
      $('[data-hc-name]', body).addEventListener('input', function () { clearTimeout(catTimer); catTimer = setTimeout(catFilter, 350); });
      watchMore(function () { if (!cat.loading && cat.page < cat.pages) { cat.page++; catLoad(); } });
      catLoad();
    }
    function catFilter() {
      readSel($('[data-hf-blocks]', body), cat.sel);
      cat.name = lat($('[data-hc-name]', body).value.trim());
      // the address keeps the choice (back button, sharing)
      if (history.replaceState) {
        var u = new URL(location.href);
        ['country', 'destination', 'zone'].forEach(function (k) { u.searchParams.delete(k); });
        if (cat.sel.country) u.searchParams.set('country', cat.sel.country);
        if (cat.sel.dest) u.searchParams.set('destination', cat.sel.dest);
        if (cat.sel.zone) u.searchParams.set('zone', cat.sel.zone);
        history.replaceState(null, '', u);
      }
      cat.page = 1; cat.items = []; catLoad();
    }
    function catLoad() {
      var box = $('[data-hf-list]', body), more = $('[data-hf-more]', body), s2 = cat.sel;
      if (cat.page === 1) box.innerHTML = '<div class="sta-ht-loading"><span class="sta-spin"></span>' + esc(L.loading) + '</div>';
      cat.loading = true;
      var p = new URLSearchParams({ page: cat.page, per_page: perPage });
      if (s2.country) p.set('country', s2.country);
      if (s2.dest) p.set('destination', s2.dest);
      if (s2.zone) p.set('zone', s2.zone);
      ['stars', 'type', 'board', 'feature'].forEach(function (k) { if (s2[k].length) p.set(k, s2[k].join(',')); });
      if (cat.name) p.set('q', cat.name);
      var mine = cat;
      api('hotels/catalog?' + p).then(function (r) {
        if (mine !== cat || !$('[data-hf-list]', body)) return;
        cat.loading = false;
        if (!r.ok) { box.innerHTML = '<div class="sta-ht-empty is-error">' + K.errors(r.body) + '</div>'; more.hidden = true; return; }
        var d = r.body.data, fresh = d.hotels || [];
        cat.pages = d.pages; cat.total = d.total;
        $('[data-hf-count]', body).textContent = plural(d.total, L.hotel1, L.hotels);
        if (cat.page === 1 && d.facets) $('[data-hf-blocks]', body).innerHTML = blocks(d.facets, s2, false);
        if (cat.page === 1) box.innerHTML = '';
        if (!cat.items.length && !fresh.length) {
          box.innerHTML = '<div class="sta-ht-empty">' + esc(L.noneFilter)
            + ' <button type="button" class="sta-btn sta-btn-sm" data-hc-all>' + esc(L.showAll) + '</button></div>';
        } else {
          box.insertAdjacentHTML('beforeend', fresh.map(function (h) { return stdCard(h, false); }).join(''));
        }
        cat.items = cat.items.concat(fresh);
        more.hidden = cat.page >= cat.pages;
      });
    }
    // hotel page without dates: "Check prices" shows the dates box right there
    function pickDates(id, name) {
      var sum = $('.sta-ht-sum', body);
      var q = state.q || query();
      if (sum) {
        sum.innerHTML = datesBox({ from: q.from, to: q.to, adults: q.adults, ages: q.ages }, { hotel: id, name: name });
        sum.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var f = $('[data-hd-from]', sum); if (f) f.focus();
      }
    }
    body.addEventListener('click', function (e) {
      if (e.target.closest('[data-hf-clear]')) { location.href = location.pathname; return; }
      var go = e.target.closest('[data-hd-go]');
      if (go) { goDates(go.closest('[data-hd]')); return; }
      // cards: open the hotel page
      var a = e.target.closest('a[href^="#h="]');
      if (a) { e.preventDefault(); open(decodeURIComponent(a.getAttribute('href').slice(3))); return; }
      var o = e.target.closest('[data-ht-open]');
      if (o) { e.preventDefault(); open(o.dataset.htOpen); return; }
      if (e.target.closest('[data-ht-back]')) { e.preventDefault(); history.back(); return; }
      // no hotel for this destination / filters: all hotels again
      if (e.target.closest('[data-hc-all]')) {
        var nmI = $('[data-hc-name]', body);
        if (nmI) nmI.value = '';
        $$('select[data-hf]', body).forEach(function (x) { x.value = ''; });
        $$('[data-hf]:checked', body).forEach(function (c) { c.checked = false; });
        catFilter();
        return;
      }
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
        // phones: "Check prices" (or the chosen dates + to the rooms) above the gallery
        + '<div class="sta-ht-mcta">' + (q.from
          ? '<span><b>' + esc(dmy(q.from)) + ' – ' + esc(dmy(q.to)) + '</b> · ' + esc(who(q)) + '</span><a href="#sta-ht-rooms" class="sta-btn sta-btn-sm">' + esc(L.roomsTitle) + ' ↓</a>'
          : '<button type="button" class="sta-btn sta-btn-block" data-hc-dates="' + esc(h.id || q.open) + '">' + esc(L.checkPrices) + '</button>') + '</div>'
        + gal
        + '<div class="sta-ht-cols"><div>' + (desc ? '<div class="sta-ht-desc' + (desc.length > 700 ? ' is-cut' : '') + '"><p>' + esc(desc).replace(/\n+/g, '</p><p>') + '</p></div>' + (desc.length > 700 ? '<button type="button" class="sta-ht-link" data-ht-more-desc>' + esc(L.readMore) + '</button>' : '') : '')
        + (fac ? '<h3>' + esc(L.facilities) + '</h3><ul class="sta-ht-facs">' + fac + '</ul>' : '') + '</div>'
        + (q.from ? '<aside class="sta-ht-sum"><b>' + esc(dmy(q.from)) + ' – ' + esc(dmy(q.to)) + '</b><br>' + esc(who(q)) + '</aside></div>'
          : '<aside class="sta-ht-sum"><p>' + esc(L.pickDates) + '</p><button type="button" class="sta-btn" data-hc-dates="' + esc(h.id || q.open) + '">' + esc(L.checkPrices) + '</button></aside></div>')
        + '<h3 class="sta-ht-rt" id="sta-ht-rooms">' + esc(L.roomsTitle) + '</h3>'
        + (q.from ? (rooms || '<div class="sta-ht-empty">' + esc(L.none) + '</div><div data-ht-alt></div>') : '<div class="sta-ht-empty">' + esc(L.pickDates) + ' <button type="button" class="sta-btn sta-btn-sm" data-hc-dates="' + esc(h.id || q.open) + '">' + esc(L.checkPrices) + '</button></div>') + '</div>';
      bindLightbox($('[data-sta-lightbox]', body));
      if (q.from && !rooms) alternatives(Object.assign({}, q, { hotel: h.id || q.open, destination: '' }), $('[data-ht-alt]', body));
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
      links.forEach(function (a, n) {
        a.setAttribute('data-elementor-open-lightbox', 'no');
        a.addEventListener('click', function (e) {
          e.preventDefault(); e.stopPropagation();
          // the full-screen gallery of the site (counter, swipe, thumbnails) when it is there
          if (window.STAGallery) {
            var h = state.hotel || {};
            window.STAGallery({ title: h.name || '', date: '', photos: links.map(function (l) { var im = l.querySelector('img'); return { url: l.href, thumb: im ? im.src : l.href }; }) }, n);
            return;
          }
          i = n; show();
        });
      });
    }

    /* ---------- booking ---------- */
    // card online / at the agency (rule of the agency for hotels)
    function payChoice(total, cur) {
      var p = H.payment || {};
      if (!p.online) return '';
      var now = Math.round(total * (p.percent || 100)) / 100;
      return '<fieldset class="sta-pay"><legend>' + esc(L.payHow || L.payTitle) + '</legend>'
        + '<label class="sta-pay-opt"><input type="radio" name="payment_method" value="card" checked><span class="sta-pay-txt"><b>' + esc(p.title || L.payCard) + '</b><small>'
        + esc(p.mode === 'full' ? L.payFull : fmt(L.payDeposit, p.percent).replace('%%', '%')) + ' <b>' + money(now, cur) + '</b></small>' + (p.note ? '<small class="sta-muted">' + esc(p.note) + '</small>' : '') + '</span>'
        + '<span class="sta-pay-cards" aria-hidden="true"><i>VISA</i><i>Mastercard</i><i>Maestro</i></span></label>'
        + (p.agency ? '<label class="sta-pay-opt"><input type="radio" name="payment_method" value="agency"><span class="sta-pay-txt"><b>' + esc(L.payAgency) + '</b><small>' + esc(L.payAgencyNote) + ' <b>' + money(total, cur) + '</b></small></span></label>' : '')
        + '<p class="sta-pay-lock">🔒 ' + esc(L.payLock) + '</p></fieldset>';
    }
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
        + payChoice(rate.price, h.currency)
        + (H.terms || '')
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
          payment_method: fd.get('payment_method') || null, return_url: location.href.split('#')[0],
          terms_accepted: fd.get('terms_accepted') ? true : null, terms_url: fd.get('terms_url') || null,
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
          if (d.payment && d.payment.method === 'card' && d.payment.url) {
            K.msg(form, '<span class="sta-spin"></span> <b>' + esc(L.payGo) + '</b> ' + esc(d.reference || ''), true);
            setTimeout(function () { location.href = d.payment.url; }, 700);
            return;
          }
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
