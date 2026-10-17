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
  function validate(form) {
    var ok = true;
    $$('[required]', form).forEach(function (el) {
      var bad = el.type === 'checkbox' ? !el.checked : el.type === 'radio' ? !$$('[name="' + el.name + '"]', form).some(function (r) { return r.checked; }) : !String(el.value || '').trim() || (el.validity && !el.validity.valid);
      el.classList.toggle('is-invalid', bad); if (bad) ok = false;
    });
    if (!ok) { var first = $('.is-invalid', form); first && first.focus(); }
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
    if (name === 'visa') { opts.body.append('locale', C.lang); return [C.direct + 'visa-applications', Object.assign({}, opts, { headers: { Accept: 'application/json' } })]; }
    var slug = q.get('slug'); q.delete('slug'); q.delete('website'); q.delete('sta_ts');
    if (name === 'quote') return [C.direct + 'accommodations/' + encodeURIComponent(slug) + '/quote?' + q, { headers: { Accept: 'application/json' } }];
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
      if (!from || !to) { validate(form); return; }
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
      var fd = new FormData(form), data = { type: type, slug: form.dataset.slug, website: fd.get('website') || '', sta_ts: started, customer: {}, extras: [], children_ages: [] };
      fd.forEach(function (v, k) {
        var m = k.match(/^customer\[(\w+)\]$/);
        if (m) data.customer[m[1]] = v;
        else if (k === 'extras[]') data.extras.push(+v);
        else if (k === 'children_ages[]') data.children_ages.push(+v);
        else if (k !== 'website') data[k] = v;
      });
      $$('[name="extras[]"]:checked:disabled', form).forEach(function (x) { if (data.extras.indexOf(+x.value) < 0) data.extras.push(+x.value); });
      ['adults', 'children', 'quantity', 'departure_id', 'departure_place_id', 'ticket_id', 'room_id', 'charter_id'].forEach(function (k) { if (data[k] !== undefined) data[k] = data[k] === '' ? null : +data[k]; });
      if (data.transport_only) data.transport_only = true;
      var btn = $('[type="submit"]', form), label = btn.textContent; btn.disabled = true; btn.textContent = T.sending;
      api('booking', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(function (r) {
        if (r.ok && r.body && r.body.data) {
          var d = r.body.data;
          msg(form, '<b>' + esc(T.thanks) + '</b><span class="sta-muted">' + esc(T.reference) + ':</span><span class="sta-ref">' + esc(d.reference) + '</span>' + (d.total ? esc(T.total) + ': <b>' + money(d.total, d.currency) + '</b>' : '') + (d.payment && d.payment.url ? '<p><a class="sta-btn" href="' + esc(d.payment.url) + '">Pay</a></p>' : ''), true);
          form.reset(); ages(); estimate();
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
    var form = $('[data-sta-booking]'); if (!form) return;
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
        msg(form, rows.filter(function (x) { return x[1]; }).map(function (x) { return (x[0] ? '<span class="sta-muted">' + esc(x[0]) + ':</span> ' : '') + '<b>' + esc(x[1]) + '</b>'; }).join('<br>'), true);
      }).catch(function () { msg(form, esc(T.error), false); });
    });
  });

  /* ---------- availability calendar ---------- */
  $$('[data-sta-calendar]').forEach(function (cal) {
    var grid = $('.sta-cal-grid', cal), label = $('.sta-cal-month', cal), room = $('.sta-cal-room', cal), cache = {};
    var now = new Date(), month = new Date(now.getFullYear(), now.getMonth(), 1);
    function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function draw(data) {
      var rows = (data && data.rooms) || [], r = rows.filter(function (x) { return String(x.room_id) === room.value; })[0];
      var days = (r && r.days) || {}, first = (month.getDay() + 6) % 7, last = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate(), today = iso(now);
      var html = (T.weekdays || []).map(function (w) { return '<div class="wd">' + esc(w) + '</div>'; }).join('');
      for (var k = 0; k < first; k++) html += '<div></div>';
      for (var d = 1; d <= last; d++) {
        var key = iso(new Date(month.getFullYear(), month.getMonth(), d)), info = days[key], cls = 'd';
        if (key < today) cls += ' is-past'; else if (info) cls += info.free > 0 ? ' is-free' : ' is-booked';
        html += '<div class="' + cls + '" title="' + (info ? (info.free > 0 ? esc(T.free) + ' ' + info.free + (info.price ? ' · ' + money(info.price) : '') : esc(T.booked)) : '') + '">' + d + '</div>';
      }
      grid.innerHTML = html;
    }
    function load() {
      label.textContent = (T.months || [])[month.getMonth()] + ' ' + month.getFullYear();
      var from = iso(month), to = iso(new Date(month.getFullYear(), month.getMonth() + 1, 0));
      if (cache[from]) { draw(cache[from]); return; }
      grid.style.opacity = .5;
      api('availability?' + new URLSearchParams({ slug: cal.dataset.slug, from: from, to: to })).then(function (r) {
        cache[from] = r.ok ? r.body.data : null; draw(cache[from]);
      }).catch(function () { draw(null); }).then(function () { grid.style.opacity = 1; });
    }
    $$('.sta-cal-nav button', cal).forEach(function (b) { b.addEventListener('click', function () { month = new Date(month.getFullYear(), month.getMonth() + (+b.dataset.dir), 1); load(); }); });
    room.addEventListener('change', function () { draw(cache[iso(month)]); });
    load();
  });
})();
