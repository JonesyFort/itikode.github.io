/* ITiKODE lesson editor: a form-based block editor (no HTML). It edits one JSON "blocks" object and posts it
   in the hidden field #blocks_json; the server validates it and compiles it into the designed lesson page. */
(function () {
  'use strict';
  var dataEl = document.getElementById('lb-data'), root = document.getElementById('lb-root'), hidden = document.getElementById('blocks_json');
  if (!dataEl || !root || !hidden) return;
  var cfg = JSON.parse(dataEl.textContent);
  var S = cfg.blocks, ACC = cfg.accents, EMOJI = cfg.emojis;
  var COLOR_NAMES = ['Green', 'Brown', 'Purple', 'Orange', 'Blue', 'Red'];
  var KINDS = [['learn', 'Explanation + code practice'], ['cards', 'Card grid'], ['outcomes', 'Outcomes + checklist'], ['video', 'Video + reflection']];
  var open = {};            // part key -> expanded?

  /* ── tiny DOM helpers ── */
  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k.slice(0, 2) === 'on') n.addEventListener(k.slice(2), v);
      else if (v !== false && v != null) n.setAttribute(k, v === true ? '' : v);
    });
    (kids || []).forEach(function (c) { if (c) n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }
  function btn(label, fn, aria, cls) {
    var b = el('button', { type: 'button', class: 'btn btn-sm' + (cls ? ' ' + cls : ''), 'aria-label': aria || label, text: label });
    b.addEventListener('click', fn);
    return b;
  }
  function field(label, control, hint) {
    return el('div', { class: 'field' }, [el('label', { class: 'label', text: label }), control, hint ? el('p', { class: 'hint', text: hint }) : null]);
  }
  function text(o, k, ph, max, list) {
    var i = el('input', { class: 'input', type: 'text', placeholder: ph || '', maxlength: max || 200 });
    if (list) i.setAttribute('list', list);
    i.value = o[k] || '';
    i.addEventListener('input', function () { o[k] = i.value; });
    return i;
  }
  function area(o, k, ph, rows, code) {
    var t = el('textarea', { class: 'textarea' + (code ? ' lb-code' : ''), placeholder: ph || '', rows: rows || 3, spellcheck: code ? 'false' : 'true' });
    if (code) t.setAttribute('wrap', 'off');
    t.value = o[k] || '';
    t.addEventListener('input', function () { o[k] = t.value; });
    return t;
  }
  function check(o, k, label, then) {
    var c = el('input', { type: 'checkbox' });
    c.checked = !!o[k];
    c.addEventListener('change', function () { o[k] = c.checked; if (then) then(); });
    return el('label', { class: 'check' }, [c, ' ' + label]);
  }
  function select(o, k, opts, then) {
    var s = el('select', { class: 'select' });
    opts.forEach(function (op) { var x = el('option', { value: op[0], text: op[1] }); if (String(o[k]) === String(op[0])) x.selected = true; s.appendChild(x); });
    s.addEventListener('change', function () { o[k] = s.value; if (then) then(); });
    return s;
  }
  function swatches(o, k, allowNone) {
    var box = el('div', { class: 'lb-sw', role: 'radiogroup' });
    function paint() { [].slice.call(box.children).forEach(function (b) { b.className = b.className.replace(/ ?on/g, ''); if ((o[k] || '') === b.getAttribute('data-v')) b.className += ' on'; }); }
    if (allowNone) { var none = el('button', { type: 'button', class: 'none', 'data-v': '', title: 'Same as the lesson colour', text: 'Auto' }); none.addEventListener('click', function () { o[k] = ''; paint(); }); box.appendChild(none); }
    ACC.forEach(function (c, i) {
      var b = el('button', { type: 'button', 'data-v': c, title: COLOR_NAMES[i] || c, 'aria-label': COLOR_NAMES[i] || c });
      b.style.background = c;
      b.addEventListener('click', function () { o[k] = c; paint(); });
      box.appendChild(b);
    });
    paint();
    return box;
  }
  function move(arr, i, d) { var j = i + d; if (j < 0 || j >= arr.length) return; var t = arr[i]; arr[i] = arr[j]; arr[j] = t; }

  /* string list: ['a','b'] */
  function strings(o, k, max, ph, again) {
    var box = el('div', { class: 'lrows' });
    o[k].forEach(function (s, i) {
      var inp = el('input', { class: 'input', type: 'text', maxlength: 300, placeholder: ph });
      inp.value = s;
      inp.addEventListener('input', function () { o[k][i] = inp.value; });
      box.appendChild(el('div', { class: 'lrow' }, [inp, btn('×', function () { o[k].splice(i, 1); again(); }, 'Remove')]));
    });
    var wrap = el('div', {}, [box]);
    if (o[k].length < max) wrap.appendChild(btn('+ Add', function () { o[k].push(''); again(); }));
    return wrap;
  }
  /* list of records, each drawn by renderItem(item, body, again) */
  function items(o, k, max, blank, renderItem, again, addLabel) {
    var wrap = el('div');
    o[k].forEach(function (it, i) {
      var body = el('div', { class: 'body' });
      renderItem(it, body, again);
      var mv = el('div', { class: 'lb-mv' }, [
        btn('↑', function () { move(o[k], i, -1); again(); }, 'Move up'), btn('↓', function () { move(o[k], i, 1); again(); }, 'Move down'),
        btn('×', function () { o[k].splice(i, 1); again(); }, 'Remove')]);
      wrap.appendChild(el('div', { class: 'lb-item' }, [body, mv]));
    });
    if (o[k].length < max) wrap.appendChild(btn(addLabel || '+ Add', function () { o[k].push(blank()); again(); }, '', 'btn-lime'));
    return wrap;
  }
  function row(kids) { return el('div', { class: 'row' }, kids); }

  /* ── data factories ── */
  function newKey() { return 'p' + Math.random().toString(16).slice(2, 8); }
  function newPart(kind) {
    var p = { key: newKey(), kind: kind, emoji: '📘', title: '', accent: '', skip_after: 5, round: blankRound(),
      concept: '', facts: [], facts_mono: false, points: [], analogy: '', example: '', footnote: '',
      sandbox: { on: false, task: '', starter: '', solution: '', hint: '', expected: [] },
      cols: 3, cards: [], tiles: [], out_tag: '', intro: '', outcomes: [], check_tag: '', check_task: '', checks: [],
      video_title: '', video_len: '', video_img: '', quote: '', cite: '', prompt: '', placeholder: '' };
    if (kind === 'cards') { p.emoji = '🧩'; p.cards = [blankCard()]; }
    if (kind === 'outcomes') { p.emoji = '🎯'; p.out_tag = 'Learning outcomes'; p.outcomes = [{ text: '', tag: '' }]; p.check_tag = 'Get ready'; }
    if (kind === 'video') { p.emoji = '🎬'; }
    return p;
  }
  function blankRound() { return { on: false, task: '', starter: '', solution: '', hint: '', expected: [], mode: 'match', tiles: [], pairs: [] }; }
  function blankCard() { return { emoji: '💡', eyebrow: '', title: '', text: '', color: ACC[0], tag: '', meta: '', note: '' }; }

  /* ── part bodies ── */
  function sandboxEditor(sb, again) {
    var box = el('div', { class: 'sandbox' });
    box.appendChild(field('What should the student do?', area(sb, 'task', 'Finish the recipe! Add a third step that tells us to bake the cake for 30 minutes.', 2)));
    box.appendChild(field('Starter code', area(sb, 'starter', 'print("Step 1")', 6, true), 'What the student sees in the editor. Leave blanks like ___ for them to fill in.'));
    box.appendChild(field('The program is correct when its output…', items(sb, 'expected', 10, function () { return { t: 'line', v: '' }; }, function (it, body) {
      body.appendChild(row([select(it, 't', [['line', 'has a line exactly'], ['has', 'contains the text'], ['not', 'does NOT contain']]), text(it, 'v', 'Step 3: Bake for 30 minutes', 200)]));
    }, again, '+ Add a check'), 'Add one or more checks. The student earns a star when every check passes.'));
    box.appendChild(field('Hint (optional)', text(sb, 'hint', 'Think about what the third print line should say.', 300)));
    box.appendChild(field('Solution (optional)', area(sb, 'solution', 'print("Step 3: Bake for 30 minutes")', 5, true), 'Shown only when the student presses "Show solution".'));
    return box;
  }
  function blanksIn(s) { return ((s || '').match(/___/g) || []).length; }
  function tidy(s) { return String(s || '').replace(/^\n+/, '').replace(/\s+$/, ''); }
  /* the answers hiding behind each ___ in the starter, found by lining it up with the solution (null = they don't line up) */
  function blankAnswers(starter, solution) {
    var segs = tidy(starter).split('___');
    if (segs.length < 2) return [];
    var esc = function (x) { return x.replace(/[.*+?^${}()|[\]\\\/]/g, '\\$&'); };
    var m = new RegExp('^' + segs.map(esc).join('([\\s\\S]*?)') + '$').exec(tidy(solution));
    return m ? m.slice(1).map(function (x) { return x.trim(); }) : null;
  }
  function roundEditor(p, again) {
    var r = p.round, box = el('div', { class: 'sandbox' });
    if (r.mode !== 'clue') r.mode = 'match';
    if (!r.tiles) r.tiles = [];
    box.appendChild(el('p', { class: 'hint', text: 'Step 2 of this part: students flip cards to collect words, then TYPE the right words into the blanks (___) of the practice code. Write the code first, then set up the cards.' }));

    box.appendChild(field('What should the student do?', area(r, 'task', 'Fill the blanks so each language prints its style.', 2)));
    box.appendChild(field('Starter code with blanks', area(r, 'starter', 'print(___)', 6, true), 'Write ___ (three underscores) wherever the student has to type an answer.'));
    box.appendChild(field('Full solution (no blanks)', area(r, 'solution', 'print("Procedural")', 5, true), 'Never shown to students. It must be the starter with every ___ filled in, so the cards can be checked against it.'));
    box.appendChild(field('The program is correct when its output…', items(r, 'expected', 10, function () { return { t: 'line', v: '' }; }, function (it, body) {
      body.appendChild(row([select(it, 't', [['line', 'has a line exactly'], ['has', 'contains the text'], ['not', 'does NOT contain']]), text(it, 'v', 'C is Procedural', 200)]));
    }, again, '+ Add a check')));
    box.appendChild(field('Hint (optional)', text(r, 'hint', 'Which style is a recipe of steps?', 300)));

    var game = el('div', { class: 'lb-gamebox' });
    game.appendChild(el('p', { class: 'card-h', style: 'margin:6px 0 8px', text: 'Card game' }));
    game.appendChild(field('Card style', select(r, 'mode', [['match', 'Matching words (the same word on two cards)'], ['clue', 'Clue and answer (a clue card matches an answer card)']], again)));
    var status = el('p', { class: 'hint lb-status' });
    function cardsNow() { return r.mode === 'clue' ? r.pairs.map(function (x) { return (x.answer || '').trim(); }).filter(Boolean) : r.tiles.map(function (x) { return (x || '').trim(); }).filter(Boolean); }
    function check() {
      var need = blankAnswers(r.starter, r.solution), have = cardsNow(), count = have.length;
      var msg = count + ' ' + (r.mode === 'clue' ? 'pair' : 'card word') + (count === 1 ? '' : 's') + ' · ' + blanksIn(r.starter) + ' blank' + (blanksIn(r.starter) === 1 ? '' : 's') + ' in the code. ';
      status.className = 'hint lb-status';
      if (need === null) msg += 'Tip: make the solution match the starter, with each ___ filled in, and the cards can be checked against your code.';
      else {
        var missing = need.filter(function (x, i) { return x && need.indexOf(x) === i && have.indexOf(x) === -1; });
        if (missing.length) { msg += 'The cards are missing words the blanks need: ' + missing.join(', ') + '.'; status.className += ' bad'; }
        else if (need.length) { msg += 'The cards cover every blank. ✓'; status.className += ' ok'; }
      }
      status.textContent = msg;
    }
    if (r.mode === 'clue') {
      game.appendChild(field('Card pairs (a clue and its answer)', items(r, 'pairs', 8, function () { return { clue: '', answer: '' }; }, function (it, body) {
        body.appendChild(row([text(it, 'clue', 'Clue, e.g. Recipe of steps', 60), text(it, 'answer', 'Answer to type, e.g. Procedural', 40)]));
      }, again, '+ Add a pair'), 'At least 4 pairs, at most 8 (8 make a 4 x 4 board). Every answer must be different and written exactly as it appears in the code.'));
    } else {
      game.appendChild(field('Card words (each word becomes two matching cards)', strings(r, 'tiles', 8, 'e.g. print', again),
        'At least 4 words, at most 8 (8 words make a 4 x 4 board). Include the words the blanks need, plus a few look-alike or related words as decoys.'));
      game.appendChild(btn('Fill the cards from my code\'s blanks', function () {
        var need = blankAnswers(r.starter, r.solution);
        if (need === null) { status.className = 'hint lb-status bad'; status.textContent = 'I could not line the solution up with the starter. Make the solution identical to the starter with each ___ filled in.'; return; }
        need.forEach(function (w) { if (w && r.tiles.indexOf(w) === -1 && r.tiles.length < 8) r.tiles.push(w); });
        again();
      }, 'Fill the cards from the blanks', 'btn-lime'));
    }
    game.appendChild(status);
    box.appendChild(game);
    box.addEventListener('input', check);
    check();
    return box;
  }
  function learnBody(p, again) {
    var b = el('div', { class: 'lb-body' });
    b.appendChild(field('Explanation', area(p, 'concept', 'A **class** is a blueprint used to create objects.', 4), 'Use **bold**, *italic* and `code`. A blank line starts a new paragraph.'));
    b.appendChild(field('Fact chips (optional)', items(p, 'facts', 6, function () { return { k: '', v: '' }; }, function (it, body) {
      body.appendChild(row([text(it, 'k', 'Keyword', 40), text(it, 'v', 'class', 100)]));
    }, again, '+ Add a chip'), 'Small label and value boxes under the explanation.'));
    if (p.facts.length) b.appendChild(check(p, 'facts_mono', 'Show chip values in a code font'));
    b.appendChild(field('Bullet points (optional)', strings(p, 'points', 12, 'A key point', again)));
    b.appendChild(field('Analogy or tip (optional)', area(p, 'analogy', 'A house blueprint isn\'t a house. It\'s the plan you can build many houses from.', 2)));
    b.appendChild(field('Example code (optional)', area(p, 'example', 'class Student:\n    pass', 5, true), 'Shown with colours under the explanation.'));
    b.appendChild(field('Small note at the bottom (optional)', text(p, 'footnote', 'Don\'t worry about the syntax yet.', 300)));
    b.appendChild(field('Panel colour', swatches(p, 'accent', true)));
    var sbHead = el('div', { class: 'sbhead' }, [check(p.sandbox, 'on', 'Add a "Try it yourself" code sandbox (students write and run Python)', again)]);
    b.appendChild(sbHead);
    if (p.sandbox.on) b.appendChild(sandboxEditor(p.sandbox, again));
    b.appendChild(el('div', { class: 'sbhead' }, [check(p.round, 'on', 'Add a card-game practice round (a second step: flip cards, then type answers into the blanks)', again)]));
    if (p.round.on) {
      b.appendChild(roundEditor(p, again));
      b.appendChild(field('Allow skipping after this many wrong runs', select(p, 'skip_after', [[3, '3 wrong runs'], [5, '5 wrong runs'], [7, '7 wrong runs'], [10, '10 wrong runs']], null), 'A student who is stuck gets a "Skip for now" button after this many wrong runs. A skipped practice stays unsolved, so they must come back and solve it.'));
    }
    return b;
  }
  function cardsBody(p, again) {
    var b = el('div', { class: 'lb-body' });
    b.appendChild(field('Cards per row', select(p, 'cols', [[3, '3 cards per row'], [4, '4 cards per row']], again)));
    b.appendChild(field('Cards', items(p, 'cards', 8, blankCard, function (c, body) {
      body.appendChild(row([text(c, 'emoji', '💡', 8, 'lb-emoji'), text(c, 'eyebrow', 'Small label (optional), e.g. Topic 1', 40), text(c, 'title', 'Card title', 80)]));
      body.appendChild(area(c, 'text', 'A sentence about this card.', 2));
      body.appendChild(row([text(c, 'tag', 'Tag (optional), e.g. Foundation', 40), text(c, 'meta', 'Time (optional), e.g. ~30 min', 30), text(c, 'note', 'Footer note (optional)', 80)]));
      body.appendChild(el('div', { class: 'lb-colorrow' }, [el('span', { class: 'hint', text: 'Card colour' }), swatches(c, 'color', false)]));
    }, again, '+ Add a card')));
    b.appendChild(field('Icon tiles under the cards (optional)', items(p, 'tiles', 8, function () { return { emoji: '🌐', title: '', sub: '' }; }, function (t, body) {
      body.appendChild(row([text(t, 'emoji', '🌐', 8, 'lb-emoji'), text(t, 'title', 'Web Development', 60), text(t, 'sub', 'Django, Flask', 80)]));
    }, again, '+ Add a tile')));
    return b;
  }
  function outcomesBody(p, again) {
    var b = el('div', { class: 'lb-body' });
    b.appendChild(field('Left panel label', text(p, 'out_tag', 'Learning outcomes', 40)));
    b.appendChild(field('Lead-in sentence', text(p, 'intro', 'By the end of this module, you will be able to:', 300)));
    b.appendChild(field('Outcomes', items(p, 'outcomes', 8, function () { return { text: '', tag: '' }; }, function (o, body) {
      body.appendChild(row([text(o, 'text', 'Explain the basic OOP concepts…', 300), text(o, 'tag', 'Tag (optional), e.g. T1', 30)]));
    }, again, '+ Add an outcome')));
    b.appendChild(field('Checklist panel label (optional)', text(p, 'check_tag', 'Get ready', 30), 'The right-hand panel with tick boxes. Leave the checklist empty to hide it.'));
    b.appendChild(field('Checklist instruction', text(p, 'check_task', 'Tick each item off to get set up.', 200)));
    b.appendChild(field('Checklist items', strings(p, 'checks', 8, 'A device with internet', again)));
    b.appendChild(field('Panel colour', swatches(p, 'accent', true)));
    return b;
  }
  function videoBody(p) {
    var b = el('div', { class: 'lb-body' });
    b.appendChild(field('Video title', text(p, 'video_title', 'Watch: Why Learn OOP in Python?', 120)));
    b.appendChild(field('Length (optional)', text(p, 'video_len', '4 min 18 sec', 30)));
    b.appendChild(field('Background picture link (optional)', text(p, 'video_img', 'https://…', 400), 'Must start with https://. The play button is a placeholder; no video is embedded.'));
    b.appendChild(field('Quote (optional)', area(p, 'quote', 'Object-oriented programming is more than a technique…', 3)));
    b.appendChild(field('Quote author (optional)', text(p, 'cite', 'Adapted from Grady Booch', 80)));
    b.appendChild(field('Question for students', text(p, 'prompt', 'Name an app you use daily. What objects could it contain?', 200), 'Students type their thoughts in a notes box (not saved or graded).'));
    b.appendChild(field('Notes box placeholder', text(p, 'placeholder', 'e.g. Banking app → Account, Transaction, User…', 120)));
    return b;
  }

  /* ── one part card ── */
  function buildPart(p, i) {
    var card = el('div', { class: 'pcard', 'data-key': p.key });
    var isOpen = !!open[p.key];
    function again() { var fresh = buildPart(p, i); card.replaceWith(fresh); }
    var head = el('div', { class: 'phead' }, [
      el('b', { class: 'pnum', text: 'Part ' + (i + 1) }),
      (function () { var e = text(p, 'emoji', '📘', 8, 'lb-emoji'); e.className += ' pemoji'; e.setAttribute('aria-label', 'Emoji'); return e; })(),
      (function () { var t = text(p, 'title', 'Part title', 120); t.style.flex = '1'; t.style.minWidth = '160px'; t.setAttribute('aria-label', 'Part title'); return t; })(),
      select(p, 'kind', KINDS, again),
      btn('↑', function () { move(S.parts, i, -1); renderParts(); }, 'Move part up'),
      btn('↓', function () { move(S.parts, i, 1); renderParts(); }, 'Move part down'),
      btn(isOpen ? 'Collapse' : 'Edit', function () { open[p.key] = !isOpen; again(); }, isOpen ? 'Collapse part' : 'Expand part', 'btn-lime'),
      btn('Delete', function () { if (S.parts.length > 1 ? confirm('Delete this whole part?') : (alert('A lesson needs at least one part.'), false)) { S.parts.splice(i, 1); renderParts(); } }, 'Delete part', 'btn-danger')
    ]);
    card.appendChild(head);
    if (isOpen) card.appendChild(p.kind === 'cards' ? cardsBody(p, again) : p.kind === 'outcomes' ? outcomesBody(p, again) : p.kind === 'video' ? videoBody(p) : learnBody(p, again));
    return card;
  }

  /* ── page ── */
  var partsBox = el('div', { id: 'lb-parts' });
  function renderParts() {
    partsBox.textContent = '';
    S.parts.forEach(function (p, i) { partsBox.appendChild(buildPart(p, i)); });
  }
  function asideEditor() {
    var d = el('details', { class: 'lb-aside' }, [el('summary', { text: 'Box at the right of the lesson banner (optional)' })]);
    if (S.aside.title || S.aside.rows.length) d.open = true;
    function draw() {
      [].slice.call(d.querySelectorAll('.lb-asidebody')).forEach(function (x) { x.remove(); });
      var body = el('div', { class: 'lb-asidebody' }, [
        field('Box heading', text(S.aside, 'title', 'In this section', 60), 'Leave everything empty to show the automatic code-practice progress instead.'),
        field('Lines', items(S.aside, 'rows', 6, function () { return { l: '', v: '' }; }, function (r, b) {
          b.appendChild(row([text(r, 'l', 'Label (optional), e.g. Level', 30), text(r, 'v', 'Beginner', 80)]));
        }, draw, '+ Add a line'), 'With labels the box shows a small table; without labels it shows a list.')]);
      d.appendChild(body);
    }
    draw();
    return d;
  }
  var top = el('div', { class: 'card', style: 'margin-bottom:12px' }, [
    el('p', { class: 'card-h', text: 'Lesson banner' }),
    el('div', { class: 'grid2' }, [
      field('Subtitle', text(S, 'subtitle', 'One friendly line under the title.', 300)),
      field('Label above the title (optional)', text(S, 'kicker', 'Lesson', 40), 'Shown with the time, e.g. "Motivation · ~5 mins".')]),
    field('Lesson colour', swatches(S, 'accent', false), 'Tints the banner and the "Try it yourself" panels.'),
    asideEditor()
  ]);
  var addSel = el('select', { class: 'select', 'aria-label': 'Layout for the new part', style: 'max-width:260px' });
  KINDS.forEach(function (k) { addSel.appendChild(el('option', { value: k[0], text: k[1] })); });
  function addPart() {
    var p = newPart(addSel.value);
    S.parts.push(p); open[p.key] = true; renderParts();
    var c = partsBox.lastChild; if (c) { c.scrollIntoView({ behavior: 'smooth', block: 'center' }); var t = c.querySelector('.pemoji'); if (t && t.nextSibling) t.nextSibling.focus(); }
  }
  var addBar = el('div', { class: 'lb-addbar' }, [el('span', { class: 'hint', text: 'Add a part:' }), addSel, btn('+ Add part', addPart, 'Add part', 'btn-lime')]);
  var dl = el('datalist', { id: 'lb-emoji' }); EMOJI.forEach(function (e) { dl.appendChild(el('option', { value: e })); });

  root.appendChild(top);
  root.appendChild(el('div', { class: 'card', style: 'margin-bottom:10px' }, [
    el('p', { class: 'card-h', style: 'margin:0', text: 'Lesson parts' }),
    el('p', { class: 'hint', style: 'margin:2px 0 8px', text: 'Each part becomes a section of the lesson. Choose a layout for each part, then click Edit to fill it in. Use the arrows to reorder.' }),
    addBar]));
  root.appendChild(partsBox);
  root.appendChild(dl);

  // first part open when there are only a few
  if (S.parts.length <= 2) S.parts.forEach(function (p) { open[p.key] = true; });
  renderParts();

  var form = document.getElementById('sec-form');
  if (form) form.addEventListener('submit', function () {
    S.parts.forEach(function (p) { p.skip_after = parseInt(p.skip_after, 10) || 5; p.cols = parseInt(p.cols, 10) || 3; });
    hidden.value = JSON.stringify(S);
  });
  window.lbState = S;       // used by tests
})();
