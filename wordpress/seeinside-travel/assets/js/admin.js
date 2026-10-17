(function () {
  var C = window.STA_ADMIN || {};
  function post(action, extra) {
    var fd = new FormData(); fd.append('action', action); fd.append('_ajax_nonce', C.nonce);
    Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
    return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  var btn = document.querySelector('[data-sta-import]');
  if (btn) {
    btn.addEventListener('click', function () {
      var box = document.querySelector('.sta-progress'), bar = box.querySelector('.sta-bar span'), log = box.querySelector('.sta-import-log');
      var order = ['meta', 'tour', 'event', 'accommodation', 'visa'], state = { type: 'meta', page: 1 };
      box.hidden = false; btn.disabled = true; log.innerHTML = '<li>' + C.i18n.running + '</li>';
      (function step() {
        post('sta_import', { state: JSON.stringify(state) }).then(function (r) {
          if (!r.success) { log.insertAdjacentHTML('afterbegin', '<li style="color:#b32d2e">' + C.i18n.error + ': ' + ((r.data && r.data.error) || '') + '</li>'); btn.disabled = false; return; }
          var d = r.data; log.insertAdjacentHTML('afterbegin', '<li>' + (d.message || '') + '</li>');
          var idx = order.indexOf(d.type || 'visa'), part = d.progress ? d.progress[1] / d.progress[2] : 0;
          bar.style.width = (d.done ? 100 : Math.min(99, ((idx + part) / order.length) * 100)) + '%';
          if (d.done) { log.insertAdjacentHTML('afterbegin', '<li><b>' + C.i18n.done + '</b> ' + JSON.stringify(d.stats || {}) + '</li>'); btn.disabled = false; return; }
          state = d; step();
        }).catch(function (e) { log.insertAdjacentHTML('afterbegin', '<li style="color:#b32d2e">' + C.i18n.error + ' ' + e + ' – retry…</li>'); setTimeout(step, 3000); });
      })();
    });
  }
  var ch = document.querySelector('[data-sta-changes]');
  ch && ch.addEventListener('click', function () {
    ch.disabled = true;
    post('sta_changes').then(function (r) { ch.disabled = false; alert(JSON.stringify(r.data || r)); location.reload(); });
  });
  document.querySelectorAll('.sta-copy').forEach(function (c) {
    c.addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(c.textContent); c.style.background = '#d1fae5'; });
  });
})();
