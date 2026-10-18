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

  $('.sta-lc-sizes button').on('click', function () {
    $(this).addClass('is-on').siblings().removeClass('is-on');
    $('#sta-lc-preview').css('max-width', $(this).data('w'));
    setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 60);
  });
});
