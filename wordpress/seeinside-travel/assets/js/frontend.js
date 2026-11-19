/* SeeInside Travel – front-end (no dependencies) */
(function () {
  'use strict';
  var C = window.STA || { rest: '/wp-json/seeinside-travel/v1/', i18n: {} };
  var T = C.i18n || {};
  var started = Date.now();

  function $(s, el) { return (el || document).querySelector(s); }
  function $$(s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); }
  function money(v, cur) {
    cur = cur || C.currency || 'EUR';
    var n = Number(v || 0).toLocaleString('de-DE', { minimumFractionDigits: v % 1 ? 2 : 0, maximumFractionDigits: 2 });
    return cur === 'MKD' ? n + ' ден.' : (cur === 'EUR' ? '€' : cur === 'USD' ? '$' : cur + ' ') + n;
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function t(v) { if (!v || typeof v !== 'object') return v || ''; return v[C.lang] || v.mk || v.en || ''; }
  /* card payment: the amount paid now next to the choice, and the button says "Book and pay" */
  function payInfo(form, sum, cur) {
    var box = $('[data-sta-pay]', form); if (!box) return;
    var amt = $('[data-sta-pay-amount]', box), pct = +box.dataset.percent || 100;
    if (amt) amt.textContent = sum ? '(' + money(Math.round(sum * pct) / 100, cur || box.dataset.currency) + ')' : '';
  }
  function payButton(form) {
    var box = $('[data-sta-pay]', form), btn = $('[type="submit"]', form); if (!box || !btn) return;
    var card = $('[name="payment_method"]:checked', form); card = !card || card.value === 'card';
    if (!btn.dataset.label) btn.dataset.label = btn.textContent;
    btn.textContent = card ? (T.bookAndPay || btn.dataset.label) : btn.dataset.label;
  }
  $$('[data-sta-pay]').forEach(function (box) { var f = box.closest('form'); if (!f) return; f.addEventListener('change', function (e) { if (e.target.name === 'payment_method') payButton(f); }); payButton(f); });
  /* after the booking: the bank's page opens (card) */
  function payGo(form, d) {
    var p = d && d.payment;
    if (!p || p.method !== 'card' || !p.url) return false;
    msg(form, '<span class="sta-spin"></span> <b>' + esc(T.payGo) + '</b>' + (d.reference ? '<span class="sta-muted">' + esc(T.reference) + ':</span><span class="sta-ref">' + esc(d.reference) + '</span>' : ''), true);
    setTimeout(function () { location.href = p.url; }, 700);
    return true;
  }
  function msg(form, html, ok) {
    var box = $('[data-sta-msg]', form); if (!box) return;
    box.className = 'sta-msg ' + (ok ? 'is-ok' : 'is-error'); box.innerHTML = html;
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function errors(body) {
    if (body && body.errors) { return Object.keys(body.errors).map(function (k) { return esc([].concat(body.errors[k]).join(' ')); }).join('<br>'); }
    return esc((body && body.message) || T.error);
  }
  // clear the red mark as soon as the visitor fixes a field
  document.addEventListener('change', function (e) {
    var el = e.target; if (!el || !el.closest || !el.closest('.sta-form')) return;
    if (el.classList.contains('is-invalid')) el.classList.remove('is-invalid');
    var lbl = el.closest('.sta-check'); if (lbl && el.checked) lbl.classList.remove('is-invalid');
  });
  function validate(form) {
    var ok = true;
    $$('[required]', form).forEach(function (el) {
      var bad = el.type === 'checkbox' ? !el.checked : el.type === 'radio' ? !$$('[name="' + el.name + '"]', form).some(function (r) { return r.checked; }) : !String(el.value || '').trim() || (el.validity && !el.validity.valid);
      el.classList.toggle('is-invalid', bad); if (bad) ok = false;
      var lbl = el.type === 'checkbox' && el.closest('.sta-check'); if (lbl) lbl.classList.toggle('is-invalid', bad);
    });
    if (!ok) {
      // say what is missing – a theme may hide checkboxes, so a silent focus is not enough
      $$('.sta-pax-row', form).forEach(function (r) { if ($('.is-invalid', r)) { r.classList.add('is-open'); var h = $('.sta-pax-head', r); if (h) h.setAttribute('aria-expanded', 'true'); } });
      var miss = $$('.is-invalid', form), consent = miss.some(function (x) { return x.classList.contains('sta-consent'); });
      msg(form, esc(consent && miss.length <= 2 ? T.needConsent : T.needFields), false);
      var first = miss[0]; if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (first.focus) first.focus({ preventScroll: true }); }
    }
    return ok;
  }
  /* proxy path on this site → address in the app when forms go directly (push mode) */
  function direct(path, opts) {
    var u = new URL(path, 'http://x/'), q = u.searchParams, name = u.pathname.replace(/^\//, '');
    opts = opts || {};
    if (name === 'booking') {
      var d = JSON.parse(opts.body || '{}'); d.locale = C.lang; d.message = ((d.message || '') + '\n\n[' + C.site + ']').trim();
      return [C.direct + 'bookings', Object.assign({}, opts, { body: JSON.stringify(d), headers: { 'Content-Type': 'application/json', Accept: 'application/json' } })];
    }
    if (name === 'review') {
      var rv = JSON.parse(opts.body || '{}'); rv.locale = C.lang; rv.page_url = location.href;
      return [C.direct + 'reviews', Object.assign({}, opts, { body: JSON.stringify(rv), headers: { 'Content-Type': 'application/json', Accept: 'application/json' } })];
    }
    if (name === 'visa') { opts.body.append('locale', C.lang); return [C.direct + 'visa-applications', Object.assign({}, opts, { headers: { Accept: 'application/json' } })]; }
    if (name === 'hotels/book') {
      var hb = JSON.parse(opts.body || '{}'); hb.locale = C.lang; hb.message = ((hb.message || '') + '\n\n[' + C.site + ']').trim();
      return [C.direct + 'hotels/book', Object.assign({}, opts, { body: JSON.stringify(hb), headers: { 'Content-Type': 'application/json', Accept: 'application/json' } })];
    }
    if (name === 'hotels/hotel') { var hid = q.get('id'); q.delete('id'); return [C.direct + 'hotels/' + encodeURIComponent(hid) + '?' + q, { headers: { Accept: 'application/json' } }]; }
    if (name.indexOf('hotels/') === 0) return [C.direct + name + '?' + q, { headers: { Accept: 'application/json' } }];
    var slug = q.get('slug'); q.delete('slug'); q.delete('website'); q.delete('sta_ts');
    if (name === 'quote') return [C.direct + 'accommodations/' + encodeURIComponent(slug) + '/quote?' + q, { headers: { Accept: 'application/json' } }];
    if (name === 'terms') return [C.direct + 'accommodations/' + encodeURIComponent(slug) + '/terms', { headers: { Accept: 'application/json' } }];
    if (name === 'available') return [C.direct + 'accommodations-available?' + q, { headers: { Accept: 'application/json' } }];
    if (name === 'availability') return [C.direct + 'accommodations/' + encodeURIComponent(slug) + '/availability?' + q, { headers: { Accept: 'application/json' } }];
    if (name === 'status') {
      var ref = (q.get('reference') || '').toUpperCase();
      return [C.direct + (ref.charAt(0) === 'V' ? 'visa-applications/' : 'bookings/') + encodeURIComponent(ref) + '?email=' + encodeURIComponent(q.get('email') || ''), { headers: { Accept: 'application/json' } }];
    }
    return [C.rest + path, opts];
  }
  function api(path, opts) {
    var target = C.direct ? direct(path, opts) : [C.rest + path, opts];
    return fetch(target[0], target[1]).then(function (r) { return r.json().catch(function () { return {}; }).then(function (b) { return { ok: r.ok, status: r.status, body: b }; }); });
  }

  /* ---------- lightbox ---------- */
  $$('[data-sta-lightbox]').forEach(function (g) {
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
    document.addEventListener('keydown', function (e) {
      if (!box) return;
      if (e.key === 'Escape') { box.remove(); box = null; }
      if (e.key === 'ArrowRight') { i = (i + 1) % links.length; show(); }
      if (e.key === 'ArrowLeft') { i = (i - 1 + links.length) % links.length; show(); }
    });
  });

  /* ---------- gallery as slider ---------- */
  $$('[data-sta-gslider]').forEach(function (g) {
    var track = $('.sta-gs-track', g), items = $$('.sta-gs-item', g), n = $('[data-sta-gs-n]', g), th = g.nextElementSibling && g.nextElementSibling.matches('[data-sta-gs-thumbs]') ? $$('button', g.nextElementSibling) : [];
    function cur() { return Math.round(track.scrollLeft / (track.clientWidth || 1)); }
    function go(k) { k = (k + items.length) % items.length; track.scrollTo({ left: k * track.clientWidth }); }
    $$('.sta-gs-nav', g).forEach(function (b) { b.addEventListener('click', function () { go(cur() + (+b.dataset.dir)); }); });
    th.forEach(function (b) { b.addEventListener('click', function () { go(+b.dataset.i); }); });
    track.addEventListener('scroll', function () {
      var k = cur(); if (n) n.textContent = (k + 1) + ' / ' + items.length;
      th.forEach(function (b, x) { b.classList.toggle('is-on', x === k); if (x === k) b.scrollIntoView({ block: 'nearest', inline: 'nearest' }); });
    }, { passive: true });
  });

  /* ---------- slider ---------- */
  $$('.sta-slider').forEach(function (s) {
    var slides = $$('.sta-slides > *', s), dots = $$('.sta-dots span', s), i = 0, timer;
    if (slides.length < 2) return;
    function go(n) {
      i = (n + slides.length) % slides.length;
      slides.forEach(function (el, k) { var sl = el.classList.contains('sta-slide') ? el : $('.sta-slide', el); sl.classList.toggle('is-active', k === i); el.classList.toggle('is-active', k === i); });
      dots.forEach(function (d, k) { d.classList.toggle('is-active', k === i); });
    }
    function auto() { var sec = +s.dataset.autoplay; clearInterval(timer); if (sec > 0) timer = setInterval(function () { go(i + 1); }, sec * 1000); }
    $('.sta-slider-next', s).addEventListener('click', function () { go(i + 1); auto(); });
    $('.sta-slider-prev', s).addEventListener('click', function () { go(i - 1); auto(); });
    dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); auto(); }); });
    var x0 = null;
    s.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    s.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) { go(i + (dx < 0 ? 1 : -1)); auto(); } x0 = null; });
    go(0); auto();
  });

  /* ---------- search ---------- */
  $$('[data-sta-search]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var tab = $('[name="sta_t"]:checked', f) || $('[name="sta_t"]', f);
      var url = new URL(tab ? tab.value : location.href, location.href);
      $$('select, input', f).forEach(function (el) {
        if (el.name === 'sta_t' || !el.name) return;
        if ((el.type === 'checkbox' && el.checked) || (el.type !== 'checkbox' && el.value)) url.searchParams.set(el.name, el.value);
      });
      location.href = url.toString();
    });
  });

  /* ---------- booking forms ---------- */
  $$('[data-sta-booking]').forEach(function (form) {
    var type = form.dataset.type, cur = form.dataset.currency, totalBox = $('[data-sta-total]', form);

    var children = $('[name="children"]', form), agesBox = $('[data-sta-ages]', form);
    function ages() {
      if (!agesBox) return;
      var n = Math.max(0, Math.min(10, +(children.value || 0))), have = $$('input', agesBox).length;
      for (var k = have; k < n; k++) {
        var l = document.createElement('label'); l.className = 'sta-field';
        l.innerHTML = '<span>' + esc(T.childAge) + ' ' + (k + 1) + '</span><input type="number" min="0" max="17" name="children_ages[]" required>';
        agesBox.appendChild(l);
      }
      $$('label', agesBox).slice(n).forEach(function (l) { l.remove(); });
    }
    children && children.addEventListener('input', ages);

    // details remembered on this device (like Booking.com)
    var KEY = 'sta_customer';
    try {
      var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
      if (saved) ['name', 'phone', 'email', 'city'].forEach(function (k) { var el = $('[name="customer[' + k + ']"]', form); if (el && !el.value && saved[k]) el.value = saved[k]; });
    } catch (e) {}
    function remember(data) {
      var box = $('[data-sta-remember]', form);
      try { if (box && box.checked) localStorage.setItem(KEY, JSON.stringify(data.customer || {})); else if (box) localStorage.removeItem(KEY); } catch (e) {}
    }

    // one row per passenger (name, date of birth, passport, phone) – goes into the contract and the confirmation
    var paxBox = $('[data-sta-pax]', form), paxRows = paxBox && $('[data-sta-pax-rows]', paxBox);
    function pax() {
      if (!paxRows) return;
      var ad = Math.max(0, Math.min(20, +(($('[name="adults"]', form) || {}).value || 0))), ch = Math.max(0, Math.min(10, +(children ? children.value : 0) || 0)), n = ad + ch;
      var rows = $$('.sta-pax-row', paxRows);
      for (var k = rows.length; k < n; k++) {
        var r = document.createElement('div'); r.className = 'sta-pax-row' + (k === 0 ? ' is-open' : '');
        r.innerHTML = '<button type="button" class="sta-pax-head" aria-expanded="' + (k === 0) + '"><span class="sta-pax-title"></span><span class="sta-pax-sum"></span><span class="sta-pax-ok" aria-hidden="true">✓</span><span class="sta-pax-arrow" aria-hidden="true"></span></button><div class="sta-row sta-pax-body">'
          + '<label class="sta-field"><span>' + esc(T.firstName) + ' *</span><input name="passengers[' + k + '][first_name]" required autocomplete="off"></label>'
          + '<label class="sta-field"><span>' + esc(T.lastName) + ' *</span><input name="passengers[' + k + '][last_name]" required autocomplete="off"></label>'
          + '<label class="sta-field"><span>' + esc(T.birthDate) + ' *</span><input type="date" name="passengers[' + k + '][birth_date]" required max="' + new Date().toISOString().slice(0, 10) + '"></label>'
          + (paxBox.dataset.passport ? '<label class="sta-field"><span>' + esc(T.passport) + '</span><input name="passengers[' + k + '][passport]" autocomplete="off"></label>' : '')
          + (paxBox.dataset.phone ? '<label class="sta-field"><span>' + esc(T.phone) + '</span><input type="tel" name="passengers[' + k + '][phone]"></label>' : '')
          + '</div>';
        paxRows.appendChild(r);
      }
      $$('.sta-pax-row', paxRows).forEach(function (r, k) {
        if (k >= n) { r.remove(); return; }
        $('.sta-pax-title', r).textContent = T.passenger + ' ' + (k + 1) + ' · ' + (k < ad ? T.adult : T.child);
        paxSum(r);
      });
      if (!$('.sta-pax-row.is-open', paxRows) && rows.length === 0) { var f = $('.sta-pax-row', paxRows); if (f) paxOpen(f, true); }
      paxBox.hidden = n === 0;
    }
    form.addEventListener('input', function (e) { if (e.target.name === 'adults' || e.target.name === 'children') pax(); });
    form.addEventListener('change', function (e) { if (e.target.name === 'adults' || e.target.name === 'children') pax(); });
    form.addEventListener('sta:pax', pax);
    // each passenger folds: the header shows the name when filled in, a click opens / closes the fields
    function paxOpen(r, open) {
      r.classList.toggle('is-open', open); $('.sta-pax-head', r).setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    function paxSum(r) {
      var v = function (n) { var el = $('[name$="[' + n + ']"]', r); return el ? String(el.value || '').trim() : ''; };
      var bd = v('birth_date'), name = (v('first_name') + ' ' + v('last_name')).trim();
      $('.sta-pax-sum', r).textContent = name ? name + (bd ? ' · ' + bd.split('-').reverse().join('.') : '') : '';
      r.classList.toggle('is-done', $$('[required]', r).every(function (el) { return String(el.value || '').trim() && (!el.validity || el.validity.valid); }));
    }
    if (paxRows) {
      paxRows.addEventListener('click', function (e) {
        var h = e.target.closest('.sta-pax-head'); if (!h) return;
        var r = h.parentNode, open = !r.classList.contains('is-open');
        if (open) $$('.sta-pax-row.is-open', paxRows).forEach(function (x) { if (x !== r) paxOpen(x, false); });
        paxOpen(r, open);
        if (open) { var first = $('input', r); if (first && !first.value) first.focus({ preventScroll: true }); }
      });
      paxRows.addEventListener('input', function (e) { var r = e.target.closest('.sta-pax-row'); if (r) paxSum(r); });
      paxRows.addEventListener('change', function (e) { var r = e.target.closest('.sta-pax-row'); if (r) paxSum(r); });
    }
    pax();

    // fixed terms: the term sets the dates
    var termSel = $('[data-sta-term]', form);
    termSel && termSel.addEventListener('change', function () {
      var o = termSel.selectedOptions[0];
      $('[name="date_from"]', form).value = o && o.value ? o.dataset.from : '';
      $('[name="date_to"]', form).value = o && o.value ? o.dataset.to : '';
      var box = $('[data-sta-offers]', form); if (box) box.innerHTML = '';
      $('[name="room_id"]', form).value = '';
      if (o && o.value) { var q = $('[data-sta-quote]', form); if (q) q.click(); }
    });

    function estimate() {
      if (!totalBox) return;
      var adults = +($('[name="adults"]', form) || {}).value || 0, kids = +(children ? children.value : 0) || 0, sum = null;
      if (type === 'tour') {
        var o = ($('[name="departure_id"]', form) || {}).selectedOptions; o = o && o[0];
        if (o && o.value) {
          sum = adults * (+o.dataset.adult || 0) + kids * (+o.dataset.child || +o.dataset.adult || 0);
          var p = $('[name="departure_place_id"]', form); var po = p && p.selectedOptions[0];
          if (po) sum += (+po.dataset.surcharge || 0) * (adults + kids);
          $$('[name="extras[]"]', form).forEach(function (x) { if (x.checked) sum += (+x.dataset.price || 0) * (x.dataset.per === 'per_person' ? adults + kids : 1); });
        }
      } else if (type === 'event') {
        var tk = ($('[name="ticket_id"]', form) || {}).selectedOptions; tk = tk && tk[0];
        if (tk) sum = (+tk.dataset.price || 0) * (+($('[name="quantity"]', form) || {}).value || 1);
      } else if (type === 'accommodation') {
        var sel = $('.sta-offer.is-selected', form); if (sel) sum = +sel.dataset.total;
        var trb = $('[data-sta-transport]', form), trc = trb && $('[name="with_transport"]', trb);
        if (trb) { var pl = $('[data-sta-transport-place]', trb); if (pl) pl.hidden = !trc.checked; }
        if (sum !== null && trc && trc.checked) {
          var add = adults * (+trb.dataset.adult || 0);
          var agesIn = $$('[name="children_ages[]"]', form);
          for (var ci = 0; ci < kids; ci++) {
            var age = agesIn[ci] && agesIn[ci].value !== '' ? +agesIn[ci].value : null;
            add += age !== null && age < +trb.dataset.free ? 0 : age !== null && age > +trb.dataset.childTo ? +trb.dataset.adult || 0 : +trb.dataset.child || 0;
          }
          sum += add;
        }
      }
      totalBox.hidden = sum === null;
      if (sum !== null) totalBox.innerHTML = '<span>' + esc(T.estimate) + '</span><b>' + money(sum, cur) + '</b>';
      payInfo(form, sum, cur);
    }
    form.addEventListener('change', estimate); form.addEventListener('input', estimate);

    // hotel: available rooms & prices
    var quoteBtn = $('[data-sta-quote]', form);
    quoteBtn && quoteBtn.addEventListener('click', function () {
      var from = $('[name="date_from"]', form).value, to = $('[name="date_to"]', form).value, box = $('[data-sta-offers]', form);
      if (!from || !to) { if (termSel) { termSel.classList.add('is-invalid'); termSel.focus(); } else validate(form); return; }
      box.innerHTML = '<p class="sta-muted">' + esc(T.checking) + '</p>';
      var q = new URLSearchParams({ slug: form.dataset.slug, from: from, to: to, adults: $('[name="adults"]', form).value, children: children ? children.value : 0 });
      api('quote?' + q).then(function (r) {
        var offers = (r.body && r.body.data) || [];
        if (!r.ok) { box.innerHTML = '<p class="sta-msg is-error">' + errors(r.body) + '</p>'; return; }
        if (!offers.length) { box.innerHTML = '<p class="sta-muted">' + esc(T.noOffers) + '</p>'; return; }
        box.innerHTML = offers.map(function (o) {
          return '<label class="sta-offer" data-room="' + o.room_id + '" data-total="' + o.total + '"><span><b style="color:inherit;font-size:1rem">' + esc(o.room) + '</b><br><small class="sta-muted">' + esc(o.board || '') + ' · ' + o.nights + ' ' + esc(T.nights) + (o.available ? ' · ' + o.available + ' ' + esc(T.available) : '') + '</small></span><b>' + money(o.total, o.currency) + '</b></label>';
        }).join('');
        var pre = $('[name="room_id"]', form).value;
        $$('.sta-offer', box).forEach(function (el, k) {
          if ((pre && el.dataset.room === pre) || (!pre && k === 0)) el.classList.add('is-selected');
          el.addEventListener('click', function () { $$('.sta-offer', box).forEach(function (x) { x.classList.remove('is-selected'); }); el.classList.add('is-selected'); $('[name="room_id"]', form).value = el.dataset.room; estimate(); });
        });
        var s = $('.sta-offer.is-selected', box); if (s) $('[name="room_id"]', form).value = s.dataset.room;
        estimate();
      }).catch(function () { box.innerHTML = '<p class="sta-msg is-error">' + esc(T.error) + '</p>'; });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!validate(form)) return;
      var fd = new FormData(form), data = { type: type, slug: form.dataset.slug, website: fd.get('website') || '', sta_ts: started, customer: {}, extras: [], children_ages: [], return_url: location.href.split('#')[0] }, px = [];
      fd.forEach(function (v, k) {
        var m = k.match(/^customer\[(\w+)\]$/), pm = k.match(/^passengers\[(\d+)\]\[(\w+)\]$/);
        if (pm) { px[+pm[1]] = px[+pm[1]] || {}; if (v) px[+pm[1]][pm[2]] = v; }
        else if (m) data.customer[m[1]] = v;
        else if (k === 'extras[]') data.extras.push(+v);
        else if (k === 'children_ages[]') data.children_ages.push(+v);
        else if (k !== 'website') data[k] = v;
      });
      $$('[name="extras[]"]:checked:disabled', form).forEach(function (x) { if (data.extras.indexOf(+x.value) < 0) data.extras.push(+x.value); });
      if (px.length) data.passengers = px.filter(Boolean);
      // contact person = first passenger when the name was left empty
      if (data.passengers && data.passengers[0] && !data.customer.name) data.customer.name = (data.passengers[0].first_name + ' ' + data.passengers[0].last_name).trim();
      ['adults', 'children', 'quantity', 'departure_id', 'departure_place_id', 'ticket_id', 'room_id', 'charter_id', 'term_id'].forEach(function (k) { if (data[k] !== undefined) data[k] = data[k] === '' ? null : +data[k]; });
      if (data.transport_only) data.transport_only = true;
      if (type === 'accommodation') { data.with_transport = !!data.with_transport; if (!data.with_transport) delete data.departure_place_id; }
      var btn = $('[type="submit"]', form), label = btn.textContent; btn.disabled = true; btn.textContent = T.sending;
      api('booking', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(function (r) {
        if (r.ok && r.body && r.body.data) {
          var d = r.body.data;
          if (payGo(form, d)) { remember(data); return; }
          msg(form, '<b>' + esc(T.thanks) + '</b><span class="sta-muted">' + esc(T.reference) + ':</span><span class="sta-ref">' + esc(d.reference) + '</span>' + (d.total ? esc(T.total) + ': <b>' + money(d.total, d.currency) + '</b>' : '')
            + (d.qr_url ? '<div class="sta-qr"><img src="' + esc(d.qr_url) + '" width="132" height="132" alt="QR ' + esc(d.reference) + '"><div><b>' + esc(T.qrTitle) + '</b><small>' + esc(T.qrHint) + '</small><a href="' + esc(d.qr_url) + '" download="' + esc(d.reference) + '.png" target="_blank" rel="noopener">' + esc(T.qrSave) + '</a></div></div>' : '')
            + (d.manage_url ? '<p class="sta-manage"><a class="sta-btn" href="' + esc(d.manage_url) + '" target="_blank" rel="noopener">' + esc(T.manage) + ' →</a><small>' + esc(T.manageHint) + '</small></p>' : '')
            , true);
          remember(data);
          var keep = {}; ['name', 'phone', 'email', 'city'].forEach(function (k) { var el = $('[name="customer[' + k + ']"]', form); if (el) keep[k] = el.value; });
          form.reset(); ages(); pax(); estimate();
          var rb = $('[data-sta-remember]', form);
          if (rb && rb.checked) Object.keys(keep).forEach(function (k) { var el = $('[name="customer[' + k + ']"]', form); if (el) el.value = keep[k]; });
          document.dispatchEvent(new CustomEvent('sta:booked', { detail: d }));
        } else msg(form, errors(r.body), false);
      }).catch(function () { msg(form, esc(T.error), false); }).then(function () { btn.disabled = false; btn.textContent = label; });
    });
    estimate();
  });

  // "Book" on a departure / room → preselect in the form
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-sta-departure],[data-sta-room]');
    if (!a) return;
    var form = $('form[data-sta-booking]:not([data-quick])'); if (!form) return;
    if (a.dataset.staDeparture) { var s = $('[name="departure_id"]', form); if (s) { s.value = a.dataset.staDeparture; s.dispatchEvent(new Event('change', { bubbles: true })); } }
    if (a.dataset.staRoom) { var r = $('[name="room_id"]', form); if (r) r.value = a.dataset.staRoom; }
  });

  /* ---------- charters ---------- */
  var dlg = $('[data-sta-charter-dialog]');
  if (dlg) {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-sta-charter]');
      if (b) {
        $('[name="charter_id"]', dlg).value = b.dataset.staCharter;
        $('[data-title]', dlg).textContent = b.dataset.title;
        $('[data-transport-only]', dlg).hidden = b.dataset.transport !== '1';
        $('[data-sta-msg]', dlg).innerHTML = '';
        dlg.showModal ? dlg.showModal() : dlg.setAttribute('open', '');
      }
      if (e.target.closest('[data-close]')) dlg.close ? dlg.close() : dlg.removeAttribute('open');
    });
  }

  /* ---------- visa application ---------- */
  $$('[data-sta-visa]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!validate(form)) return;
      var fd = new FormData(form); fd.append('sta_ts', started); fd.append('return_url', location.href.split('#')[0]);
      var btn = $('[type="submit"]', form), label = btn.textContent; btn.disabled = true; btn.textContent = T.sending;
      api('visa', { method: 'POST', body: fd }).then(function (r) {
        if (r.ok && r.body && r.body.data) {
          if (payGo(form, r.body.data)) return;
          msg(form, '<b>' + esc(T.thanks) + '</b><span class="sta-muted">' + esc(T.reference) + ':</span><span class="sta-ref">' + esc(r.body.data.reference) + '</span>', true);
          form.reset();
        } else msg(form, errors(r.body), false);
      }).catch(function () { msg(form, esc(T.error), false); }).then(function () { btn.disabled = false; btn.textContent = label; });
    });
  });

  /* ---------- booking / visa status ---------- */
  $$('[data-sta-status]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!validate(form)) return;
      var q = new URLSearchParams({ reference: $('[name="reference"]', form).value.trim(), email: $('[name="email"]', form).value.trim(), website: $('[name="website"]', form).value, sta_ts: started });
      api('status?' + q).then(function (r) {
        if (!r.ok || !r.body.data) { msg(form, esc(T.notFound), false); return; }
        var d = r.body.data, rows = [
          [T.reference, d.reference], ['', d.offer || d.visa || ''],
          [T.status, t(d.status && d.status.label) || (d.status && d.status.value)], [T.payment, t(d.payment_status && d.payment_status.label) || ''],
        ];
        if (d.total != null) rows.push([T.total, money(d.total, d.currency)]);
        if (d.amount != null) rows.push([T.total, money(d.amount, d.currency)]);
        if (d.paid) rows.push([T.paid, money(d.paid, d.currency)]);
        msg(form, rows.filter(function (x) { return x[1]; }).map(function (x) { return (x[0] ? '<span class="sta-muted">' + esc(x[0]) + ':</span> ' : '') + '<b>' + esc(x[1]) + '</b>'; }).join('<br>')
          + (d.qr_url ? '<div class="sta-qr"><img src="' + esc(d.qr_url) + '" width="120" height="120" alt="QR"></div>' : '')
          + (d.manage_url ? '<p class="sta-manage"><a class="sta-btn" href="' + esc(d.manage_url) + '" target="_blank" rel="noopener">' + esc(T.manage) + ' →</a></p>' : ''), true);
      }).catch(function () { msg(form, esc(T.error), false); });
    });
  });

  /* ---------- availability calendar + quick booking (pick arrival → departure, like Booking) ---------- */
  function initCal(cal) {
    if (cal.dataset.ready) return; cal.dataset.ready = 1;
    var grid = $('.sta-cal-grid', cal), label = $('.sta-cal-month', cal), room = $('.sta-cal-room', cal), cache = {};
    var pickable = cal.classList.contains('is-pickable'), pickBox = $('[data-sta-cal-pick]', cal), hint = $('[data-sta-cal-hint]', cal);
    var dlg = $('[data-sta-quick-dialog]', cal), qform = dlg && $('form', dlg), cur = cal.dataset.currency || 'EUR';
    var now = new Date(), month = new Date(now.getFullYear(), now.getMonth(), 1), start = null, end = null, hover = null, quote = null;
    function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function parse(k) { var p = k.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function addDays(k, n) { var d = parse(k); d.setDate(d.getDate() + n); return iso(d); }
    function nights(a, b) { return Math.round((parse(b) - parse(a)) / 864e5); }
    function fmt(k) { var d = parse(k); return d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear(); }
    function info(k) {
      var c = cache[k.slice(0, 7) + '-01'], rows = (c && c.rooms) || [], r = rows.filter(function (x) { return String(x.room_id) === room.value; })[0];
      return r && r.days ? r.days[k] : undefined;
    }
    // every night from a to b-1 must be free (unknown months are checked by the price request)
    function rangeFree(a, b) { for (var k = a; k < b; k = addDays(k, 1)) { var i = info(k); if (i && !(i.free > 0)) return false; } return true; }
    function draw() {
      var data = cache[iso(month)], rows = (data && data.rooms) || [], r = rows.filter(function (x) { return String(x.room_id) === room.value; })[0];
      var days = (r && r.days) || {}, first = (month.getDay() + 6) % 7, last = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate(), today = iso(now);
      var to = end || (start && hover && hover > start ? hover : null);
      var html = (T.weekdays || []).map(function (w) { return '<div class="wd">' + esc(w) + '</div>'; }).join('');
      for (var k = 0; k < first; k++) html += '<div></div>';
      for (var d = 1; d <= last; d++) {
        var key = iso(new Date(month.getFullYear(), month.getMonth(), d)), i = days[key], cls = 'd';
        if (key < today) cls += ' is-past'; else if (i) cls += i.free > 0 ? ' is-free' : ' is-booked';
        if (start && key === start) cls += ' is-start';
        if (to && key === to) cls += ' is-end';
        if (start && to && key > start && key < to) cls += ' is-range';
        html += '<div class="' + cls + '" data-day="' + key + '" title="' + (i ? (i.free > 0 ? esc(T.free) + ' ' + i.free + (i.price ? ' · ' + money(i.price, cur) : '') : esc(T.booked)) : '') + '">' + d
          + (pickable && i && i.price && i.free > 0 && key >= today ? '<small>' + money(i.price, cur) + '</small>' : '') + '</div>';
      }
      grid.innerHTML = html;
    }
    function load() {
      label.textContent = (T.months || [])[month.getMonth()] + ' ' + month.getFullYear();
      var from = iso(month), to = iso(new Date(month.getFullYear(), month.getMonth() + 1, 0));
      if (cache[from] !== undefined) { draw(); return; }
      grid.style.opacity = .5;
      api('availability?' + new URLSearchParams({ slug: cal.dataset.slug, from: from, to: to })).then(function (r) {
        cache[from] = r.ok ? r.body.data : null; draw();
      }).catch(function () { cache[from] = null; draw(); }).then(function () { grid.style.opacity = 1; });
    }
    function reset() { start = end = hover = quote = null; if (pickBox) { pickBox.hidden = true; pickBox.innerHTML = ''; } if (hint) hint.hidden = false; draw(); }
    function panel(html) { if (!pickBox) return; pickBox.hidden = false; pickBox.innerHTML = html; if (hint) hint.hidden = true; }
    function roomName() { var o = room.selectedOptions[0]; return o ? o.textContent : ''; }
    function priceFor(a, b) {
      quote = null;
      var adults = qform ? +$('[name="adults"]', qform).value || 2 : 2, kids = qform ? +$('[name="children"]', qform).value || 0 : 0;
      panel('<div class="sta-cal-pick-in"><div><b>' + esc(fmt(a)) + ' → ' + esc(fmt(b)) + '</b><small>' + nights(a, b) + ' ' + esc(T.nights) + ' · ' + esc(roomName()) + '</small></div><span class="sta-muted">' + esc(T.checking) + '</span></div>');
      return api('quote?' + new URLSearchParams({ slug: cal.dataset.slug, from: a, to: b, adults: adults, children: kids })).then(function (r) {
        var offers = (r.ok && r.body && r.body.data) || [], o = offers.filter(function (x) { return String(x.room_id) === room.value; })[0];
        if (!r.ok) { panel('<div class="sta-cal-pick-in is-bad">' + errors(r.body) + '<button type="button" class="sta-btn sta-btn-ghost" data-cal-reset>' + esc(T.change) + '</button></div>'); return; }
        if (!o) { panel('<div class="sta-cal-pick-in is-bad"><span>' + esc(T.notFree) + '</span><button type="button" class="sta-btn sta-btn-ghost" data-cal-reset>' + esc(T.change) + '</button></div>'); return; }
        quote = o;
        panel('<div class="sta-cal-pick-in"><div><b>' + esc(fmt(a)) + ' → ' + esc(fmt(b)) + '</b><small>' + o.nights + ' ' + esc(T.nights) + ' · ' + esc(o.room) + (o.board ? ' · ' + esc(o.board) : '') + '</small></div>'
          + '<div class="sta-cal-price"><small>' + esc(T.total) + '</small><b>' + money(o.total, o.currency || cur) + '</b></div>'
          + '<div class="sta-cal-acts"><button type="button" class="sta-btn sta-btn-ghost" data-cal-reset>' + esc(T.change) + '</button>'
          + (qform ? '<button type="button" class="sta-btn" data-cal-book>' + esc(T.quickBook) + '</button>' : '') + '</div></div>');
      }).catch(function () { panel('<div class="sta-cal-pick-in is-bad">' + esc(T.error) + '</div>'); });
    }
    function fillSum() {
      var sum = $('[data-sta-quick-sum]', qform);
      sum.classList.toggle('is-selected', !!quote);
      if (!quote) { sum.dataset.total = ''; sum.innerHTML = '<span class="sta-muted">' + esc(T.notFree) + '</span>'; qform.dispatchEvent(new Event('change')); return; }
      sum.dataset.total = quote.total;
      sum.innerHTML = '<span><b style="color:inherit;font-size:1rem">' + esc(quote.room) + '</b><br><small class="sta-muted">' + esc(fmt(start)) + ' → ' + esc(fmt(end)) + ' · ' + quote.nights + ' ' + esc(T.nights) + (quote.board ? ' · ' + esc(quote.board) : '') + '</small></span><b>' + money(quote.total, quote.currency || cur) + '</b>';
      qform.dispatchEvent(new Event('change'));
    }
    function openDialog() {
      if (!qform || !quote) return;
      $('[name="date_from"]', qform).value = start; $('[name="date_to"]', qform).value = end; $('[name="room_id"]', qform).value = room.value;
      fillSum();
      $('[data-sta-msg]', qform).className = 'sta-msg';
      dlg.showModal ? dlg.showModal() : dlg.setAttribute('open', '');
    }
    grid.addEventListener('click', function (e) {
      var c = e.target.closest('.d[data-day]'); if (!pickable || !c) return;
      var k = c.dataset.day, today = iso(now), i = info(k);
      if (k < today) return;
      if (!start || end || k <= start) {
        if (i && !(i.free > 0)) return;           // can't arrive on a booked night
        start = k; end = null; quote = null; if (pickBox) pickBox.hidden = true; if (hint) { hint.hidden = false; hint.textContent = T.pickDeparture; }
      } else if (!rangeFree(start, k)) {
        start = (i && i.free > 0) ? k : null; end = null; if (hint) { hint.hidden = false; hint.textContent = T.notFree; }
      } else { end = k; priceFor(start, end); }
      draw();
    });
    grid.addEventListener('mouseover', function (e) { var c = e.target.closest('.d[data-day]'); if (start && !end && c && c.dataset.day !== hover) { hover = c.dataset.day; draw(); } });
    cal.addEventListener('click', function (e) {
      if (e.target.closest('[data-cal-reset]')) { if (hint) hint.textContent = T.pickArrival; reset(); }
      if (e.target.closest('[data-cal-book]')) openDialog();
    });
    if (dlg) dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close ? dlg.close() : dlg.removeAttribute('open'); });
    // after a booking from this page: fresh availability
    document.addEventListener('sta:booked', function () { cache = {}; reset(); load(); });
    $$('.sta-cal-nav button', cal).forEach(function (b) { b.addEventListener('click', function () { month = new Date(month.getFullYear(), month.getMonth() + (+b.dataset.dir), 1); load(); }); });
    room.addEventListener('change', function () { reset(); });
    // more people → new price (and the room may not fit)
    if (qform) ['adults', 'children'].forEach(function (n) { $('[name="' + n + '"]', qform).addEventListener('change', function () { if (start && end) priceFor(start, end).then(function () { fillSum(); }); }); });
    load();
  }
  $$('[data-sta-calendar]:not([data-lazy])').forEach(initCal);

  /* ---------- room details: photos, description, amenities, prices, availability ---------- */
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-sta-room-open]');
    if (o) {
      var dl = $('[data-sta-room-dlg="' + o.dataset.staRoomOpen + '"]');
      if (!dl) return;
      e.preventDefault();
      dl.showModal ? dl.showModal() : dl.setAttribute('open', '');
      var c = $('[data-sta-calendar][data-lazy]', dl); if (c) initCal(c);
      return;
    }
    var d = e.target.closest('[data-sta-room-dlg]');
    if (!d) return;
    if (e.target === d || e.target.closest('[data-close]')) { d.close ? d.close() : d.removeAttribute('open'); return; }
    var st = e.target.closest('[data-sta-rg-step],[data-sta-rg-i]');
    if (st) {
      var thumbs = $$('[data-sta-rg-i]', d), n = thumbs.length; if (!n) return;
      var cur = thumbs.findIndex(function (t) { return t.classList.contains('is-on'); });
      var k = st.dataset.staRgI !== undefined ? +st.dataset.staRgI : (cur + (+st.dataset.staRgStep) + n) % n;
      thumbs.forEach(function (t, x) { t.classList.toggle('is-on', x === k); });
      $('[data-sta-rg-img]', d).src = $('img', thumbs[k]).src;
      var lab = $('[data-sta-rg-n]', d); if (lab) lab.textContent = (k + 1) + ' / ' + n;
      thumbs[k].scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }
  });
  document.addEventListener('keydown', function (e) {
    var d = $('[data-sta-room-dlg][open]'); if (!d || (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight')) return;
    var b = $('[data-sta-rg-step="' + (e.key === 'ArrowLeft' ? -1 : 1) + '"]', d); if (b) b.click();
  });

  /* ---------- fixed terms table (15–22.05 …): click a price → quick booking ---------- */
  $$('[data-sta-terms]').forEach(function (box) {
    var dlg = $('[data-sta-quick-dialog]', box), qform = dlg && $('form', dlg), cur = box.dataset.currency || 'EUR', sel = null;
    function fmt(k) { var p = k.split('-'); return +p[2] + '.' + +p[1] + '.' + p[0]; }
    function quote() {
      if (!sel || !qform) return;
      var sum = $('[data-sta-quick-sum]', qform);
      var q = new URLSearchParams({ slug: box.dataset.slug, from: sel.from, to: sel.to, adults: +$('[name="adults"]', qform).value || 1, children: +$('[name="children"]', qform).value || 0 });
      sum.innerHTML = '<span class="sta-muted">' + esc(T.checking) + '</span>';
      api('quote?' + q).then(function (r) {
        var o = ((r.ok && r.body && r.body.data) || []).filter(function (x) { return String(x.room_id) === sel.room; })[0];
        sum.classList.toggle('is-selected', !!o); sum.dataset.total = o ? o.total : '';
        sum.innerHTML = o
          ? '<span><b style="color:inherit;font-size:1rem">' + esc(o.room) + '</b><br><small class="sta-muted">' + esc(fmt(sel.from)) + ' → ' + esc(fmt(sel.to)) + ' · ' + o.nights + ' ' + esc(T.nights) + (o.board ? ' · ' + esc(o.board) : '') + '</small></span><b>' + money(o.total, o.currency || cur) + '</b>'
          : '<span class="sta-muted">' + esc(T.termFull) + '</span>';
        qform.dispatchEvent(new Event('change'));
      });
    }
    box.addEventListener('click', function (e) {
      var c = e.target.closest('button.sta-term-cell'); if (!c || !qform) return;
      var tr = c.closest('tr');
      sel = { term: tr.dataset.term, from: tr.dataset.from, to: tr.dataset.to, room: c.dataset.room };
      $('[name="date_from"]', qform).value = sel.from; $('[name="date_to"]', qform).value = sel.to;
      $('[name="room_id"]', qform).value = sel.room; $('[name="term_id"]', qform).value = sel.term;
      $('[data-sta-msg]', qform).className = 'sta-msg';
      quote();
      dlg.showModal ? dlg.showModal() : dlg.setAttribute('open', '');
    });
    if (dlg) dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close ? dlg.close() : dlg.removeAttribute('open'); });
    if (qform) ['adults', 'children'].forEach(function (n) { $('[name="' + n + '"]', qform).addEventListener('change', quote); });
    // live prices and free units (the page may be cached)
    function refresh() {
      api('terms?' + new URLSearchParams({ slug: box.dataset.slug })).then(function (r) {
        var d = r.ok && r.body && r.body.data; if (!d || !d.terms) return;
        d.terms.forEach(function (t) {
          var tr = $('tr[data-term="' + t.id + '"]', box); if (!tr) return;
          $$('button.sta-term-cell', tr).forEach(function (b) {
            var c = t.rooms[b.dataset.room]; if (!c) return;
            var full = c.free !== null && c.free < 1, small = $('small', b);
            if (small) small.textContent = full ? T.booked : (c.free !== null ? c.free + ' ' + T.freeUnits : T.free);
            var bb = $('b', b); if (bb) bb.textContent = money(c.price, d.currency || cur);
            b.disabled = full; b.closest('td').classList.toggle('is-full', full);
          });
        });
      }).catch(function () {});
    }
    document.addEventListener('sta:booked', refresh);
    refresh();
  });

  /* ---------- phone: price + "Book" bar at the bottom while the booking form is out of sight ---------- */
  (function () {
    var bar = $('[data-sta-sticky-cta]'), target = $('#sta-booking') || $('form[data-sta-booking]:not([data-quick])');
    if (!bar || !target || !('IntersectionObserver' in window)) return;
    document.body.appendChild(bar);
    var formSeen = false, priceSeen = true, price = $('.sta-pricebox');
    function upd() { bar.hidden = !(window.innerWidth < 900 && !formSeen && !priceSeen); document.body.classList.toggle('sta-has-cta', !bar.hidden); }
    new IntersectionObserver(function (e) { formSeen = e[0].isIntersecting; upd(); }).observe(target);
    if (price) new IntersectionObserver(function (e) { priceSeen = e[0].isIntersecting; upd(); }).observe(price); else priceSeen = false;
    window.addEventListener('resize', upd);
  })();

  /* ---------- testimonials carousel ---------- */
  $$('[data-sta-tm]').forEach(function (root) {
    var track = $('.sta-tm-track', root), orig = $$('.sta-tm-card', track), n = orig.length;
    if (!n) return;
    var vp = $('.sta-tm-viewport', root), dots = $$('.sta-tm-dot', root), per = 3, i = 0, timer = null, busy = false;
    // clones on both sides make the loop endless
    var before = orig.map(function (c) { var x = c.cloneNode(true); x.setAttribute('aria-hidden', 'true'); return x; });
    var after = orig.map(function (c) { var x = c.cloneNode(true); x.setAttribute('aria-hidden', 'true'); return x; });
    track.innerHTML = ''; before.concat(orig, after).forEach(function (c) { track.appendChild(c); });
    var cards = $$('.sta-tm-card', track);
    function perView() { var w = root.clientWidth, want = +root.dataset.per || 3; return Math.max(1, Math.min(want, n, w < 680 ? 1 : w < 1024 ? 2 : want)); }
    function place(jump) {
      per = perView(); root.style.setProperty('--tm-per', per);
      var pos = n + i, vw = vp.clientWidth, w = vw / per;
      // centre the active card
      var offset = vw / 2 - w * (pos + .5);
      track.classList.toggle('is-jump', !!jump);
      track.style.transform = 'translate3d(' + offset + 'px,0,0)';
      cards.forEach(function (c, k) { c.classList.toggle('is-active', k === pos); });
      dots.forEach(function (d, k) { d.classList.toggle('is-on', k === ((i % n) + n) % n); });
      if (jump) { void track.offsetWidth; track.classList.remove('is-jump'); }
    }
    function go(to) {
      if (busy) return; i = to; place(false);
      if (i < 0 || i >= n) { busy = true; setTimeout(function () { i = ((i % n) + n) % n; place(true); busy = false; }, 620); }
    }
    function auto() { var sec = +root.dataset.autoplay; clearInterval(timer); if (sec > 0 && n > 1) timer = setInterval(function () { go(i + 1); }, sec * 1000); }
    $$('.sta-tm-arrow', root).forEach(function (b) { b.addEventListener('click', function () { go(i + (+b.dataset.dir)); auto(); }); });
    dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); auto(); }); });
    cards.forEach(function (c, k) { c.addEventListener('click', function () { var to = k - n; if (to !== i) { go(to); auto(); } }); });
    root.addEventListener('mouseenter', function () { clearInterval(timer); });
    root.addEventListener('mouseleave', auto);
    var x0 = null;
    root.addEventListener('pointerdown', function (e) { x0 = e.clientX; });
    root.addEventListener('pointerup', function (e) { if (x0 === null) return; var dx = e.clientX - x0; x0 = null; if (Math.abs(dx) > 40) { go(i + (dx < 0 ? 1 : -1)); auto(); } });
    window.addEventListener('resize', function () { place(true); });
    place(true); auto();
  });

  /* ---------- reviews ---------- */
  $$('[data-sta-review]').forEach(function (form) {
    var opener = form.parentNode && $('[data-sta-review-open]', form.parentNode);
    opener && opener.addEventListener('click', function () { form.classList.remove('is-collapsed'); form.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!$('[name="rating"]:checked', form)) { msg(form, esc(T.chooseRating), false); return; }
      if (!validate(form)) return;
      var data = {}; new FormData(form).forEach(function (v, k) { data[k] = v; });
      data.rating = +data.rating; data.sta_ts = started;
      var btn = $('[type=submit]', form), label = btn.textContent; btn.disabled = true; btn.textContent = T.sending;
      api('review', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(function (r) {
        if (r.ok) { form.reset(); $$('.sta-field, .sta-row, .sta-rate', form).forEach(function (el) { el.style.display = 'none'; }); btn.style.display = 'none'; msg(form, esc(T.reviewThanks), true); }
        else msg(form, errors(r.body), false);
      }).catch(function () { msg(form, esc(T.error), false); }).then(function () { btn.disabled = false; btn.textContent = label; });
    });
  });
  $$('[data-sta-reviews-more]').forEach(function (b) {
    b.addEventListener('click', function () {
      var list = b.parentNode.previousElementSibling, step = +(list && list.dataset.step) || 6;
      $$('.sta-review.is-hidden', list).slice(0, step).forEach(function (r) { r.classList.remove('is-hidden'); });
      if (!$('.sta-review.is-hidden', list)) b.remove();
    });
  });

  /* ---------- dialogs (visa info cards) ---------- */
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-sta-dialog]');
    if (o) { var d = document.getElementById(o.dataset.staDialog); if (d && d.showModal) { d.showModal(); document.documentElement.classList.add('sta-noscroll'); } return; }
    var c = e.target.closest('[data-sta-dialog-close]');
    if (c) { c.closest('dialog').close(); return; }
    if (e.target.tagName === 'DIALOG' && e.target.classList.contains('sta-dialog')) e.target.close();   // click on the backdrop
  });
  $$('dialog.sta-dialog').forEach(function (d) { d.addEventListener('close', function () { document.documentElement.classList.remove('sta-noscroll'); }); });

  /* ---------- hotel finder: filters on the left, free places for the dates ---------- */
  $$('[data-sta-finder]').forEach(function (box) {
    var form = $('[data-sta-finder-form]', box), timer;
    function go() { clearTimeout(timer); timer = setTimeout(function () {
      var data = new FormData(form), u = new URL(form.action, location.href);
      data.forEach(function (v, k) { if (String(v) !== '' && !(k === 'f_sort' && v === 'recommended') && !(k === 'f_beach' && v === '0') && !(k === 'f_ad' && v === '2' && !form.f_from.value) && !(k === 'f_ch' && v === '0')) u.searchParams.append(k, v); });
      var pr = $('[data-sta-price]', form); if (pr && !pr.dataset.dirty && !new URLSearchParams(location.search).get('f_price')) u.searchParams.delete('f_price');
      box.classList.add('is-loading'); location.href = u.toString();
    }, 250); }
    form.addEventListener('change', function (e) { if (e.target.name === 'f_dest' && form.f_place) form.f_place.value = ''; if (['f_from', 'f_to', 'f_ad', 'f_ch', 'f_q'].indexOf(e.target.name) < 0) go(); });
    form.addEventListener('submit', function (e) { e.preventDefault(); go(); });
    if (form.f_q) form.f_q.addEventListener('search', function () { if (!form.f_q.value && new URLSearchParams(location.search).get('f_q')) go(); });
    var pr = $('[data-sta-price]', form), out = $('[data-sta-price-out]', form);
    if (pr && out) pr.addEventListener('input', function () { pr.dataset.dirty = 1; out.textContent = pr.value; });
    var sort = $('[data-sta-finder-sort]', box);
    if (sort) sort.addEventListener('change', function () { form.f_sort.value = sort.value; go(); });
    var tg = $('[data-sta-finder-toggle]', box);
    if (tg) tg.addEventListener('click', function () { box.classList.toggle('is-open'); });
    if (form.f_from) form.f_from.addEventListener('change', function () { if (form.f_to && (!form.f_to.value || form.f_to.value <= form.f_from.value)) { var d = new Date(form.f_from.value); d.setDate(d.getDate() + 7); form.f_to.value = d.toISOString().slice(0, 10); } if (form.f_to) form.f_to.min = form.f_from.value; });
    // endless list: the next page is added when the end of the list comes into view
    var more = $('[data-sta-more]', box);
    if (more && 'IntersectionObserver' in window) {
      var list = $('.sta-l-items', box), loading = false;
      more.style.display = 'none';   // the pages stay for search engines
      var spin = document.createElement('div');
      spin.className = 'sta-more-loader';
      spin.innerHTML = '<span class="sta-spin"></span>';
      more.parentNode.insertBefore(spin, more);
      var next = more.dataset.next;
      if (!next) spin.remove();
      var io = new IntersectionObserver(function (entries) {
        if (!entries[0].isIntersecting || loading || !next) return;
        loading = true;
        fetch(next, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          // the same list on the next page (the n-th finder of the page)
          var all = $$('[data-sta-finder]'), idx = all.indexOf(box), other = doc.querySelectorAll('[data-sta-finder]')[idx];
          if (!other) { next = null; spin.remove(); return; }
          Array.prototype.forEach.call(other.querySelectorAll('.sta-l-items > *'), function (cell) { list.appendChild(document.importNode(cell, true)); });
          var nm = other.querySelector('[data-sta-more]');
          next = nm && nm.dataset.next ? nm.dataset.next : null;
          if (!next) { spin.remove(); io.disconnect(); }
          loading = false;
        }).catch(function () { loading = false; more.style.display = ''; spin.remove(); io.disconnect(); });
      }, { rootMargin: '600px 0px' });
      io.observe(spin);
    }
    if (!box.dataset.from) return;
    var q = new URLSearchParams({ from: box.dataset.from, to: box.dataset.to, adults: box.dataset.ad, children: box.dataset.ch }), note = $('[data-sta-finder-note]', box);
    api('available?' + q).then(function (res) {
      var list = (res.body && res.body.data) || [];
      if (!res.ok) { if (note) note.textContent = T.error; return; }
      var free = {}; list.forEach(function (x) { free[x.slug] = x; });
      // nothing is hidden: the places with a term for the dates go first, the others stay below marked as full
      var cells = $$('.sta-finder-cell', box), n = 0, items = cells.length ? cells[0].parentNode : null, busy = [];
      cells.forEach(function (cell) {
        var f = free[cell.dataset.slug], av = $('.sta-finder-avail', cell);
        cell.hidden = false; av.hidden = false;
        cell.classList.toggle('is-free', !!f); cell.classList.toggle('is-busy', !f);
        if (!f) { av.className = 'sta-finder-avail is-busy'; av.textContent = T.noTerm; busy.push(cell); return; }
        n++;
        av.className = 'sta-finder-avail';
        av.innerHTML = '<b>✓ ' + esc(T.hasTerm) + '</b><br>' + f.nights + ' ' + esc(T.nights) + ': <strong>' + esc(money(f.total, f.currency)) + '</strong>';
        items.appendChild(cell);
        $$('a[href]', cell).forEach(function (a) { try { var u = new URL(a.href); if (u.origin === location.origin) { u.searchParams.set('from', box.dataset.from); u.searchParams.set('to', box.dataset.to); u.searchParams.set('adults', box.dataset.ad); a.href = u.toString(); } } catch (e) {} });
      });
      busy.forEach(function (cell) { items.appendChild(cell); });
      var c = $('[data-sta-finder-count]', box); if (c) c.textContent = String(T.freeOf || '%1$d / %2$d').replace('%1$d', n).replace('%2$d', cells.length);
      if (note) { note.textContent = n ? T.freeFirst : T.noOffers; note.classList.toggle('is-empty', !n); }
    });
  });

  /* ---------- search box with tabs (hotels · apartments · tours · flights) ---------- */
  $$('[data-sta-sbox]').forEach(function (box) {
    var MONTHS = Array.isArray(T.months) ? T.months : 'Јануари,Февруари,Март,Април,Мај,Јуни,Јули,Август,Септември,Октомври,Ноември,Декември'.split(','), DAYS = (T.sbDays || 'Пон,Вто,Сре,Чет,Пет,Саб,Нед').split(',');
    var URLQ = new URLSearchParams(location.search), prefill = box.classList.contains('sta-sbox--compact');
    function closeAll(except) { $$('.sta-sb-pop', box).forEach(function (p) { if (p !== except) { p.hidden = true; var f = p.closest('.sta-sb-f'); if (f) f.classList.remove('is-open'); } }); }
    function open(pop) { var was = !pop.hidden; closeAll(); if (!was) { pop.hidden = false; pop.closest('.sta-sb-f').classList.add('is-open'); } }
    document.addEventListener('click', function (e) { if (e.target.isConnected && !box.contains(e.target)) closeAll(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });

    // tabs
    $$('[data-tab]', box).forEach(function (b) {
      b.addEventListener('click', function () {
        $$('[data-tab]', box).forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
        $$('[data-panel]', box).forEach(function (p) { p.hidden = p.dataset.panel !== b.dataset.tab; });
        closeAll();
      });
    });

    $$('[data-sb-form]', box).forEach(function (form) {
      $$('[data-sb-open]', form).forEach(function (b) { b.addEventListener('click', function () { open($('[data-sb-pop]', b.closest('.sta-sb-f'))); }); });
      $$('[data-sb-done]', form).forEach(function (b) { b.addEventListener('click', function () { closeAll(); }); });
      function nextField(after) {
        var fields = $$('.sta-sb-f', form), i = fields.indexOf(after), nx = fields[i + 1];
        if (!nx) return;
        var q2 = $('[data-sb-q]', nx); if (q2 && !$('[data-sb-h]', nx).value) { q2.focus(); return; }
        var o = $('[data-sb-open]', nx); if (o && nx.matches('[data-sb-dates]')) o.click();
      }

      /* destination / airports: suggestions while typing */
      $$('[data-sb-dest]', form).forEach(function (dest) {
        var q = $('[data-sb-q]', dest), sugg = $('[data-sb-sugg]', dest), items = [], remote = [], timer;
        try { items = JSON.parse($('[data-sb-items]', dest).textContent) || []; } catch (e) {}
        var hidden = $$('[data-sb-h]', dest);
        function norm(v) { return String(v || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
        // Cyrillic typed text -> Latin (Атина -> atina, Лондон -> london)
        function lat(v) {
          var m = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', ѓ: 'gj', е: 'e', ж: 'zh', з: 'z', ѕ: 'dz', и: 'i', ј: 'j', к: 'k', л: 'l', љ: 'lj', м: 'm', н: 'n', њ: 'nj', о: 'o', п: 'p', р: 'r', с: 's', т: 't', ќ: 'kj', у: 'u', ф: 'f', х: 'h', ц: 'c', ч: 'ch', џ: 'dz', ш: 'sh', й: 'j', ы: 'y', э: 'e', ю: 'ju', я: 'ja', ь: '', ъ: '', щ: 'sht' };
          return String(v || '').toLowerCase().replace(/[а-яѓѕјљњќџ]/g, function (c) { return m[c] != null ? m[c] : c; });
        }
        function clearPick() { hidden.forEach(function (h) { h.value = ''; }); if (q.dataset.name) q.name = q.dataset.name; }
        q.dataset.name = q.getAttribute('name') || '';
        // flights "To": the whole world, loaded on the first click (assets/data/airports.json)
        var world = null, worldUrl = dest.dataset.sbWorld, loading = false;
        function loadWorld() {
          if (!worldUrl || world || loading) return;
          loading = true;
          fetch(worldUrl, { credentials: 'omit' }).then(function (r) { return r.json(); }).then(function (rows) {
            var f = items[0] ? items[0].f : 'to';
            world = rows.map(function (a) { return { k: 'air', v: a[0], l: a[1] + ' (' + a[0] + ')', s: [a[2], a[3]].filter(Boolean).join(', '), x: norm(a[5] || ''), r: a[4] || 0, f: f }; });
            if (document.activeElement === q && q.value.trim()) render();
          }).catch(function () { loading = false; });
        }
        function all() { return (q.value.trim() && world ? world : items).concat(remote); }
        function render() {
          var raw = dest.hasAttribute('data-sb-strict') ? '' : q.value.trim(), v = norm(raw), vl = norm(lat(raw)), html = '', list = all(), groups = { dest: [], place: [], group: [], hotel: [], item: [], air: [] };
          list.forEach(function (it) {
            if (it.k === 'hotel' || !v) { groups[it.k].push(it); return; }
            var l = norm(it.l), s2 = norm(it.s), x = it.x || '';
            if (l.indexOf(v) > -1 || s2.indexOf(v) > -1 || x.indexOf(v) > -1 || norm(it.v) === v || (vl !== v && (l.indexOf(vl) > -1 || s2.indexOf(vl) > -1 || x.indexOf(vl) > -1))) groups[it.k].push(it);
          });
          // airports: the exact code, then the city that starts with the text, then the big airports
          function score(it) { var l = norm(it.l); return (norm(it.v) === v ? 100 : 0) + (l.indexOf(v) === 0 || l.indexOf(vl) === 0 || (it.x || '').indexOf(v) === 0 ? 10 : 0) + (it.r || 0); }
          if (v) groups.air.sort(function (x, y) { return score(y) - score(x); });
          var titles = { dest: T.sbDest, place: T.sbPlace, group: T.sbGroup, hotel: T.sbHotels, item: T.sbItem, air: T.sbAir }, lim = { dest: 8, place: 8, group: 6, hotel: 8, item: v ? 8 : 0, air: 8 }, n = 0;
          ['dest', 'place', 'group', 'hotel', 'item', 'air'].forEach(function (k) {
            var l = groups[k].slice(0, lim[k]); if (!l.length) return;
            html += '<div class="sta-sb-sg-t">' + esc(titles[k]) + '</div>';
            l.forEach(function (it) {
              n++;
              html += '<button type="button" class="sta-sb-sg" data-k="' + k + '" data-i="' + list.indexOf(it) + '">' + '<span class="sta-sb-sg-ic' + (k === 'item' || k === 'hotel' ? ' is-item' : k === 'air' ? ' is-air' : '') + '"></span><span><b>' + esc(it.l) + '</b>' + (it.s ? '<small>' + esc(it.s) + '</small>' : '') + '</span></button>';
            });
          });
          sugg.innerHTML = n ? html : '<div class="sta-sb-sg-none">' + esc(dest.classList.contains('sta-sb-air') ? T.sbAirNone : T.sbNone) + '</div>';
        }
        function ask() {
          // hotels by name from the app (live list)
          if (!dest.dataset.sbRemote) return;
          clearTimeout(timer);
          var v = q.value.trim();
          if (v.length < 3) { remote = []; return; }
          timer = setTimeout(function () {
            api(dest.dataset.sbRemote + '?q=' + encodeURIComponent(v)).then(function (r) {
              remote = ((r.body && r.body.data) || []).map(function (h) { return { k: 'hotel', v: h.id, l: h.name + (h.stars ? ' ' + new Array(h.stars + 1).join('★') : ''), s: h.place, f: 'hotel' }; });
              render();
            });
          }, 250);
        }
        q.addEventListener('focus', function () { loadWorld(); closeAll(sugg); render(); sugg.hidden = false; dest.classList.add('is-open'); if (q.value && hidden[0] && hidden[0].value) q.select(); });
        q.addEventListener('input', function () { clearPick(); render(); ask(); sugg.hidden = false; });
        sugg.addEventListener('click', function (e) {
          var b = e.target.closest('.sta-sb-sg'); if (!b) return;
          var it = all()[+b.dataset.i];
          if (it.u) { location.href = it.u; return; }
          clearPick();
          var h = hidden.filter(function (x) { return x.dataset.sbH === it.f || x.dataset.sbH === it.f + '[]'; })[0];
          if (h) {
            h.value = it.v; q.value = it.l; q.removeAttribute('name');
            if (it.k === 'hotel') { var hn = $('input[name="hn"]', form) || form.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name: 'hn' })); hn.value = it.l; }
          }
          sugg.hidden = true; dest.classList.remove('is-open'); dest.classList.remove('is-invalid');
          nextField(dest);
        });
        q.addEventListener('keydown', function (e) {
          if (e.key !== 'ArrowDown' && e.key !== 'Enter') return;
          var first = $('.sta-sb-sg', sugg);
          if (e.key === 'Enter' && !sugg.hidden && first && q.value.trim()) { e.preventDefault(); first.click(); }
          if (e.key === 'ArrowDown' && first) { e.preventDefault(); first.focus(); }
        });
        // results page: show what was searched
        if (prefill) {
          hidden.forEach(function (h) {
            var v = URLQ.get(h.name); if (!v) return;
            h.value = v; q.removeAttribute('name');
            var it = items.filter(function (x) { return x.v === v; })[0];
            q.value = it ? it.l : (h.name === 'hotel' ? (URLQ.get('hn') || v) : v);
            if (h.name === 'hotel') { var hn = form.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name: 'hn' })); hn.value = URLQ.get('hn') || ''; }
          });
        }
      });

      /* dates: range calendar (one date for one-way flights) */
      var dbox = $('[data-sb-dates]', form);
      var fromI = dbox && $('[data-sb-from]', dbox), toI = dbox && $('[data-sb-to]', dbox);
      var oneway = $('[data-sb-oneway]', form);
      function single() { return !!(oneway && oneway.checked); }
      if (dbox) {
        var val = $('[data-sb-dates-val]', dbox), monthsEl = $('[data-sb-months]', dbox);
        var today = new Date(); today.setHours(0, 0, 0, 0);
        var view = new Date(today.getFullYear(), today.getMonth(), 1), a = null, b2 = null;
        function iso(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
        function show(d) { return ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear(); }
        function parse(v) { var p = String(v || '').split('-'); return p.length === 3 ? new Date(+p[0], +p[1] - 1, +p[2]) : null; }
        function month(m) {
          var y = m.getFullYear(), mo = m.getMonth(), first = (new Date(y, mo, 1).getDay() + 6) % 7, len = new Date(y, mo + 1, 0).getDate();
          var h = '<div class="sta-sb-month"><div class="sta-sb-mt">' + MONTHS[mo] + ' ' + y + '</div><div class="sta-sb-grid">';
          DAYS.forEach(function (d) { h += '<span class="sta-sb-wd">' + esc(d) + '</span>'; });
          for (var i = 0; i < first; i++) h += '<span></span>';
          for (var d = 1; d <= len; d++) {
            var dt = new Date(y, mo, d), t = dt.getTime(), c = '';
            if (dt < today) c = ' is-past';
            if (a && t === a.getTime()) c += ' is-a';
            if (b2 && t === b2.getTime()) c += ' is-b';
            if (a && b2 && t > a.getTime() && t < b2.getTime()) c += ' is-in';
            h += '<button type="button" class="sta-sb-d' + c + '" data-d="' + iso(dt) + '"' + (dt < today ? ' disabled' : '') + '>' + d + '</button>';
          }
          return h + '</div></div>';
        }
        function draw() {
          var two = window.matchMedia('(min-width: 720px)').matches;
          monthsEl.innerHTML = month(view) + (two ? month(new Date(view.getFullYear(), view.getMonth() + 1, 1)) : '');
          val.textContent = a ? show(a) + (single() ? '' : ' - ' + (b2 ? show(b2) : '…')) : (single() ? T.sbDate : T.sbDates);
          dbox.classList.toggle('has-value', !!a);
          fromI.value = a ? iso(a) : ''; toI.value = b2 && !single() ? iso(b2) : '';
        }
        monthsEl.addEventListener('click', function (e) {
          var d = e.target.closest('[data-d]'); if (!d) return;
          var dt = parse(d.dataset.d);
          if (single() || !a || b2 || dt <= a) { a = dt; b2 = null; } else { b2 = dt; }
          draw(); dbox.classList.remove('is-invalid');
          if (a && (b2 || single())) setTimeout(function () { closeAll(); var g = $('[data-sb-guests] [data-sb-open]', form); if (g) g.click(); }, 150);
        });
        $$('[data-sb-m]', dbox).forEach(function (bt) { bt.addEventListener('click', function () { var nv = new Date(view.getFullYear(), view.getMonth() + (+bt.dataset.sbM), 1); if (nv >= new Date(today.getFullYear(), today.getMonth(), 1)) { view = nv; draw(); } }); });
        $('[data-sb-clear]', dbox).addEventListener('click', function () { a = b2 = null; draw(); });
        $$('[data-sb-trip]', form).forEach(function (r) { r.addEventListener('change', function () { if (single()) b2 = null; draw(); }); });
        if (prefill) {
          a = parse(URLQ.get(fromI.name)); b2 = parse(URLQ.get(toI.name));
          if (a && a < today) { a = b2 = null; }
          if (a) view = new Date(a.getFullYear(), a.getMonth(), 1);
        }
        draw();
      }

      /* guests: adults / children (hotels: rooms + ages; flights: passengers + class) */
      var gbox = $('[data-sb-guests]', form);
      function role(r) { return gbox ? $('[data-sb-role="' + r + '"]', gbox) : null; }
      function ages() {
        var wrap = gbox && $('[data-sb-ages]', gbox); if (!wrap) return;
        var ch = +role('ch').value, list = $('[data-sb-ages-list]', wrap), tpl = $('[data-sb-age-tpl]', wrap), have = $$('select', list);
        for (var i = have.length; i < ch; i++) {
          var node = tpl.content.firstElementChild.cloneNode(true);
          $('span', node).textContent = (T.childAge || 'Child') + ' ' + (i + 1); $('select', node).value = '6';
          $('select', node).addEventListener('change', agesValue);
          list.appendChild(node);
        }
        have.slice(ch).forEach(function (sel) { sel.parentNode.remove(); });
        wrap.hidden = ch === 0; agesValue();
      }
      function agesValue() { var v = $('[data-sb-ages-value]', gbox); if (v) v.value = $$('[data-sb-ages-list] select', gbox).map(function (s) { return s.value; }).join(','); }
      function guests() {
        if (!gbox) return;
        var ad = +role('ad').value, ch = role('ch') ? +role('ch').value : 0, out;
        if (role('rooms')) {
          var rm = +role('rooms').value;
          out = rm + ' ' + (rm === 1 ? T.room1 : T.roomN) + ' · ' + (ad * rm) + ' ' + (ad * rm === 1 ? T.adult1 : T.adultN) + (ch ? ' · ' + (ch * rm) + ' ' + (ch * rm === 1 ? T.child1 : T.childN) : '');
        } else if (role('inf')) {
          var pax = ad + ch + +role('inf').value, bus = $('[data-sb-business]', gbox);
          out = pax + ' ' + (pax === 1 ? T.passenger1 : T.passengerN) + ' · ' + (bus && bus.checked ? 'Business' : 'Economy');
          var cls = $('[data-sb-class]', gbox); if (cls) cls.value = bus && bus.checked ? 'BUS' : 'ECO';
        } else {
          out = ad + ' ' + (ad === 1 ? T.adult1 : T.adultN) + ' · ' + ch + ' ' + (ch === 1 ? T.child1 : T.childN);
        }
        $('[data-sb-guests-val]', gbox).textContent = out;
      }
      $$('[data-sb-step]', form).forEach(function (bt) {
        bt.addEventListener('click', function () {
          var inp = $('input', bt.parentNode), v = Math.max(+inp.min, Math.min(+inp.max, +inp.value + (+bt.dataset.sbStep)));
          // flights: max 9 passengers, an infant needs an adult
          if (role('inf') && +bt.dataset.sbStep > 0 && +role('ad').value + +role('ch').value + +role('inf').value >= 9) return;
          if (inp === role('inf') && v > +role('ad').value) return;
          inp.value = v;
          if (role('inf') && +role('inf').value > +role('ad').value) role('inf').value = role('ad').value;
          ages(); guests();
        });
      });
      var busBox = gbox && $('[data-sb-business]', gbox); if (busBox) busBox.addEventListener('change', guests);
      if (prefill && gbox) {
        ['room_count', 'adults'].forEach(function (n) { var el = $('[name="' + n + '"]', gbox); if (el && URLQ.get(n)) el.value = Math.max(+el.min, Math.min(+el.max, +URLQ.get(n))); });
        var ag = (URLQ.get('ages') || '').split(',').filter(function (x) { return x !== ''; });
        if (role('ch') && ag.length) { var per = Math.min(4, ag.length); role('ch').value = per; ages(); $$('[data-sb-ages-list] select', gbox).forEach(function (sel, i) { if (ag[i] != null) sel.value = ag[i]; }); agesValue(); }
        $$('input[type=checkbox][name]', form).forEach(function (c) { if (URLQ.getAll(c.name).indexOf(c.value) > -1) c.checked = true; });
      }
      ages(); guests();

      /* price: two handles */
      var pr = $('[data-sb-price]', form);
      function price() {
        if (!pr) return;
        var lo = $('[data-sb-lo]', pr), hi = $('[data-sb-hi]', pr), L = +pr.dataset.lo, H = +pr.dataset.hi, sym = pr.dataset.sym;
        if (+lo.value > +hi.value) { var t = lo.value; lo.value = hi.value; hi.value = t; }
        $('[data-sb-price-out]', pr).textContent = sym + lo.value + ' – ' + sym + hi.value;
        var f = $('[data-sb-fill]', pr); f.style.left = ((lo.value - L) / (H - L) * 100) + '%'; f.style.right = (100 - (hi.value - L) / (H - L) * 100) + '%';
        var mn = $('[data-sb-pmin]', pr), mx = $('[data-sb-pmax]', pr);
        mn.value = lo.value; mn.disabled = +lo.value <= L; mx.value = hi.value; mx.disabled = +hi.value >= H;
      }
      if (pr) { $$('input[type=range]', pr).forEach(function (r) { r.addEventListener('input', price); }); price(); }
      var reset = $('[data-sb-reset]', form);
      if (reset) reset.addEventListener('click', function () {
        $$('.sta-sb-adv-pop input[type=checkbox]', form).forEach(function (c) { c.checked = false; });
        if (pr) { $('[data-sb-lo]', pr).value = pr.dataset.lo; $('[data-sb-hi]', pr).value = pr.dataset.hi; price(); }
      });

      /* before sending: what is required, a typed airport code, a clean address */
      form.addEventListener('submit', function (e) {
        var missing = [];
        if (form.hasAttribute('data-sb-hotels')) {
          var d1 = $('[data-sb-dest]', form);
          if (!$('[name="destination"]', form).value && !$('[name="hotel"]', form).value) missing.push(d1);
          if (!fromI.value || !toI.value) missing.push(dbox);
        }
        if (form.hasAttribute('data-sb-flights')) {
          $$('[data-sb-dest]', form).forEach(function (d) {
            var h = $('[data-sb-h]', d), typed = $('[data-sb-q]', d).value.trim();
            if (!h.value && /^[a-z]{3}$/i.test(typed)) h.value = typed.toUpperCase();
            var m = !h.value && typed.match(/\(([A-Z]{3})\)\s*$/); if (m) h.value = m[1];
            // "from": only the departure airports of the agency
            if (h.value && d.hasAttribute('data-sb-strict') && !(function () { try { return JSON.parse($('[data-sb-items]', d).textContent) || []; } catch (e) { return []; } })().some(function (it) { return it.v === h.value; })) h.value = '';
            if (!h.value) missing.push(d);
          });
          if (!fromI.value || (!single() && !toI.value)) missing.push(dbox);
        }
        if (missing.length) {
          e.preventDefault();
          missing.forEach(function (m) { m.classList.add('is-invalid'); });
          var q1 = $('[data-sb-q]', missing[0]); if (q1) q1.focus(); else { var o = $('[data-sb-open]', missing[0]); if (o) o.click(); }
          return;
        }
        agesValue();
        $$('input, select', form).forEach(function (el) {
          if (el.type === 'checkbox' || el.type === 'radio') { if (el.name === 'sb_trip') el.disabled = true; return; }
          if (el.matches('[data-sb-ages-list] select')) { el.disabled = true; return; }
          if (String(el.value).trim() === '' || (el.name === 'f_ad' && el.value === '2' && !(fromI && fromI.value)) || (el.name === 'f_ch' && el.value === '0') || (el.name === 'children' && form.hasAttribute('data-sb-hotels'))) {
            if (!(form.hasAttribute('data-sb-flights') && el.name === 'returnDate')) el.disabled = true;
          }
        });
        setTimeout(function () { $$('input, select', form).forEach(function (el) { if (!el.matches('[data-sb-pmin],[data-sb-pmax]')) el.disabled = false; }); price(); }, 500);
      });
      $$('.sta-sb-f', form).forEach(function (f) { f.addEventListener('click', function () { f.classList.remove('is-invalid'); }); });
    });
  });

  // shared with hotels.js (hotel results, hotel page and booking)
  window.staCore = { api: api, money: money, esc: esc, t: t, $: $, $$: $$, validate: validate, msg: msg, errors: errors, T: T, C: C };
})();
