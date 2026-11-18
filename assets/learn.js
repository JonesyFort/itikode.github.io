/* ITiKODE learner pages: mobile menu + one-question-at-a-time quiz player.
   Answers are checked on the server (section.php?do=check), so the answer key
   is never sent to the browser before a student has committed to a choice. */
(function () {
  'use strict';

  // ── mobile sidebar toggle ───────────────────────────────────────────────
  var menuBtn = document.querySelector('[data-menu]');
  if (menuBtn) {
    menuBtn.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
    document.addEventListener('click', function (e) {
      if (document.body.classList.contains('nav-open') && !e.target.closest('.lx-side') && !e.target.closest('[data-menu]')) {
        document.body.classList.remove('nav-open');
      }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') document.body.classList.remove('nav-open'); });
  }

  // ── quiz player ─────────────────────────────────────────────────────────
  var root = document.getElementById('lx-quiz');
  if (!root) return;

  var data, csrf;
  try {
    data = JSON.parse(document.getElementById('lx-data').textContent);
    csrf = JSON.parse(document.getElementById('lx-csrf').textContent);
  } catch (err) { return; }

  var qs = data.questions;
  var n = qs.length;
  var LETTERS = ['A', 'B', 'C', 'D', 'E', 'F'];
  var results = [];                      // results[i] = {chosen, correct, correctIndex, explanation}
  Object.keys(data.resume || {}).forEach(function (k) { results[+k] = data.resume[k]; });

  var current = 0;
  while (current < n - 1 && results[current]) current++;   // resume at first unchecked question
  var selected = null;
  var busy = false;

  var el = {
    count: document.getElementById('lx-count'),
    tracker: document.getElementById('lx-tracker'),
    stagePart: document.getElementById('lx-stage-part'),
    stageTitle: document.getElementById('lx-stage-title'),
    tag: document.getElementById('lx-tag'),
    text: document.getElementById('lx-qtext'),
    hint: document.getElementById('lx-hint'),
    opts: document.getElementById('lx-opts'),
    action: document.getElementById('lx-action'),
    msg: document.getElementById('lx-msg'),
    form: document.getElementById('lx-form')
  };

  var ICON = {
    check: '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
    x: '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
    next: '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>'
  };

  // text with `backticks` -> <code>, built with the DOM so nothing is parsed as HTML
  function rich(node, text) {
    node.textContent = '';
    var parts = String(text).split('`');
    parts.forEach(function (p, i) {
      if (i % 2 === 1 && i < parts.length - 1) {
        var c = document.createElement('code');
        c.textContent = p;
        node.appendChild(c);
      } else {
        node.appendChild(document.createTextNode((i > 0 && i % 2 === 1 ? '`' : '') + p));
      }
    });
  }

  function renderTracker() {
    el.tracker.textContent = '';
    if (n > 12) {                         // long assessments get a bar instead of boxes
      var bar = document.createElement('div');
      bar.className = 'lx-bar';
      var fill = document.createElement('i');
      var done = results.filter(Boolean).length;
      fill.style.width = Math.round(done / n * 100) + '%';
      bar.appendChild(fill);
      el.tracker.appendChild(bar);
      return;
    }
    qs.forEach(function (q, i) {
      var s = document.createElement('span');
      var r = results[i];
      s.textContent = i + 1;
      if (r) s.className = r.correct ? 'ok' : 'bad';
      else if (i === current) s.className = 'cur';
      el.tracker.appendChild(s);
    });
  }

  function render() {
    var q = qs[current];
    var r = results[current];
    var revealed = !!r;
    if (revealed) selected = r.chosen;

    el.count.textContent = (current + 1) + '/' + n;
    renderTracker();
    el.stagePart.textContent = 'Question ' + (current + 1) + ' of ' + n;
    el.stageTitle.textContent = q.part ? q.part : data.stageTitle;
    el.tag.textContent = 'Question ' + (current + 1);
    rich(el.text, q.text);
    el.msg.textContent = '';

    // answer choices
    el.opts.textContent = '';
    q.options.forEach(function (opt, idx) {
      var b = document.createElement('button');
      b.type = 'button';
      var cls = 'lx-opt';
      if (revealed) {
        if (idx === r.correctIndex) cls += ' ok';
        else if (idx === r.chosen) cls += ' bad';
        else cls += ' dim';
      } else if (selected === idx) cls += ' sel';
      b.className = cls;
      b.disabled = revealed || busy;
      b.setAttribute('aria-pressed', String(selected === idx));
      var letter = document.createElement('span');
      letter.className = 'lx-letter';
      letter.textContent = LETTERS[idx] || String(idx + 1);
      var tx = document.createElement('span');
      tx.className = 'tx';
      rich(tx, opt);
      b.appendChild(letter);
      b.appendChild(tx);
      if (revealed && (idx === r.correctIndex || idx === r.chosen)) {
        var res = document.createElement('span');
        res.className = 'res';
        res.innerHTML = idx === r.correctIndex ? ICON.check : ICON.x;   // fixed icon markup only
        b.appendChild(res);
      }
      b.addEventListener('click', function () { if (!revealed && !busy) { selected = idx; render(); } });
      el.opts.appendChild(b);
    });

    // feedback box
    el.hint.className = '';
    el.hint.textContent = '';
    if (revealed) {
      el.hint.className = r.correct ? 'ok' : 'bad';
      var line = document.createElement('div');
      line.appendChild(document.createTextNode(r.correct
        ? '\u2705 Correct! Nice work.'
        : '\u274C Not quite. The answer is \u201C' + q.options[r.correctIndex] + '\u201D.'));
      el.hint.appendChild(line);
      if (r.explanation) {
        var ex = document.createElement('div');
        ex.className = 'ex';
        ex.textContent = r.explanation;
        el.hint.appendChild(ex);
      }
    } else {
      el.hint.textContent = 'Pick one answer, then press Check Answer.';
    }

    // main button
    el.action.textContent = '';
    if (!revealed) {
      el.action.textContent = busy ? 'Checking\u2026' : 'Check Answer';
      el.action.disabled = selected === null || busy;
    } else {
      el.action.appendChild(document.createTextNode(current < n - 1 ? 'Next Question ' : 'See Results '));
      var span = document.createElement('span');
      span.style.display = 'flex';
      span.innerHTML = ICON.next;
      el.action.appendChild(span);
      el.action.disabled = busy;
    }
  }

  function check() {
    if (selected === null || busy || results[current]) return;
    busy = true; render();
    var body = new URLSearchParams();
    body.set('_csrf', csrf);
    body.set('do', 'check');
    body.set('qi', String(current));
    body.set('choice', String(selected));
    fetch(data.url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (res) {
        if (!res.ok) throw new Error(res.status === 419 ? 'expired' : 'failed');
        return res.json();
      })
      .then(function (j) {
        if (!j || !j.ok) throw new Error('failed');
        results[current] = j;
        busy = false; render();
        el.action.focus();
      })
      .catch(function (err) {
        busy = false; render();
        el.msg.textContent = err.message === 'expired'
          ? 'Your session expired. Reload the page and try again.'
          : 'Could not check that answer. Check your connection and try again.';
      });
  }

  function advance() {
    if (!results[current] || busy) return;
    if (current < n - 1) {
      current++; selected = null; render();
      el.text.scrollIntoView({ block: 'nearest' });
      return;
    }
    // last question done: send everything for scoring
    busy = true; render();
    results.forEach(function (r, i) {
      var inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'q[' + i + ']';
      inp.value = String(r.chosen);
      el.form.appendChild(inp);
    });
    el.form.submit();
  }

  el.action.addEventListener('click', function () { if (results[current]) advance(); else check(); });

  // keyboard: A-D / 1-4 pick an answer, Enter checks or continues
  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey || busy) return;
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    if (results[current]) return;
    var k = e.key.toLowerCase();
    var idx = LETTERS.map(function (l) { return l.toLowerCase(); }).indexOf(k);
    if (idx < 0 && /^[1-6]$/.test(k)) idx = +k - 1;
    if (idx >= 0 && idx < qs[current].options.length) { selected = idx; render(); }
  });

  render();
})();

