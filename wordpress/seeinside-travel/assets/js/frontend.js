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
    var slug = q.get('slug'); q.delete('slug'); q.delete('website'); q.delete('sta_ts');
    if (name === 'quote') return [C.direct + 'accommodations/' + encodeURIComponent(slug) + '/quote?' + q, { headers: { Accept: 'application/json' } }];
    if (name === 'terms') return [C.direct + 'accommodations/' + encodeURIComponent(slug) + '/terms', { headers: { Accept: 'application/json' } }];
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
        var r = document.createElement('div'); r.className = 'sta-pax-row';
        r.innerHTML = '<div class="sta-pax-title"></div><div class="sta-row">'
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
      });
      paxBox.hidden = n === 0;
    }
    form.addEventListener('input', function (e) { if (e.target.name === 'adults' || e.target.name === 'children') pax(); });
    form.addEventListener('change', function (e) { if (e.target.name === 'adults' || e.target.name === 'children') pax(); });
    form.addEventListener('sta:pax', pax);
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
      }
      totalBox.hidden = sum === null;
      if (sum !== null) totalBox.innerHTML = '<span>' + esc(T.estimate) + '</span><b>' + money(sum, cur) + '</b>';
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
      var fd = new FormData(form), data = { type: type, slug: form.dataset.slug, website: fd.get('website') || '', sta_ts: started, customer: {}, extras: [], children_ages: [] }, px = [];
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
      var btn = $('[type="submit"]', form), label = btn.textContent; btn.disabled = true; btn.textContent = T.sending;
      api('booking', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(function (r) {
        if (r.ok && r.body && r.body.data) {
          var d = r.body.data;
          msg(form, '<b>' + esc(T.thanks) + '</b><span class="sta-muted">' + esc(T.reference) + ':</span><span class="sta-ref">' + esc(d.reference) + '</span>' + (d.total ? esc(T.total) + ': <b>' + money(d.total, d.currency) + '</b>' : '')
            + (d.qr_url ? '<div class="sta-qr"><img src="' + esc(d.qr_url) + '" width="132" height="132" alt="QR ' + esc(d.reference) + '"><div><b>' + esc(T.qrTitle) + '</b><small>' + esc(T.qrHint) + '</small><a href="' + esc(d.qr_url) + '" download="' + esc(d.reference) + '.png" target="_blank" rel="noopener">' + esc(T.qrSave) + '</a></div></div>' : '')
            + (d.manage_url ? '<p class="sta-manage"><a class="sta-btn" href="' + esc(d.manage_url) + '" target="_blank" rel="noopener">' + esc(T.manage) + ' →</a><small>' + esc(T.manageHint) + '</small></p>' : '')
            + (d.payment && d.payment.url ? '<p><a class="sta-btn" href="' + esc(d.payment.url) + '">Pay</a></p>' : ''), true);
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
      var fd = new FormData(form); fd.append('sta_ts', started);
      var btn = $('[type="submit"]', form), label = btn.textContent; btn.disabled = true; btn.textContent = T.sending;
      api('visa', { method: 'POST', body: fd }).then(function (r) {
        if (r.ok && r.body && r.body.data) {
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
  $$('[data-sta-calendar]').forEach(function (cal) {
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
})();
