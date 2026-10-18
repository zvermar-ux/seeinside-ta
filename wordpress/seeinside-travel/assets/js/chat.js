/* SeeInside Travel – live chat widget (no dependencies). Talks to SeeInside directly from the browser. */
(function () {
  'use strict';
  var S = window.STA_CHAT; if (!S || !S.config || !S.config.enabled) return;
  var C = S.config, T = S.i18n || {}, L = S.lang || 'mk';
  var store = { get: function (k) { try { return JSON.parse(localStorage.getItem(k)); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} } };

  function tr(v) { if (!v || typeof v !== 'object') return v || ''; return v[L] || v.mk || v.en || ''; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function h(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function uuid() { return (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); })); }
  function money(v, cur) { cur = cur || S.currency || 'EUR'; var n = Number(v || 0).toLocaleString('de-DE', { maximumFractionDigits: 0 }); return cur === 'MKD' ? n + ' ден.' : (cur === 'EUR' ? '€' : cur + ' ') + n; }
  function date(s) { if (!s) return ''; var d = new Date(s); return isNaN(d) ? '' : ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear(); }

  var ICON = {
    chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>',
    close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    back: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>',
    send: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>',
    ai: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.6L12 15l-1.8-4.4L5.5 9l4.7-1.4z"/><path d="M19 15l.8 2 2 .8-2 .7-.8 2-.8-2-2-.7 2-.8z"/></svg>',
    quiz: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
    callme: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>',
    viber: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.4 1.6C9.6 1.6 5.2 1.9 2.9 4 1.2 5.7.6 8.2.5 11.3c-.1 3.1-.2 8.9 5.4 10.5v2.4s0 1 .6 1.2c.8.2 1.2-.5 2-1.4l1.4-1.6c3.8.3 6.8-.4 7.1-.5.8-.3 5.1-.8 5.8-6.6.7-6-.4-9.7-2.4-11.4C19.8 2.8 17.1 1.6 11.4 1.6zm.2 1.7c4.9 0 7.2 1 7.8 1.5 1.6 1.4 2.4 4.6 1.8 9.6-.6 4.8-4.1 5.1-4.7 5.3-.3.1-2.9.7-6.2.5 0 0-2.4 2.9-3.2 3.7-.1.1-.3.2-.4.1-.2 0-.2-.3-.2-.5v-4C2 18.2 2.3 13.6 2.3 11.3c.1-2.6.6-4.7 2-6 1.9-1.8 5.6-2 7.3-2zm.4 2.6c-.2 0-.2.3 0 .3 1.4 0 2.7.5 3.7 1.4 1 1 1.6 2.4 1.6 4.2 0 .2.3.2.3 0 0-1.9-.6-3.4-1.7-4.5-1-1-2.4-1.5-3.9-1.4zm-3.8.7c-.3 0-.6.1-.8.3-.6.5-1.2 1.2-1 1.9 0 0 .2.6 1 2.2.4.7.9 1.4 1.4 2 .5.7 1.4 1.5 1.4 1.5l.1.1c.6.5 1.3 1 2 1.4 1.6.8 2.2 1 2.2 1 .7.2 1.4-.4 1.9-1 .3-.3.4-.9 0-1.3-.5-.4-1-.8-1.6-1.2-.5-.3-1-.1-1.2.2l-.4.5c-.2.3-.6.2-.6.2-2.9-.8-3.7-3.7-3.7-3.7s0-.4.2-.6l.5-.4c.3-.2.5-.8.2-1.2l-.6-.9c-.3-.4-.6-.8-.8-.9-.1-.1-.2-.1-.2-.1zm4.1.5c-.2 0-.2.3 0 .3 1 .1 1.7.4 2.2.9.5.6.8 1.3.8 2.3 0 .2.3.2.3 0 0-1-.3-1.9-.9-2.5-.6-.6-1.4-1-2.4-1zm.3 1.1c-.2 0-.2.3 0 .3.9 0 1.3.5 1.4 1.4 0 .2.3.2.3 0-.1-1.1-.7-1.7-1.7-1.7z"/></svg>',
    messenger: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.4 2 2 6.1 2 11.6c0 2.9 1.2 5.4 3.1 7.1.2.1.3.4.3.6l.1 1.8c0 .6.6 1 1.1.7l2-.9c.2-.1.4-.1.6 0 .9.3 1.8.4 2.8.4 5.6 0 10-4.1 10-9.6S17.6 2 12 2zm6 7.5l-2.9 4.6c-.5.7-1.5.9-2.2.4l-2.3-1.7a.6.6 0 0 0-.7 0l-3.1 2.4c-.4.3-1-.2-.7-.6l2.9-4.6c.5-.7 1.5-.9 2.2-.4l2.3 1.7c.2.2.5.2.7 0l3.1-2.4c.4-.3 1 .2.7.6z"/></svg>',
    whatsapp: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5.3-.5c.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.3zM12 21.8c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8zM12 0C5.4 0 0 5.4 0 12c0 2.1.6 4.2 1.6 6L0 24l6.2-1.6A12 12 0 1 0 12 0z"/></svg>',
    phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>',
    email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg>'
  };

  /* ---------- state ---------- */
  var visitor = store.get('sta_chat_visitor') || uuid(); store.set('sta_chat_visitor', visitor);
  var conv = store.get('sta_chat_conv');   // {id, token, at}
  var msgs = [], lastId = 0, human = false, view = 'home', open = false, busy = false, timer = null, unread = 0;

  function api(path, body, method) {
    var url = S.api + path, opts = { method: method || (body ? 'POST' : 'GET'), headers: { Accept: 'application/json' } };
    if (opts.method === 'GET' && body) { url += '?' + new URLSearchParams(body); }
    else if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(Object.assign({ locale: L }, body)); }
    return fetch(url, opts).then(function (r) { return r.json().catch(function () { return {}; }).then(function (b) { if (!r.ok) { var e = new Error(b.message || 'HTTP ' + r.status); e.status = r.status; throw e; } return b.data || b; }); });
  }

  /* ---------- DOM ---------- */
  var root = h('div', 'sta-chat is-' + (C.position === 'left' ? 'left' : 'right'));
  root.style.setProperty('--chat-c', C.color || '#0e7490');
  var launcher = h('button', 'sta-chat-launcher', '<span class="sta-chat-launcher-ico">' + ICON.chat + '</span><span class="sta-chat-launcher-x">' + ICON.close + '</span>' + (tr(C.launcher) ? '<span class="sta-chat-launcher-label">' + esc(tr(C.launcher)) + '</span>' : '') + '<i class="sta-chat-badge" hidden></i>');
  launcher.type = 'button'; launcher.setAttribute('aria-label', tr(C.launcher) || 'Chat'); launcher.setAttribute('aria-expanded', 'false');
  var panel = h('div', 'sta-chat-panel'); panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', tr(C.title));
  var avatar = C.avatar ? '<img src="' + esc(C.avatar) + '" alt="">' : '<span>' + ICON.ai + '</span>';
  panel.innerHTML = '<header class="sta-chat-head"><button type="button" class="sta-chat-back" aria-label="' + esc(T.back) + '" hidden>' + ICON.back + '</button>'
    + '<div class="sta-chat-ava">' + avatar + '<i></i></div><div class="sta-chat-ttl"><b>' + esc(tr(C.title)) + '</b><small>' + esc(tr(C.subtitle)) + '</small></div>'
    + '<button type="button" class="sta-chat-x" aria-label="' + esc(T.close) + '">' + ICON.close + '</button></header>'
    + '<div class="sta-chat-body" aria-live="polite"></div><div class="sta-chat-foot"></div>';
  root.appendChild(panel); root.appendChild(launcher);
  var body = panel.querySelector('.sta-chat-body'), foot = panel.querySelector('.sta-chat-foot'), back = panel.querySelector('.sta-chat-back'), badge = launcher.querySelector('.sta-chat-badge');

  function toggle(state) {
    open = state === undefined ? !open : state;
    root.classList.toggle('is-open', open); launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { unread = 0; badge.hidden = true; if (!body.childNodes.length) (conv && conv.id && msgs.length ? showChat() : showHome()); schedule(); }
    document.documentElement.classList.toggle('sta-chat-lock', open && window.innerWidth < 560);
  }
  launcher.addEventListener('click', function () { toggle(); });
  panel.querySelector('.sta-chat-x').addEventListener('click', function () { toggle(false); });
  back.addEventListener('click', function () { showHome(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && open) toggle(false); });

  function setView(v) { view = v; back.hidden = v === 'home'; panel.setAttribute('data-view', v); body.innerHTML = ''; foot.innerHTML = ''; }

  /* ---------- home: how do you want to talk? ---------- */
  function option(icon, cls, title, sub, onClick, href) {
    var el = h(href ? 'a' : 'button', 'sta-chat-opt ' + cls, '<span class="sta-chat-opt-ico">' + icon + '</span><span class="sta-chat-opt-txt"><b>' + esc(title) + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</span>');
    if (href) { el.href = href; if (/^https?:/.test(href)) { el.target = '_blank'; el.rel = 'noopener'; } }
    else { el.type = 'button'; el.addEventListener('click', onClick); }
    return el;
  }
  function showHome() {
    setView('home');
    body.appendChild(h('div', 'sta-chat-bubble is-bot', esc(tr(C.greeting))));
    var list = h('div', 'sta-chat-opts');
    if (C.ai) list.appendChild(option(ICON.ai, 'is-ai', T.aiChat, T.aiChatSub, function () { startChat(); }));
    if ((C.questionnaires || []).length) list.appendChild(option(ICON.quiz, 'is-quiz', T.choose, T.chooseSub, showQuizzes));
    (C.channels || []).forEach(function (ch) {
      list.appendChild(option(ICON[ch.type] || ICON.chat, 'is-' + ch.type, T[ch.type] || ch.type, ch.label || '', null, ch.url));
    });
    if (C.ask_contact) list.appendChild(option(ICON.callme, 'is-callme', T.callMe, T.callMeSub, showContact));
    if (conv && conv.id && msgs.length > 1) list.insertBefore(option(ICON.chat, 'is-continue', T.aiChat, '…' + esc((msgs[msgs.length - 1] || {}).body || '').slice(0, 40), function () { showChat(); }), list.firstChild);
    body.appendChild(list);
    var meta = [tr(C.hours), tr(C.privacy)].filter(Boolean);
    if (meta.length) body.appendChild(h('p', 'sta-chat-meta', meta.map(esc).join('<br>')));
  }

  /* ---------- conversation ---------- */
  function ensureConv() {
    if (conv && conv.id) return Promise.resolve(conv);
    return api('start', { visitor: visitor, page_url: location.href }).then(function (d) {
      conv = { id: d.conversation, token: d.token }; store.set('sta_chat_conv', conv);
      msgs = []; lastId = 0; add(d.messages || []); human = !!d.human; return conv;
    });
  }
  function restore() {
    if (!conv || !conv.id) return;
    api('poll', { conversation: conv.id, token: conv.token, after: 0 }, 'GET').then(function (d) {
      msgs = []; lastId = 0; add(d.messages || []); human = !!d.human;
    }).catch(function (e) { if (e.status === 404 || e.status === 422) { conv = null; store.set('sta_chat_conv', null); } });
  }
  function add(list, fromPoll) {
    list.forEach(function (m) {
      if (m.id <= lastId) return;
      lastId = m.id; msgs.push(m);
      if (view === 'chat') draw(m);
      if (fromPoll && !open && m.role !== 'visitor') { unread++; badge.textContent = unread; badge.hidden = false; }
    });
  }
  function itemCard(it) {
    var url = (S.paths[it.type] || '').replace('%s', encodeURIComponent(it.slug));
    var a = h('a', 'sta-chat-card', (it.image ? '<img src="' + esc(it.image) + '" alt="" loading="lazy">' : '<span class="sta-chat-card-noimg"></span>')
      + '<span class="sta-chat-card-txt"><b>' + esc(tr(it.title)) + '</b><small>' + [it.date ? date(it.date) : '', it.price ? esc(T.from) + ' <strong>' + esc(money(it.price, it.currency)) + '</strong>' : ''].filter(Boolean).join(' · ') + '</small></span><span class="sta-chat-card-go">' + esc(T.view) + ' →</span>');
    a.href = url || '#';
    return a;
  }
  function draw(m) {
    var b = h('div', 'sta-chat-bubble is-' + m.role);
    b.innerHTML = (m.role === 'operator' ? '<span class="sta-chat-who">' + esc(m.name || T.agent) + '</span>' : '') + esc(m.body).replace(/\n/g, '<br>');
    var thread = body.querySelector('.sta-chat-thread') || body;
    thread.appendChild(b);
    if (m.items && m.items.length) { var cards = h('div', 'sta-chat-cards'); m.items.forEach(function (it) { cards.appendChild(itemCard(it)); }); thread.appendChild(cards); }
    body.scrollTop = body.scrollHeight;
  }
  function typing(on) {
    var t = body.querySelector('.sta-chat-typing');
    if (on && !t) { (body.querySelector('.sta-chat-thread') || body).appendChild(h('div', 'sta-chat-bubble is-bot sta-chat-typing', '<i></i><i></i><i></i>')); body.scrollTop = body.scrollHeight; }
    if (!on && t) t.remove();
  }
  function showChat() {
    setView('chat');
    body.appendChild(h('div', 'sta-chat-thread'));
    msgs.forEach(draw);
    var form = h('form', 'sta-chat-input', '<textarea rows="1" maxlength="1500" placeholder="' + esc(T.placeholder) + '" aria-label="' + esc(T.placeholder) + '"></textarea><button type="submit" aria-label="' + esc(T.send) + '">' + ICON.send + '</button>');
    var ta = form.querySelector('textarea');
    ta.addEventListener('input', function () { ta.style.height = 'auto'; ta.style.height = Math.min(120, ta.scrollHeight) + 'px'; });
    ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); } });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); var text = ta.value.trim(); if (!text || busy) return;
      busy = true; ta.value = ''; ta.style.height = 'auto';
      var temp = { id: 0, role: 'visitor', body: text }; draw(temp); var tempEl = body.querySelector('.sta-chat-thread').lastChild;
      if (!human) typing(true);
      ensureConv().then(function () { return api('message', { conversation: conv.id, token: conv.token, text: text, page_url: location.href }); })
        .then(function (d) { tempEl.remove(); typing(false); add(d.messages || []); human = !!d.human; })
        .catch(function (err) { typing(false); if (err.status === 404) { conv = null; store.set('sta_chat_conv', null); } draw({ role: 'system', body: err.status === 429 ? err.message : T.error }); })
        .then(function () { busy = false; ta.focus(); });
    });
    foot.appendChild(form);
    var links = (C.channels || []).filter(function (c) { return c.type === 'viber' || c.type === 'messenger' || c.type === 'whatsapp'; });
    if (links.length) {
      var p = h('div', 'sta-chat-alt', esc(T.or) + ' ');
      links.forEach(function (c) { var a = h('a', 'is-' + c.type, ICON[c.type] + ' ' + esc(T[c.type])); a.href = c.url; a.target = '_blank'; a.rel = 'noopener'; p.appendChild(a); });
      foot.appendChild(p);
    }
    setTimeout(function () { ta.focus(); }, 60);
  }
  function startChat() {
    showChat();
    if (!conv || !conv.id) { typing(true); ensureConv().then(function () { typing(false); body.querySelector('.sta-chat-thread').innerHTML = ''; msgs.forEach(draw); }).catch(function () { typing(false); draw({ role: 'system', body: T.error }); }); }
  }

  /* ---------- questionnaires ---------- */
  function showQuizzes() {
    var qs = C.questionnaires || [];
    if (qs.length === 1) return runQuiz(qs[0]);
    setView('quiz');
    var grid = h('div', 'sta-chat-quizzes');
    qs.forEach(function (q) {
      var b = h('button', 'sta-chat-quiz', (q.image ? '<img src="' + esc(q.image) + '" alt="" loading="lazy">' : '<span class="sta-chat-quiz-ico">' + esc(q.icon || '✨') + '</span>') + '<b>' + esc(tr(q.title)) + '</b>' + (tr(q.intro) ? '<small>' + esc(tr(q.intro)) + '</small>' : ''));
      b.type = 'button'; b.addEventListener('click', function () { runQuiz(q); }); grid.appendChild(b);
    });
    body.appendChild(grid);
  }
  function runQuiz(q) {
    var answers = {}, i = 0, questions = q.questions || [];
    function step() {
      setView('quiz');
      var x = questions[i];
      if (!x) return finish();
      var wrap = h('div', 'sta-chat-step');
      wrap.appendChild(h('div', 'sta-chat-progress', '<i style="width:' + Math.round(i / questions.length * 100) + '%"></i>'));
      wrap.appendChild(h('div', 'sta-chat-qhead', (q.icon ? '<span>' + esc(q.icon) + '</span> ' : '') + esc(tr(q.title)) + ' · ' + (i + 1) + '/' + questions.length));
      wrap.appendChild(h('h4', 'sta-chat-q', esc(tr(x.label))));
      var opts = h('div', 'sta-chat-choices' + (x.type === 'multi' ? ' is-multi' : ''));
      if (x.type === 'text') {
        var inp = h('textarea', 'sta-chat-text'); inp.rows = 3; inp.maxLength = 500; opts.appendChild(inp);
        var nb = h('button', 'sta-chat-btn', esc(i === questions.length - 1 ? T.showOffers : T.next)); nb.type = 'button';
        nb.addEventListener('click', function () { answers[x.key] = inp.value.trim(); i++; step(); }); opts.appendChild(nb);
      } else {
        var chosen = [];
        (x.options || []).forEach(function (o) {
          var label = tr(o), b = h('button', 'sta-chat-choice', esc(label)); b.type = 'button';
          b.addEventListener('click', function () {
            if (x.type === 'multi') { var k = chosen.indexOf(label); if (k < 0) chosen.push(label); else chosen.splice(k, 1); b.classList.toggle('is-on'); }
            else { answers[x.key] = label; i++; step(); }
          });
          opts.appendChild(b);
        });
        if (x.type === 'multi') { var mb = h('button', 'sta-chat-btn', esc(i === questions.length - 1 ? T.showOffers : T.next)); mb.type = 'button'; mb.addEventListener('click', function () { answers[x.key] = chosen.slice(); i++; step(); }); opts.appendChild(mb); }
      }
      wrap.appendChild(opts);
      var skip = h('button', 'sta-chat-skip', esc(T.skip)); skip.type = 'button'; skip.addEventListener('click', function () { i++; step(); });
      wrap.appendChild(skip);
      body.appendChild(wrap);
    }
    function finish() {
      showChat(); typing(true);
      ensureConv().then(function () { return api('questionnaire', { conversation: conv.id, token: conv.token, questionnaire: q.id, answers: answers }); })
        .then(function (d) { typing(false); add(d.messages || []); })
        .catch(function () { typing(false); draw({ role: 'system', body: T.error }); });
    }
    step();
  }

  /* ---------- call me back ---------- */
  function showContact() {
    setView('contact');
    var f = h('form', 'sta-chat-contact',
      '<p>' + esc(tr(C.handoff) || T.callMeSub) + '</p>'
      + '<label><span>' + esc(T.name) + '</span><input name="name" maxlength="120" autocomplete="name"></label>'
      + '<label><span>' + esc(T.phoneField) + '</span><input name="phone" type="tel" maxlength="50" autocomplete="tel"></label>'
      + '<label><span>' + esc(T.emailField) + '</span><input name="email" type="email" maxlength="150" autocomplete="email"></label>'
      + '<label><span>' + esc(T.note) + '</span><textarea name="note" rows="2" maxlength="1000"></textarea></label>'
      + '<div class="sta-chat-err" hidden></div><button type="submit" class="sta-chat-btn">' + esc(T.sendContact) + '</button>');
    f.addEventListener('submit', function (e) {
      e.preventDefault(); var d = {}; new FormData(f).forEach(function (v, k) { d[k] = String(v).trim(); });
      var err = f.querySelector('.sta-chat-err');
      if (!d.phone && !d.email) { err.textContent = T.contactNeeded; err.hidden = false; return; }
      var btn = f.querySelector('button'); btn.disabled = true;
      ensureConv().then(function () { return api('contact', Object.assign({ conversation: conv.id, token: conv.token }, d)); })
        .then(function (r) { add(r.messages || []); showChat(); })
        .catch(function (x) { err.textContent = x.message || T.error; err.hidden = false; btn.disabled = false; });
    });
    body.appendChild(f);
  }

  /* ---------- operator answers ---------- */
  function poll() {
    if (!conv || !conv.id || document.hidden) return;
    api('poll', { conversation: conv.id, token: conv.token, after: lastId }, 'GET').then(function (d) {
      var wasHuman = human; human = !!d.human;
      add(d.messages || [], true);
      if (human && !wasHuman && view === 'chat') draw({ role: 'system', body: T.humanOn });
    }).catch(function (e) { if (e.status === 404) { conv = null; store.set('sta_chat_conv', null); } });
  }
  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(function () { poll(); schedule(); }, open ? 5000 : 30000);
  }

  function mount() { document.body.appendChild(root); restore(); if (conv) schedule(); }
  if (document.readyState !== 'loading') mount(); else document.addEventListener('DOMContentLoaded', mount);
  window.STAChat = { open: function () { toggle(true); }, close: function () { toggle(false); } };
})();
