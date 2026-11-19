/* SeeInside Travel – Lists & cards admin: drag & drop elements, live preview */
jQuery(function ($) {
  var C = window.STA_LC || {};
  $(document).on('click', '.sta-lc code.sta-copy', function () {
    var el = this, t = el.textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).catch(function () {}).then(function () { var o = el.textContent; el.textContent = C.copied + ' ✓'; setTimeout(function () { el.textContent = o; }, 1100); });
  });
  $(document).on('click', '.sta-lc a.sta-del', function (e) { if (!confirm(C.confirmDelete)) e.preventDefault(); });

  var $form = $('.sta-lc-form');
  if (!$form.length) return;
  var mode = $form.data('mode'), dirty = false, timer = null, req = null;

  function radios(sel) { $form.on('change', sel + ' input', function () { $(this).closest(sel).parent().children(sel).removeClass('is-on'); $(this).closest(sel).addClass('is-on'); }); }
  radios('.sta-lc-layout'); radios('.sta-lc-skin');

  $('.sta-lc-els').sortable({ handle: '.dashicons-menu', axis: 'y', update: function () { var $l = $(this); $l.siblings('.sta-lc-order').val($l.children('li').map(function () { return $(this).data('key'); }).get().join(',')); refresh(); } });
  $form.on('input change', 'input[type=color]', function () { $(this).removeAttr('data-empty'); });
  $form.on('change', 'input[name="list[own_design]"][type=checkbox]', function () { $('.sta-lc-own').prop('hidden', !this.checked); });

  // another content type: reload the form for it
  $form.on('change', 'select[name="list[source]"]', function () {
    if (dirty && !confirm(C.leave)) return;
    var u = new URL(location.href); u.searchParams.set('source', this.value);
    var layout = $form.find('input[name="list[layout]"]:checked').val(); if (layout) u.searchParams.set('layout', layout);
    dirty = false; location.href = u.toString();
  });

  function data() {
    var fd = new FormData($form[0]);
    $form.find('input[type=color][data-empty]').each(function () { fd.set(this.name, ''); });
    fd.set('action', 'sta_lc_preview'); fd.set('_ajax_nonce', C.nonce); fd.set('mode', mode);
    if (mode === 'card') fd.set('source', $form.data('source'));
    return fd;
  }
  function refresh() {
    dirty = true;
    clearTimeout(timer);
    timer = setTimeout(function () {
      var box = document.getElementById('sta-lc-preview'); if (!box) return;
      box.classList.add('is-loading');
      if (req) req.abort();
      req = new AbortController();
      fetch(C.ajax, { method: 'POST', body: data(), credentials: 'same-origin', signal: req.signal }).then(function (r) { return r.json(); }).then(function (res) {
        if (res && res.success) { box.innerHTML = res.data.html; if (window.STAListings) window.STAListings.init(box); }
      }).catch(function () {}).then(function () { box.classList.remove('is-loading'); });
    }, 350);
  }
  $form.on('input change', 'input, select, textarea', function (e) { if (e.target.name === 'list[source]') return; refresh(); });
  $form.on('submit', function () { dirty = false; $form.find('input[type=color][data-empty]').each(function () { $(this).attr('type', 'hidden').val(''); }); });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* offers picked by hand: search, add, drag to order, remove */
  $('[data-sta-pick]').each(function () {
    var $p = $(this), $list = $p.find('.sta-pick-list'), $res = $p.find('[data-sta-pick-res]'), $q = $p.find('[data-sta-pick-q]'), api = $p.data('source') === 'api_hotel', t = null, xhr = null;
    $list.sortable({ handle: '.dashicons-menu', axis: 'y', update: refresh });
    function has(id) { return $list.children('li[data-id="' + id + '"]').length > 0; }
    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function li(it) {
      return '<li data-id="' + esc(it.id) + '"><span class="dashicons dashicons-menu"></span> <b>' + esc(it.name) + '</b> <small>' + esc(api ? it.id : '#' + it.id) + '</small>'
        + '<button type="button" class="sta-pick-x" aria-label="×">×</button><input type="hidden" name="list[pick][]" value="' + esc(it.id) + '">'
        + (api ? '<input type="hidden" name="list[pick_names][' + esc(it.id) + ']" value="' + esc(it.name) + '">' : '') + '</li>';
    }
    function search() {
      var fd = new FormData(); fd.set('action', 'sta_lc_pick'); fd.set('_ajax_nonce', C.pickNonce); fd.set('source', $p.data('source')); fd.set('q', $q.val());
      if (api) {
        ['country', 'destination', 'zone'].forEach(function (k) { var v = $form.find('[name="list[api][' + k + ']"]').val(); if (v) fd.set(k, v); });
        $form.find('[name="list[api][stars][]"]:checked').each(function () { fd.append('stars[]', this.value); });
        $form.find('[name="list[api][features][]"]:checked').each(function () { fd.append('features[]', this.value); });
      }
      if (xhr) xhr.abort(); xhr = new AbortController();
      $res.addClass('is-loading');
      fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin', signal: xhr.signal }).then(function (r) { return r.json(); }).then(function (r) {
        var items = (r && r.success && r.data.items) || [];
        $res.html(items.length ? items.map(function (it) {
          return '<button type="button" class="sta-pick-hit' + (has(it.id) ? ' is-in' : '') + '" data-it="' + esc(JSON.stringify(it)) + '">'
            + (it.image ? '<img src="' + esc(it.image) + '" alt="" loading="lazy">' : '<span class="sta-pick-noimg"></span>')
            + '<span><b>' + esc(it.name) + '</b><small>' + esc(it.meta || '') + '</small></span><em>' + esc(has(it.id) ? C.added : '+ ' + C.add) + '</em></button>';
        }).join('') + (r.data && r.data.total > items.length ? '<p class="description">' + items.length + ' / ' + r.data.total + '</p>' : '') : '<p class="description">' + esc(r && r.data && r.data.message || C.nothing) + '</p>');
      }).catch(function () {}).then(function () { $res.removeClass('is-loading'); });
    }
    $q.on('input', function () { clearTimeout(t); t = setTimeout(search, 300); }).on('focus', function () { if (!$res.children().length) search(); })
      .on('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    $res.on('click', '.sta-pick-hit', function () {
      var it = $(this).data('it'); if (typeof it === 'string') it = JSON.parse(it);
      if (has(it.id)) return;
      $list.append(li(it)); $(this).addClass('is-in').find('em').text(C.added); refresh();
    });
    $list.on('click', '.sta-pick-x', function () { $(this).closest('li').remove(); refresh(); search(); });
    $p.find('[data-sta-pick-clear]').on('click', function () { $list.empty(); refresh(); search(); });
    // other filters above: new results
    $form.on('change', '[name^="list[api]"]', function () { if ($res.children().length) search(); });
  });

  $('.sta-lc-sizes button').on('click', function () {
    $(this).addClass('is-on').siblings().removeClass('is-on');
    $('#sta-lc-preview').css('max-width', $(this).data('w'));
    setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 60);
  });
});
