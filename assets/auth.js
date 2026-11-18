/* Small helpers shared by the auth + account pages. No dependencies. */
(function () {
  // show / hide password
  document.querySelectorAll('[data-eye]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-eye'));
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.setAttribute('title', show ? 'Hide password' : 'Show password');
      btn.setAttribute('aria-pressed', String(show));
      btn.querySelector('.i-show').style.display = show ? 'none' : '';
      btn.querySelector('.i-hide').style.display = show ? '' : 'none';
    });
  });

  // caps lock warning
  document.querySelectorAll('input[type=password]').forEach(function (input) {
    var warn = input.closest('.field') && input.closest('.field').querySelector('.caps');
    if (!warn) return;
    function check(e) { warn.style.display = e.getModifierState && e.getModifierState('CapsLock') ? 'block' : 'none'; }
    input.addEventListener('keyup', check); input.addEventListener('keydown', check);
    input.addEventListener('blur', function () { warn.style.display = 'none'; });
  });

  // password strength meter + rule checklist
  var pw = document.querySelector('[data-strength]');
  if (pw) {
    var min = parseInt(pw.getAttribute('data-min') || '8', 10);
    var bar = document.querySelector('.meter>i'), label = document.getElementById('strength-label');
    var rules = {
      len:   function (v) { return v.length >= min; },
      case:  function (v) { return /[a-z]/.test(v) && /[A-Z]/.test(v); },
      num:   function (v) { return /\d/.test(v); },
      sym:   function (v) { return /[^a-zA-Z0-9]/.test(v); }
    };
    pw.addEventListener('input', function () {
      var v = pw.value, score = 0;
      Object.keys(rules).forEach(function (k) {
        var ok = rules[k](v), li = document.querySelector('[data-rule="' + k + '"]');
        if (li) li.className = ok ? 'ok' : '';
        if (ok) score++;
      });
      if (v.length >= min + 6) score++;
      var pct = v ? Math.min(100, score * 20) : 0;
      var colors = ['#D9694F', '#D9694F', '#E0A93B', '#8DBF5E', '#55883B', '#2D4A1C'];
      var names = ['', 'Too weak', 'Weak', 'Okay', 'Strong', 'Excellent'];
      if (bar) { bar.style.width = pct + '%'; bar.style.background = colors[score]; }
      if (label) label.textContent = v ? names[score] : '';
    });
  }

  // confirm-password match hint
  var c1 = document.querySelector('[data-strength]'), c2 = document.getElementById('confirm');
  if (c1 && c2) {
    var m = document.getElementById('match');
    function chk() { if (!m) return; if (!c2.value) { m.textContent = ''; return; }
      var ok = c1.value === c2.value; m.textContent = ok ? 'Passwords match' : 'Passwords do not match';
      m.style.color = ok ? '#55883B' : '#B3402A'; }
    c1.addEventListener('input', chk); c2.addEventListener('input', chk);
  }

  // 6-digit one-time-code boxes
  var otp = document.querySelector('.otp');
  if (otp) {
    var boxes = otp.querySelectorAll('input'), hidden = document.getElementById('code');
    function sync() { hidden.value = Array.prototype.map.call(boxes, function (b) { return b.value; }).join(''); }
    boxes.forEach(function (b, i) {
      b.addEventListener('input', function () { b.value = b.value.replace(/\D/g, '').slice(-1); sync(); if (b.value && boxes[i + 1]) boxes[i + 1].focus(); if (hidden.value.length === 6) otp.closest('form').requestSubmit(); });
      b.addEventListener('keydown', function (e) { if (e.key === 'Backspace' && !b.value && boxes[i - 1]) boxes[i - 1].focus(); });
      b.addEventListener('paste', function (e) {
        var t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6); if (!t) return; e.preventDefault();
        t.split('').forEach(function (ch, j) { boxes[j].value = ch; }); sync(); boxes[Math.min(t.length, 5)].focus();
        if (t.length === 6) otp.closest('form').requestSubmit();
      });
    });
    boxes[0].focus();
  }

  // submit buttons: prevent double submit
  document.querySelectorAll('form[data-once]').forEach(function (f) {
    f.addEventListener('submit', function () {
      var b = f.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.dataset.label = b.textContent; b.textContent = 'Please wait…'; }
    });
  });

  // lockout countdown
  var cd = document.getElementById('countdown');
  if (cd) {
    var left = parseInt(cd.getAttribute('data-seconds'), 10);
    var t = setInterval(function () {
      left--; if (left <= 0) { clearInterval(t); location.reload(); return; }
      cd.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
    }, 1000);
  }

  // confirm dialogs for destructive buttons
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) { if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault(); });
  });
})();
