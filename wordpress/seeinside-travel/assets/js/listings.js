/* SeeInside Travel – lists: filters, "show more", arc slider, coverflow, carousel (no dependencies) */
(function () {
  'use strict';
  var G = window.STA_LIST || {};
  function $(s, el) { return (el || document).querySelector(s); }
  function $$(s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); }
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- "+5 dates" dropdown on cards ---------- */
  if (!window.__staMoreDates) {
    window.__staMoreDates = true;
    document.addEventListener('click', function (e) {
      var b = e.target.closest('.sta-c-more-btn');
      $$('.sta-c-more.is-open').forEach(function (m) { if (!b || m !== b.parentNode) { m.classList.remove('is-open'); m.querySelector('button').setAttribute('aria-expanded', 'false'); } });
      if (b) { e.preventDefault(); e.stopPropagation(); var m = b.parentNode; m.classList.toggle('is-open'); b.setAttribute('aria-expanded', m.classList.contains('is-open') ? 'true' : 'false'); }
    });
  }

  function fetchList(root, params) {
    var q = new URLSearchParams(Object.assign({ cfg: root.getAttribute('data-cfg'), lang: G.lang || '' }, params));
    root.classList.add('is-loading');
    return fetch((G.rest || '/wp-json/seeinside-travel/v1/listing') + '?' + q, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .finally(function () { root.classList.remove('is-loading'); });
  }

  /* ---------- grids: filters, show more, page numbers ---------- */
  function grid(root) {
    var term = new URLSearchParams(location.search).get('sta_f') || '';
    var items = $('.sta-l-items', root);
    if (!items) return;
    function bindMore() {
      $$('.sta-l-more-btn, .sta-l-page', root).forEach(function (b) {
        b.addEventListener('click', function () {
          var page = +b.getAttribute('data-page'), append = b.classList.contains('sta-l-more-btn');
          fetchList(root, { page: page, term: term }).then(function (d) {
            if (append) items.insertAdjacentHTML('beforeend', d.html); else { items.innerHTML = d.html; root.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); }
            var box = $('.sta-l-more, .sta-l-pages', root); if (box) box.outerHTML = d.more || ''; else if (d.more) items.insertAdjacentHTML('afterend', d.more);
            bindMore();
          });
        });
      });
    }
    $$('.sta-l-f', root).forEach(function (b) {
      b.addEventListener('click', function () {
        term = b.getAttribute('data-term') || '';
        $$('.sta-l-f', root).forEach(function (x) { x.classList.toggle('is-active', x === b); });
        fetchList(root, { page: 1, term: term }).then(function (d) {
          items.innerHTML = d.html;
          var cur = $('.sta-l-current', root); if (cur) cur.textContent = d.title || '';
          var box = $('.sta-l-more, .sta-l-pages', root); if (box) box.remove();
          if (d.more) items.insertAdjacentHTML('afterend', d.more);
          bindMore();
          try { var u = new URL(location.href); if (term) u.searchParams.set('sta_f', term); else u.searchParams.delete('sta_f'); history.replaceState(null, '', u); } catch (e) {}
          if (window.innerWidth < 900) items.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });
    });
    bindMore();
  }

  /* ---------- shared slider engine (arc + coverflow) ---------- */
  function slider(root, opts, place) {
    var track = $('.sta-l-track', root);
    if (!track) return;
    var cells = $$('.sta-l-cell', track), real = cells.length;
    if (!real) return;
    // few cards: repeat them so the arc / the stack is always full
    if (opts.loop && real < (opts.min || 7)) {
      var k = 0;
      while ($$('.sta-l-cell', track).length < (opts.min || 7) && k++ < 12) cells.forEach(function (c) { var x = c.cloneNode(true); x.setAttribute('aria-hidden', 'true'); x.classList.add('is-clone'); track.appendChild(x); });
      cells = $$('.sta-l-cell', track);
    }
    var n = cells.length;
    var cur = 0, timer = null, drag = null, moved = false, dotsBox = $('.sta-l-dots', root);
    function wrap(o) { if (!opts.loop) return o; o = ((o % n) + n) % n; return o > n / 2 ? o - n : o; }
    function draw(pos) {
      cells.forEach(function (c, i) { place(c, wrap(i - pos), i === Math.round(((pos % n) + n) % n)); });
      if (dotsBox) $$('button', dotsBox).forEach(function (d, i) { d.classList.toggle('is-active', i === (((Math.round(pos) % n) + n) % n) % real); });
    }
    function go(i) { cur = opts.loop ? i : Math.max(0, Math.min(n - 1, i)); draw(cur); restart(); }
    function restart() { clearInterval(timer); if (opts.autoplay > 0 && !reduce) timer = setInterval(function () { if (!document.hidden && !root.matches(':hover')) go(cur + 1); }, opts.autoplay * 1000); }
    if (dotsBox) { dotsBox.innerHTML = cells.slice(0, real).map(function (c, i) { return '<button type="button" aria-label="' + (i + 1) + '"></button>'; }).join(''); $$('button', dotsBox).forEach(function (d, i) { d.addEventListener('click', function () { var base = cur - (((cur % n) + n) % n % real); go(base + i); }); }); }
    var prev = $('.sta-l-arrow.is-prev', root), next = $('.sta-l-arrow.is-next', root);
    prev && prev.addEventListener('click', function () { go(cur - 1); });
    next && next.addEventListener('click', function () { go(cur + 1); });
    root.setAttribute('tabindex', '0');
    root.addEventListener('keydown', function (e) { if (e.key === 'ArrowLeft') go(cur - 1); if (e.key === 'ArrowRight') go(cur + 1); });
    // drag / swipe
    var spacing = function () { return (cells[0].offsetWidth || 260) * (opts.spacing || 1); };
    track.addEventListener('pointerdown', function (e) { if (e.button !== 0) return; drag = { x: e.clientX, start: cur }; moved = false; });
    window.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var dx = e.clientX - drag.x;
      if (Math.abs(dx) > 6) { moved = true; root.classList.add('is-dragging'); }
      if (moved) draw(drag.start - dx / spacing());
    });
    window.addEventListener('pointerup', function (e) {
      if (!drag) return;
      var dx = e.clientX - drag.x; root.classList.remove('is-dragging');
      if (moved) go(Math.round(drag.start - dx / spacing()));
      drag = null;
    });
    track.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
    $$('img, a', track).forEach(function (el) { el.setAttribute('draggable', 'false'); });
    // clicking a side card brings it to the middle
    cells.forEach(function (c, i) { c.addEventListener('click', function (e) { if (!c.classList.contains('is-center') && opts.centerOnClick) { e.preventDefault(); go(cur + wrap(i - cur)); } }); });
    window.addEventListener('resize', function () { draw(cur); });
    draw(0); restart();
    return { go: go };
  }

  function arc(root, opts) {
    slider(root, Object.assign({ spacing: 1.06, centerOnClick: false }, opts), function (c, o, isCenter) {
      var w = c.offsetWidth || 260, a = Math.abs(o);
      var x = o * w * 1.06, y = a * a * 12 + a * 6, r = o * 4.5;
      c.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0) rotate(' + r + 'deg)';
      c.style.zIndex = 50 - Math.round(a * 2);
      c.classList.toggle('is-hidden', a > 3.2);
      c.classList.toggle('is-center', isCenter);
    });
    // height of the stage = tallest card + the arc drop
    var cell = $('.sta-l-cell', root); if (cell) root.style.setProperty('--arc-h', (cell.offsetHeight + 70) + 'px');
    window.addEventListener('load', function () { var c = $('.sta-l-cell', root); if (c) root.style.setProperty('--arc-h', (c.offsetHeight + 70) + 'px'); });
  }

  function coverflow(root, opts) {
    slider(root, Object.assign({ spacing: .62, centerOnClick: true }, opts), function (c, o, isCenter) {
      var w = c.offsetWidth || 330, a = Math.min(Math.abs(o), 3.5);
      var x = o * w * .62 - Math.sign(o) * Math.max(0, Math.abs(o) - 1) * w * .12;
      var s = 1 - a * .14;
      c.style.transform = 'translate(-50%,-50%) translateX(' + x + 'px) scale(' + s + ')';
      c.style.zIndex = 50 - Math.round(a * 4);
      c.style.filter = a > .2 ? 'blur(' + Math.min(6, a * 2.6).toFixed(1) + 'px) brightness(' + (1 - a * .06).toFixed(2) + ')' : 'none';
      c.style.opacity = Math.abs(o) > 3.3 ? 0 : 1;
      c.classList.toggle('is-center', isCenter);
    });
    var cell = $('.sta-l-cell', root);
    function h() { var c = $('.sta-l-cell .sta-c', root); if (c) root.style.setProperty('--cf-h', (c.offsetHeight + 20) + 'px'); }
    h(); window.addEventListener('load', h);
    // "DRAG" circle following the mouse
    var dragEl = $('.sta-l-drag', root), track = $('.sta-l-track', root);
    if (dragEl && window.matchMedia && matchMedia('(hover: hover)').matches) {
      track.addEventListener('pointermove', function (e) { var r = root.getBoundingClientRect(); dragEl.style.left = (e.clientX - r.left) + 'px'; dragEl.style.top = (e.clientY - r.top) + 'px'; root.classList.add('is-pointer'); });
      track.addEventListener('pointerleave', function () { root.classList.remove('is-pointer'); });
    }
  }

  function carousel(root, opts) {
    var track = $('.sta-l-track', root), timer = null, dotsBox = $('.sta-l-dots', root);
    if (!track) return;
    function step() { var c = $('.sta-l-cell', track); return c ? c.offsetWidth + parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || 24) : 300; }
    function pages() { return Math.max(1, Math.ceil((track.scrollWidth - 4) / track.clientWidth)); }
    function page() { return Math.round(track.scrollLeft / Math.max(1, track.clientWidth)); }
    function by(dir) {
      var max = track.scrollWidth - track.clientWidth - 4;
      if (dir > 0 && track.scrollLeft >= max) { if (opts.loop) track.scrollTo({ left: 0 }); return; }
      if (dir < 0 && track.scrollLeft <= 4) { if (opts.loop) track.scrollTo({ left: track.scrollWidth }); return; }
      track.scrollBy({ left: dir * step() });
    }
    var prev = $('.sta-l-arrow.is-prev', root), next = $('.sta-l-arrow.is-next', root);
    prev && prev.addEventListener('click', function () { by(-1); restart(); });
    next && next.addEventListener('click', function () { by(1); restart(); });
    function dots() {
      if (!dotsBox) return;
      var p = pages();
      if (dotsBox.children.length !== p) { dotsBox.innerHTML = Array.from({ length: p }, function (x, i) { return '<button type="button" aria-label="' + (i + 1) + '"></button>'; }).join(''); $$('button', dotsBox).forEach(function (d, i) { d.addEventListener('click', function () { track.scrollTo({ left: i * track.clientWidth }); restart(); }); }); }
      $$('button', dotsBox).forEach(function (d, i) { d.classList.toggle('is-active', i === page()); });
    }
    track.addEventListener('scroll', function () { clearTimeout(track.__t); track.__t = setTimeout(dots, 80); }, { passive: true });
    function restart() { clearInterval(timer); if (opts.autoplay > 0 && !reduce) timer = setInterval(function () { if (!document.hidden && !root.matches(':hover')) by(1); }, opts.autoplay * 1000); }
    // mouse drag on desktop
    var d = null;
    track.addEventListener('pointerdown', function (e) { if (e.pointerType !== 'mouse') return; d = { x: e.clientX, l: track.scrollLeft, m: false }; });
    window.addEventListener('pointermove', function (e) { if (!d) return; var dx = e.clientX - d.x; if (Math.abs(dx) > 5) { d.m = true; track.style.scrollSnapType = 'none'; track.style.scrollBehavior = 'auto'; track.scrollLeft = d.l - dx; } });
    window.addEventListener('pointerup', function () { if (!d) return; var m = d.m; d = null; track.style.scrollSnapType = ''; track.style.scrollBehavior = ''; if (m) { track.__drag = true; setTimeout(function () { track.__drag = false; }, 50); } });
    track.addEventListener('click', function (e) { if (track.__drag) { e.preventDefault(); e.stopPropagation(); } }, true);
    $$('img, a', track).forEach(function (el) { el.setAttribute('draggable', 'false'); });
    window.addEventListener('resize', dots);
    dots(); restart();
  }

  function init(scope) {
    $$('[data-sta-listing]', scope).forEach(function (root) {
      if (root.__sta) return; root.__sta = true;
      var o = {}; try { o = JSON.parse(root.getAttribute('data-sta-listing')); } catch (e) {}
      if (o.layout === 'arc') arc(root, o);
      else if (o.layout === 'coverflow') coverflow(root, o);
      else if (o.layout === 'carousel') carousel(root, o);
      else grid(root);
    });
  }
  window.STAListings = { init: init };
  if (document.readyState !== 'loading') init(); else document.addEventListener('DOMContentLoaded', function () { init(); });
  window.addEventListener('elementor/frontend/init', function () { if (window.elementorFrontend && elementorFrontend.hooks) elementorFrontend.hooks.addAction('frontend/element_ready/sta-listing.default', function ($el) { init($el[0] || $el); }); });
})();