/* ── Lesson pages: live Python practice editors, progress stars, small widgets ─────────
   Python runs in the browser (Pyodide, loaded from a CDN inside a Web Worker), so an
   endless loop can never freeze the page. It needs an internet connection the first time. */
(function () {
  'use strict';

  // checklist on the Introduction page
  var chks = document.querySelectorAll('.lx-chk');
  var readyEl = document.querySelector('.lx-ready');
  chks.forEach(function (b) {
    b.addEventListener('click', function () {
      var on = b.classList.toggle('on');
      b.setAttribute('aria-pressed', String(on));
      if (readyEl) readyEl.textContent = document.querySelectorAll('.lx-chk.on').length + '/' + chks.length + ' ready';
    });
  });

  var plays = [].slice.call(document.querySelectorAll('.lx-play'));
  if (!plays.length) return;

  // ── per-student progress, remembered in this browser ───────────────────
  var topicEl = document.querySelector('[data-topic]');
  var key = 'itikode-solved-' + (document.body.getAttribute('data-uid') || '0') + '-' + (topicEl ? topicEl.getAttribute('data-topic') : 'page');
  var solved = [];
  try { solved = JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { solved = []; }
  function save() { try { localStorage.setItem(key, JSON.stringify(solved)); } catch (e) { /* private mode */ } }

  // the server also remembers solved challenges (for the teacher's gradebook and for other devices)
  var host = document.querySelector('[data-practice-url]');
  if (host) {
    try {
      JSON.parse(host.getAttribute('data-solved') || '[]').forEach(function (id) { if (solved.indexOf(id) === -1) solved.push(id); });
    } catch (e) { /* ignore */ }
  }
  function report(id) {
    if (!host) return;
    var body = new URLSearchParams();
    body.set('_csrf', host.getAttribute('data-csrf') || '');
    body.set('do', 'practice');
    body.set('challenge', id);
    fetch(host.getAttribute('data-practice-url'), { method: 'POST', body: body, credentials: 'same-origin' }).catch(function () {});
  }

  // ── struggle statistics: runs, wrong runs, card-game misses, time, skips (teachers see these) ──
  var uid = document.body.getAttribute('data-uid') || '0';
  var topicKey = topicEl ? topicEl.getAttribute('data-topic') : 'page';
  var statsKey = 'itikode-stats-' + uid + '-' + topicKey;
  var stats = {}, dirty = {}, activeId = null;
  try { stats = JSON.parse(localStorage.getItem(statsKey) || '{}') || {}; } catch (e) { stats = {}; }
  var STAT_KEYS = ['runs', 'fails', 'flips', 'misses', 'seconds', 'skipped'];
  function S(id) { return stats[id] || (stats[id] = { runs: 0, fails: 0, flips: 0, misses: 0, seconds: 0, skipped: 0 }); }
  function touch(id) { dirty[id] = true; }
  if (host) {
    try {
      var fromServer = JSON.parse(host.getAttribute('data-stats') || '{}');
      Object.keys(fromServer).forEach(function (id) {
        var a = S(id), b = fromServer[id];
        STAT_KEYS.forEach(function (k) { a[k] = Math.max(a[k] || 0, +b[k] || 0); });
      });
    } catch (e) { /* ignore */ }
  }
  function flushStats(keepalive) {
    try { localStorage.setItem(statsKey, JSON.stringify(stats)); } catch (e) { /* private mode */ }
    if (!host) { dirty = {}; return; }
    Object.keys(dirty).forEach(function (id) {
      var s = S(id), body = new URLSearchParams();
      body.set('_csrf', host.getAttribute('data-csrf') || ''); body.set('do', 'stats'); body.set('challenge', id);
      STAT_KEYS.forEach(function (k) { body.set(k, String(s[k] || 0)); });
      fetch(host.getAttribute('data-practice-url'), { method: 'POST', body: body, credentials: 'same-origin', keepalive: !!keepalive }).catch(function () {});
    });
    dirty = {};
  }
  setInterval(function () { if (!document.hidden && activeId) { S(activeId).seconds++; touch(activeId); } }, 1000);
  setInterval(function () { if (Object.keys(dirty).length) flushStats(false); }, 15000);
  document.addEventListener('visibilitychange', function () { if (document.hidden) flushStats(true); });
  window.addEventListener('pagehide', function () { flushStats(true); });
  var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ── Python runtime in a Web Worker ─────────────────────────────────────
  var PY_URL = 'https://cdn.jsdelivr.net/pyodide/v0.26.4/full/';
  var TIMEOUT = 8000;
  var workerSrc = 'var PY_URL=' + JSON.stringify(PY_URL) + ';(' + function () {
    importScripts(PY_URL + 'pyodide.js');
    var py = null;
    var ready = loadPyodide({ indexURL: PY_URL }).then(function (p) {
      py = p; py.setStdin({ stdin: function () { return undefined; } });
      postMessage({ type: 'ready' });
    }).catch(function (e) { postMessage({ type: 'fail', message: String(e && e.message || e) }); throw e; });
    onmessage = async function (ev) {
      var id = ev.data.id, code = ev.data.code;
      try { await ready; } catch (e) { postMessage({ type: 'result', id: id, ok: false, output: 'Could not load the Python runtime.' }); return; }
      var out = '';
      py.setStdout({ batched: function (t) { out += t + '\n'; } });
      py.setStderr({ batched: function (t) { out += t + '\n'; } });
      var ns = py.globals.get('dict')();
      try {
        await py.runPythonAsync(code, { globals: ns });
        postMessage({ type: 'result', id: id, ok: true, output: out.replace(/\n$/, '') });
      } catch (err) {
        var raw = String((err && err.message) || err).trim().split('\n');
        postMessage({ type: 'result', id: id, ok: false, output: (out + raw[raw.length - 1]).trim() });
      } finally { if (ns.destroy) ns.destroy(); }
    };
  }.toString() + ')();';

  var worker = null, readyP = null, nextId = 1, pending = {};
  function stop() { if (worker) worker.terminate(); worker = null; readyP = null; }
  function start() {
    var w = new Worker(URL.createObjectURL(new Blob([workerSrc], { type: 'text/javascript' })));
    worker = w;
    readyP = new Promise(function (resolve, reject) {
      w.onmessage = function (ev) {
        var m = ev.data;
        if (m.type === 'ready') resolve();
        else if (m.type === 'fail') { reject(new Error(m.message)); stop(); }
        else if (m.type === 'result') {
          var p = pending[m.id];
          if (p) { clearTimeout(p.timer); delete pending[m.id]; p.resolve({ ok: m.ok, output: m.output }); }
        }
      };
      w.onerror = function () { reject(new Error('worker')); stop(); };
    });
    readyP.catch(function () {});
    return readyP;
  }
  function getPy() { if (!readyP) start(); return readyP; }
  function runPy(code) {
    return getPy().then(function () {
      var w = worker, id = nextId++;
      return new Promise(function (resolve) {
        var timer = setTimeout(function () {
          delete pending[id]; stop();
          resolve({ ok: false, output: 'Stopped: your code ran for more than ' + (TIMEOUT / 1000) + ' seconds. Check for a loop that never ends (like while True).' });
          getPy().catch(function () {});
        }, TIMEOUT);
        pending[id] = { resolve: resolve, timer: timer };
        w.postMessage({ id: id, code: code });
      });
    }, function () { return { ok: false, output: 'Could not load the Python runtime. Check your connection and try again.' }; });
  }

  // ── icons (fixed markup only) ──────────────────────────────────────────
  function ic(path, size, cls) {
    return '<svg' + (cls ? ' class="' + cls + '"' : '') + ' xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
  }
  var PATH = {
    play: '<path d="M6 3l14 9-14 9z"/>',
    spin: '<path d="M21 12a9 9 0 1 1-6.219-8.56"/>',
    retry: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    ok: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    no: '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>'
  };

  // ── page-level progress (hero card, stepper, stars, wrap-up) ───────────
  var hero = { count: document.querySelector('.lx-pg-count'), fill: document.querySelector('.lx-pg-bar i'), stars: [].slice.call(document.querySelectorAll('.lx-star')) };
  var wrap = document.querySelector('.lx-wrapup');
  var chals = [].slice.call(document.querySelectorAll('.lx-chal'));
  var games = {};
  function isSolved(p) { return solved.indexOf(p.getAttribute('data-id')) !== -1; }

  // ── one practice at a time (Topics 1 to 4): flip through the parts, two steps per part ──
  var topic = document.querySelector('.lx-topic[data-paged]');
  var parts = topic ? chals : [];
  var pager = parts.length > 1 ? topic.querySelector('.lx-pager') : null;
  var isStaff = document.body.getAttribute('data-role') !== 'student';
  var atKey = key + '-reached', reached = 0, current = 0, stepNo = 0;
  function stepsOf(i) { var p = parts[i]; return p ? [].slice.call(p.children).filter(function (c) { return c.getAttribute && c.getAttribute('data-substep'); }) : []; }
  function playOfStep(i, s) { var st = stepsOf(i), h = st.length ? st[s] : parts[i]; return h ? h.querySelector('.lx-play') : null; }
  function partPlays(i) { return chals[i] ? [].slice.call(chals[i].querySelectorAll('.lx-play')) : []; }
  function stepDone(i, s) { var pl = playOfStep(i, s); return isStaff || !pl || isSolved(pl); }
  function partComplete(i) { return partPlays(i).every(function (pl) { return isStaff || isSolved(pl); }); }
  function firstOpenStep(i) { var n = Math.max(1, stepsOf(i).length); for (var s = 0; s < n; s++) { if (!stepDone(i, s)) return s; } return 0; }
  function skipAllowed(pl) {
    if (!pl) return false;
    var rec = S(pl.getAttribute('data-id')), need = parseInt(pl.getAttribute('data-skip') || '5', 10) || 5;
    return rec.fails >= need || rec.misses >= 25;
  }
  if (pager) {
    try { reached = parseInt(localStorage.getItem(atKey) || '0', 10) || 0; } catch (e) { reached = 0; }
    if (isStaff) reached = parts.length - 1;
    reached = Math.min(Math.max(reached, 0), parts.length - 1);
    current = reached;
  }
  function reach(n) {
    n = Math.min(n, parts.length - 1);
    if (n > reached) { reached = n; try { localStorage.setItem(atKey, String(reached)); } catch (e) { /* private mode */ } }
  }
  function partTitle(i) { var h = parts[i] && parts[i].querySelector('.lx-stage h2'); return h ? h.textContent : ''; }

  function refresh() {
    var n = plays.filter(isSolved).length, total = plays.length;
    plays.forEach(function (p) { p.classList.toggle('solved', isSolved(p)); });
    var pills = [].slice.call(document.querySelectorAll('.lx-steps a'));
    chals.forEach(function (sec, i) {
      var all = partPlays(i), complete = all.length > 0 && all.every(isSolved);
      var skipped = all.some(function (pl) { return !isSolved(pl) && S(pl.getAttribute('data-id')).skipped; });
      var icon = sec.querySelector('.lx-stage-ic');
      if (icon && all.length) icon.classList.toggle('done', complete);
      if (pills[i]) { pills[i].classList.toggle('done', complete); pills[i].classList.toggle('skipped', skipped); pills[i].title = skipped ? 'Skipped. Come back and solve it.' : ''; }
    });
    if (hero.count) hero.count.textContent = n + '/' + total;
    if (hero.fill) hero.fill.style.width = Math.round(n / total * 100) + '%';
    hero.stars.forEach(function (s, i) { s.classList.toggle('on', plays[i] && isSolved(plays[i])); });
    if (wrap) {
      var open = plays.filter(function (p) { return !isSolved(p); }), left = open.length;
      wrap.classList.toggle('all', left === 0);
      wrap.querySelector('b').textContent = left === 0 ? "All practice complete! You're ready for the quiz." : left + ' practice' + (left === 1 ? '' : 's') + ' left';
      var skippedOpen = open.filter(function (p) { return S(p.getAttribute('data-id')).skipped; });
      wrap.querySelector('p').textContent = left === 0 ? "Move on to the formative assessment when you're ready."
        : (skippedOpen.length ? 'You skipped ' + skippedOpen.length + '. Come back and solve ' + (skippedOpen.length === 1 ? 'it' : 'them') + ' to finish: ' + skippedOpen[0].getAttribute('data-title') + (skippedOpen.length > 1 ? ' and more' : '') + '.'
          : 'Up next: ' + open[0].getAttribute('data-title') + '.');
      var go = wrap.querySelector('.lx-wrap-go');
      if (left > 0 && pager) {
        if (!go) { go = document.createElement('button'); go.type = 'button'; go.className = 'lx-btn ghost lx-wrap-go'; go.textContent = 'Go there'; wrap.appendChild(go); go.addEventListener('click', function () { jumpToPlay(open0()); }); }
        go.hidden = false;
      } else if (go) go.hidden = true;
    }
    updatePager();
  }
  function open0() { var o = plays.filter(function (p) { return !isSolved(p); }); var sk = o.filter(function (p) { return S(p.getAttribute('data-id')).skipped; }); return (sk[0] || o[0]) || null; }
  function jumpToPlay(pl) {
    if (!pl || !pager) return;
    var sec = pl.closest('.lx-chal'), i = chals.indexOf(sec), st = stepsOf(i), s = 0;
    st.forEach(function (h, k) { if (h.contains(pl)) s = k; });
    goTo(i, s, true);
  }
  function markSolved(p) {
    var id = p.getAttribute('data-id');
    if (solved.indexOf(id) === -1) { solved.push(id); save(); }
    var rec = S(id); rec.skipped = 0; touch(id);
    flushStats(false);
    report(id);          // safe to repeat: the server ignores duplicates
    if (pager) { var i = chals.indexOf(p.closest('.lx-chal')); if (partComplete(i)) reach(i + 1); }
    refresh();
  }

  function updatePager() {
    if (!pager) return;
    var last = parts.length - 1, st = stepsOf(current), nSteps = Math.max(1, st.length);
    stepNo = Math.min(stepNo, nSteps - 1);
    var here = playOfStep(current, stepNo), done = stepDone(current, stepNo);
    var atEnd = current === last && stepNo === nSteps - 1;
    var prev = pager.querySelector('.lx-pg-prev'), next = pager.querySelector('.lx-pg-next');
    var skip = pager.querySelector('.lx-pg-skip'), hint = pager.querySelector('.lx-pg-hint');
    parts.forEach(function (s, i) { s.hidden = i !== current; });
    st.forEach(function (s, i) { s.hidden = i !== stepNo; });
    activeId = here ? here.getAttribute('data-id') : null;
    [].slice.call(topic.querySelectorAll('.lx-steps a')).forEach(function (a, i) {
      a.classList.toggle('cur', i === current);
      a.classList.toggle('locked', i > reached);
    });
    var skippable = !done && !atEnd && !!here && skipAllowed(here);
    prev.hidden = current === 0 && stepNo === 0;
    next.hidden = atEnd;
    skip.hidden = !skippable;
    next.disabled = !done;
    next.classList.toggle('pulse', done && !atEnd);
    var lbl = next.querySelector('.lbl');
    if (lbl) {
      if (stepNo < nSteps - 1) lbl.textContent = st[stepNo + 1].getAttribute('data-substep') === 'round' ? 'Next: Card game' : 'Next';
      else lbl.textContent = playOfStep(current + 1, 0) ? 'Next practice' : 'Next';
    }
    var prefix = nSteps > 1 ? 'Step ' + (stepNo + 1) + ' of ' + nSteps + ' · ' : '';
    var msg;
    if (!done) {
      msg = skippable ? "Stuck? You can skip for now, but you'll need to come back and solve it."
        : (stepNo < nSteps - 1 ? 'Solve it to unlock the card game.' : (current === last ? 'Last one! Solve it to finish this topic.' : 'Solve it to unlock the next part.'));
    } else if (atEnd) msg = '';
    else if (stepNo < nSteps - 1) msg = 'Nice work! Next up: the card game and practice.';
    else msg = 'Nice work! Next up: ' + partTitle(current + 1);
    hint.textContent = msg ? prefix + msg : '';
    if (wrap) wrap.hidden = current !== last;
    var lessonNext = document.querySelector('.lx-lessonnext');
    if (lessonNext) lessonNext.hidden = !isStaff && plays.some(function (pl) { return !isSolved(pl); });
    var g = (st[stepNo] && st[stepNo].getAttribute('data-substep') === 'round') ? games[parts[current].id] : null;
    if (g) g.shown();
  }
  function goTo(i, s, scroll) {
    current = Math.min(Math.max(i, 0), parts.length - 1);
    stepNo = s || 0;
    updatePager();
    if (scroll) { var m = document.querySelector('.lx-main'); if (m) m.scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' }); }
  }
  function advance(fromSkip) {
    var nSteps = Math.max(1, stepsOf(current).length), here = playOfStep(current, stepNo);
    if (fromSkip && here) { var rec = S(here.getAttribute('data-id')); rec.skipped = 1; touch(here.getAttribute('data-id')); flushStats(false); }
    if (stepNo < nSteps - 1) goTo(current, stepNo + 1, true);
    else { reach(current + 1); goTo(current + 1, 0, true); }
    refresh();
  }
  if (pager) {
    stepNo = firstOpenStep(current);
    pager.querySelector('.lx-pg-next').addEventListener('click', function () { advance(false); });
    pager.querySelector('.lx-pg-skip').addEventListener('click', function () { advance(true); });
    pager.querySelector('.lx-pg-prev').addEventListener('click', function () {
      if (stepNo > 0) goTo(current, stepNo - 1, true);
      else if (current > 0) goTo(current - 1, Math.max(1, stepsOf(current - 1).length) - 1, true);
    });
    [].slice.call(topic.querySelectorAll('.lx-steps a')).forEach(function (a, i) {
      a.addEventListener('click', function (e) { e.preventDefault(); if (i <= reached) goTo(i, firstOpenStep(i), true); });
    });
  }

  // ── each editor ────────────────────────────────────────────────────────
  var preload = function () { getPy().catch(function () {}); };
  preload();

  plays.forEach(function (p) {
    var expect = [];
    try {
      expect = JSON.parse(p.getAttribute('data-expect') || '[]').map(function (e) { return (typeof e === 'string' || e.not !== undefined) ? e : new RegExp(e.re, e.flags || ''); });
    } catch (e) { expect = []; }
    var pid = p.getAttribute('data-id');
    var ta = p.querySelector('textarea.lx-src'), sol = p.querySelector('textarea.lx-sol');
    var gut = p.querySelector('.lx-gut'), run = p.querySelector('.lx-run'), outEl = p.querySelector('.lx-out-pre');
    var verdict = p.querySelector('.lx-verdict'), hintBtn = p.querySelector('.lx-hintbtn'), hint = p.querySelector('.lx-hint');
    var st = { ready: false, failed: false, running: false };
    var blanksEl = document.createElement('span');
    blanksEl.className = 'lx-blanks';
    var topRow = p.querySelector('.lx-play-top');
    if (topRow) topRow.appendChild(blanksEl);

    function blanks() {
      var n = (ta.value.match(/___/g) || []).length;
      blanksEl.textContent = n ? n + ' blank' + (n === 1 ? '' : 's') + ' to fill' : '';
    }
    function gutter() {
      var lines = ta.value.split('\n').length, n = Math.max(lines, 10), h = '';
      for (var i = 1; i <= n; i++) h += '<div>' + i + '</div>';
      gut.innerHTML = h;
      ta.rows = n;
      blanks();
    }
    function paintRun() {
      var icon, label;
      if (st.failed) { icon = ic(PATH.retry, 12); label = 'Retry'; }
      else if (!st.ready) { icon = ic(PATH.spin, 12, 'spin'); label = 'Loading Python\u2026'; }
      else if (st.running) { icon = ic(PATH.spin, 12, 'spin'); label = 'Running\u2026'; }
      else { icon = ic(PATH.play, 12, 'fill'); label = 'Run'; }
      run.innerHTML = icon + ' <span>' + label + '</span>';
      run.disabled = (!st.ready && !st.failed) || st.running;
    }
    function show(kind, custom) {
      if (kind === 'idle') { verdict.hidden = true; return; }
      var msg = { pass: ['ok', 'Nailed it! Output matches. +1 star'],
                  fail: ['no', "It runs, but the output isn't what the task asks for yet."],
                  error: ['no', 'Python hit an error. Read the message above and tweak.'] }[kind];
      verdict.className = 'lx-verdict ' + kind;
      verdict.innerHTML = ic(PATH[msg[0]], 16) + '<span></span>';
      verdict.lastChild.textContent = custom || msg[1];
      verdict.hidden = false;
    }
    function setOut(text, isErr) {
      outEl.className = 'lx-out-pre' + (isErr ? ' err' : '');
      outEl.textContent = '';
      if (text) outEl.textContent = text;
      else {
        var s = document.createElement('span');
        s.className = 'lx-out-empty';
        s.textContent = 'Press Run to see what your code prints\u2026';
        outEl.appendChild(s);
      }
    }
    function connect() {
      st.failed = false; paintRun();
      getPy().then(function () { st.ready = true; paintRun(); }, function () {
        st.failed = true; paintRun();
        setOut('Could not load the Python runtime. Check your internet connection, then press Retry.', true);
      });
    }

    function runCode() {
      if (st.failed) { setOut('', false); connect(); return; }
      if (st.running || !st.ready) return;
      if (/___/.test(ta.value)) {      // blanks left: not a real attempt, so it does not count as a wrong run
        setOut('', false);
        show('fail', p.closest('[data-substep="round"]') ? 'Type your answer over every blank (___) first. Your collected items can help.' : 'Replace every blank (___) with your answer first.');
        return;
      }
      st.running = true; paintRun();
      var rec = S(pid); rec.runs++; touch(pid);
      runPy(ta.value).then(function (res) {
        setOut(res.output || '(no output)', !res.ok);
        var loadProblem = !res.ok && /Could not load the Python runtime/.test(res.output || '');
        if (!res.ok) { show('error'); if (!loadProblem) rec.fails++; }
        else {
          var lines = res.output.split('\n').map(function (l) { return l.replace(/\s+$/, ''); });
          var pass = expect.every(function (e) {
            if (typeof e === 'string') return lines.indexOf(e) !== -1;
            if (e && e.not !== undefined) return res.output.indexOf(e.not) === -1;
            return e.test(res.output);
          });
          show(pass ? 'pass' : 'fail');
          if (pass) markSolved(p); else rec.fails++;
        }
        touch(pid);
        st.running = false; paintRun();
        updatePager();
      });
    }

    ta.addEventListener('input', gutter);
    ta.addEventListener('keydown', function (e) {
      var v = ta.value, s = ta.selectionStart, end = ta.selectionEnd, pos;
      if (e.key === 'Tab') {
        e.preventDefault();
        ta.value = v.slice(0, s) + '    ' + v.slice(end);
        ta.setSelectionRange(s + 4, s + 4); gutter();
      } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault(); runCode();
      } else if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        var ls = v.lastIndexOf('\n', s - 1) + 1, before = v.slice(ls, s);
        var indent = (before.match(/^ */) || [''])[0] + (/:\s*$/.test(before) ? '    ' : '');
        ta.value = v.slice(0, s) + '\n' + indent + v.slice(end);
        pos = s + 1 + indent.length;
        ta.setSelectionRange(pos, pos); gutter();
      }
    });
    run.addEventListener('click', runCode);
    p.querySelector('.lx-ed-reset').addEventListener('click', function () { ta.value = ta.defaultValue; gutter(); setOut('', false); show('idle'); });
    var solBtn = p.querySelector('.lx-solbtn');
    if (solBtn && sol) solBtn.addEventListener('click', function () { ta.value = sol.defaultValue; gutter(); setOut('', false); show('idle'); });
    if (hintBtn && hint) hintBtn.addEventListener('click', function () {
      hint.hidden = !hint.hidden;
      hintBtn.querySelector('span').textContent = hint.hidden ? 'Need a hint?' : 'Hide hint';
    });

    gutter(); paintRun();
    // reuse the already-started runtime; flip to ready when it is
    getPy().then(function () { st.ready = true; paintRun(); }, function () {
      st.failed = true; paintRun();
      setOut('Could not load the Python runtime. Check your internet connection, then press Retry.', true);
    });
  });

  // ── the card game: flip two cards, a clue and its answer make a pair, answers go to the tray ──
  function shuffle(a) {
    for (var i = a.length - 1; i > 0; i--) {
      var r = window.crypto && crypto.getRandomValues ? crypto.getRandomValues(new Uint32Array(1))[0] / 4294967296 : Math.random();
      var k = Math.floor(r * (i + 1)), t = a[i]; a[i] = a[k]; a[k] = t;
    }
    return a;
  }
  function initGame(card) {
    var gid = card.getAttribute('data-gid'), pairs = [];
    try { pairs = JSON.parse(card.getAttribute('data-pairs') || '[]'); } catch (e) { pairs = []; }
    if (!pairs.length) return;
    var mode = card.getAttribute('data-mode') || 'match';   // pages saved before the matching-words style have no data-mode
    if (mode === 'match') {
      // matching words: the same word on two cards. Pages saved before this style existed stored clue/answer pairs, so use their answers.
      pairs = pairs.map(function (x) { return [x[1], x[1]]; });
      var helpEl = card.querySelector('.lx-game-help'), trayHead = card.querySelector('.lx-tray small');
      if (helpEl) helpEl.textContent = 'Flip two cards at a time and find the matching words. Each match is collected below. Then type the words you need into the blanks in the Practice.';
      if (trayHead) { trayHead.textContent = 'Collected items '; var em = document.createElement('em'); em.textContent = '(type what you need into the Practice)'; trayHead.appendChild(em); }
    }
    var sec = card.closest('.lx-chal');
    var board = card.querySelector('.lx-deck'), tray = card.querySelector('.lx-chips'), status = card.querySelector('.lx-game-status');
    var gkey = 'itikode-game-' + uid + '-' + topicKey + '-' + gid, n = pairs.length;
    var saved = null;
    try { saved = JSON.parse(localStorage.getItem(gkey) || 'null'); } catch (e) { saved = null; }
    var order = saved && saved.order && saved.order.length === n * 2 ? saved.order : shuffle(Array.apply(null, Array(n * 2)).map(function (_, i) { return i; }));
    var matched = saved && saved.matched ? saved.matched.slice() : [];
    var peeked = !!(saved && saved.peeked);
    function persist() { try { localStorage.setItem(gkey, JSON.stringify({ order: order, matched: matched, peeked: peeked })); } catch (e) { /* private mode */ } }

    var tiles = [], first = null, lock = false;
    function labelOf(t) { return mode === 'match' ? 'word' : (t.side ? 'answer' : 'clue'); }
    function setLabel(t) { t.el.setAttribute('aria-label', 'Card ' + (t.pos + 1) + (t.up ? ': ' + labelOf(t) + ' ' + t.text + (t.done ? ', matched' : '') : ', face down')); }
    function up(t, on) { t.up = on; t.el.classList.toggle('up', on); t.el.setAttribute('aria-pressed', on ? 'true' : 'false'); setLabel(t); }
    order.forEach(function (id, pos) {
      var pair = id >> 1, side = id & 1, text = pairs[pair][side];
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'lx-tile ' + (mode === 'match' || side ? 'ans' : 'clue');
      var inner = document.createElement('span'); inner.className = 'lx-tile-in';
      var cover = document.createElement('span'); cover.className = 'lx-face cover'; cover.setAttribute('aria-hidden', 'true'); cover.textContent = '?';
      var face = document.createElement('span'); face.className = 'lx-face content'; face.setAttribute('aria-hidden', 'true'); face.textContent = text;
      inner.appendChild(cover); inner.appendChild(face); b.appendChild(inner);
      var t = { el: b, pair: pair, side: side, text: text, pos: pos, up: false, done: false };
      b.addEventListener('click', function () { flip(t); });
      board.appendChild(b); tiles.push(t); setLabel(t);
    });

    function addChip(pair) {
      var empty = tray.querySelector('.lx-chips-empty'); if (empty) empty.remove();
      var chip = document.createElement('span');
      chip.className = 'lx-chip pop'; chip.textContent = pairs[pair][1];
      if (mode === 'clue') chip.title = pairs[pair][0];
      tray.appendChild(chip);
    }
    function paint() {
      status.textContent = matched.length + '/' + n + ' pairs';
      card.classList.toggle('complete', matched.length === n);
      if (matched.length === n) status.textContent = 'All ' + n + ' pairs found';
    }
    function setMatched(pair, silent) {
      tiles.forEach(function (t) { if (t.pair === pair) { t.done = true; t.el.classList.add('matched'); up(t, true); } });
      if (matched.indexOf(pair) === -1) matched.push(pair);
      addChip(pair); paint();
      if (!silent) { persist(); }
    }
    function flip(t) {
      if (lock || t.done || t.up) return;
      up(t, true);
      if (!first) { first = t; return; }
      var a = first, b = t; first = null;
      var rec = S(gid); rec.flips++; touch(gid);
      if (a.pair === b.pair) {
        setMatched(a.pair, false);
        status.setAttribute('aria-live', 'polite');
        if (matched.length === n) { flushStats(false); updatePager(); }
        refresh();
      } else {
        rec.misses++; lock = true;
        setTimeout(function () { up(a, false); up(b, false); lock = false; updatePager(); }, REDUCED ? 600 : 900);
      }
    }
    matched.forEach(function (pair) { setMatched(pair, true); });
    paint();
    games[sec.id] = {
      // called when the card-game step is shown: a short peek at all the cards the first time
      shown: function () {
        if (peeked || matched.length || REDUCED) { peeked = true; return; }
        peeked = true; persist(); lock = true;
        tiles.forEach(function (t) { up(t, true); });
        setTimeout(function () { tiles.forEach(function (t) { if (!t.done) up(t, false); }); lock = false; }, 2600);
      }
    };
  }
  [].slice.call(document.querySelectorAll('.lx-gamecard')).forEach(initGame);

  refresh();
})();
