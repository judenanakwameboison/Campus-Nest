// assets/js/modern.js - theme, search palette, notifications, lightbox, 360 viewer, map tooltips
(function () {
  var body = document.body, base = body.dataset.base || '', root = document.documentElement;
  var csrfEl = document.querySelector('meta[name=csrf]'), csrf = csrfEl ? csrfEl.content : '';
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; }

  // ---------- theme ----------
  function paint() {
    var d = root.getAttribute('data-theme') === 'dark';
    $$('.theme-toggle').forEach(function (b) { b.textContent = d ? '\u2600' : '\u263E'; b.setAttribute('aria-label', d ? 'Switch to light mode' : 'Switch to dark mode'); });
  }
  $$('.theme-toggle').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', t); try { localStorage.setItem('cn-theme', t); } catch (e) {} paint();
    });
  });
  paint();

  // ---------- toasts ----------
  var toasts = el('div'); toasts.id = 'toasts'; toasts.setAttribute('aria-live', 'polite'); document.body.appendChild(toasts);
  function toast(n) {
    var t = el('div', 'toast'); t.appendChild(el('b', '', n.title)); t.appendChild(el('span', '', n.body || ''));
    t.addEventListener('click', function () { if (n.link) location.href = base + n.link; t.remove(); });
    toasts.appendChild(t); setTimeout(function () { t.remove(); }, 7000);
  }
  window.CNToast = toast;

  // ---------- notifications ----------
  var bell = $('#bell');
  if (bell) {
    var menu = $('#bellMenu'), badge = $('.badge-n', bell), lastId = 0, first = true;
    function draw(d) {
      badge.textContent = d.unread > 9 ? '9+' : d.unread; badge.hidden = !d.unread;
      menu.textContent = '';
      var h = el('header'); h.appendChild(el('span', '', 'Notifications'));
      var mk = el('button', '', 'Mark all read'); mk.type = 'button';
      mk.addEventListener('click', function (e) { e.stopPropagation(); var f = new FormData(); f.append('csrf', csrf); fetch(base + '/api/notifications.php', { method: 'POST', body: f, credentials: 'same-origin' }).then(poll); });
      h.appendChild(mk); menu.appendChild(h);
      if (!d.items.length) menu.appendChild(el('p', '', 'You are all caught up.'));
      d.items.forEach(function (n) {
        var a = el('a', n.is_read ? '' : 'unread'); a.href = base + (n.link || '/index.php');
        a.appendChild(el('b', '', n.title)); a.appendChild(el('span', '', n.body || '')); menu.appendChild(a);
      });
    }
    function poll() {
      fetch(base + '/api/notifications.php', { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
        if (!d) return; draw(d);
        d.items.forEach(function (n) { if (!first && n.id > lastId && !n.is_read) toast(n); });
        d.items.forEach(function (n) { if (n.id > lastId) lastId = n.id; }); first = false;
      }).catch(function () {});
    }
    bell.addEventListener('click', function (e) { e.stopPropagation(); menu.hidden = !menu.hidden; bell.setAttribute('aria-expanded', !menu.hidden); });
    document.addEventListener('click', function (e) { if (!menu.hidden && !menu.contains(e.target)) menu.hidden = true; });
    poll(); setInterval(poll, 15000);
  }

  // ---------- search palette (Ctrl+K) ----------
  var cmds = []; try { cmds = JSON.parse(body.dataset.nav || '[]'); } catch (e) {}
  var pal = el('div'); pal.id = 'palette'; pal.hidden = true;
  var box = el('div', 'pal-box'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', 'Search');
  var inp = el('input'); inp.type = 'search'; inp.placeholder = 'Search rooms, pages, people...'; inp.setAttribute('aria-label', 'Search'); inp.autocomplete = 'off';
  var list = el('ul'); list.id = 'palList'; var hint = el('p', 'pal-hint', 'Arrow keys to move, Enter to open, Esc to close');
  box.appendChild(inp); box.appendChild(list); box.appendChild(hint); pal.appendChild(box); document.body.appendChild(pal);
  var items = [], sel = 0, remote = [], timer = null;
  function render() {
    var q = inp.value.trim().toLowerCase();
    var local = cmds.filter(function (c) { return !q || c.t.toLowerCase().indexOf(q) > -1; }).map(function (c) { return { t: c.t, s: 'Page', u: c.u }; });
    items = local.concat(remote).slice(0, 14); sel = Math.min(sel, Math.max(items.length - 1, 0)); list.textContent = '';
    if (!items.length) { var li = el('li', '', 'No results'); list.appendChild(li); return; }
    items.forEach(function (it, i) {
      var li = el('li', i === sel ? 'sel' : ''); li.appendChild(el('span', '', it.t)); li.appendChild(el('small', '', it.s)); li.setAttribute('role', 'option');
      li.addEventListener('click', function () { location.href = base + it.u; }); list.appendChild(li);
    });
  }
  function openPal() { pal.hidden = false; inp.value = ''; remote = []; sel = 0; render(); inp.focus(); }
  function closePal() { pal.hidden = true; }
  var sb = $('#searchBtn'); if (sb) sb.addEventListener('click', openPal);
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); pal.hidden ? openPal() : closePal(); return; }
    if (pal.hidden) return;
    if (e.key === 'Escape') closePal();
    else if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, items.length - 1); render(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); render(); }
    else if (e.key === 'Enter' && items[sel]) { location.href = base + items[sel].u; }
  });
  pal.addEventListener('click', function (e) { if (e.target === pal) closePal(); });
  inp.addEventListener('input', function () {
    sel = 0; render(); clearTimeout(timer); var q = inp.value.trim();
    if (!q) { remote = []; return; }
    timer = setTimeout(function () {
      fetch(base + '/api/search.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) { remote = d; render(); }).catch(function () {});
    }, 200);
  });

  // ---------- lightbox ----------
  var shots = $$('[data-gallery] img[data-full]');
  if (shots.length) {
    var lb = el('div'); lb.id = 'lightbox'; lb.hidden = true; var im = el('img'); im.alt = '';
    var prev = el('button', '', '\u2039'), next = el('button', '', '\u203A'), cl = el('button', '', '\u00D7'); prev.id = 'lbPrev'; next.id = 'lbNext'; cl.id = 'lbClose';
    prev.setAttribute('aria-label', 'Previous'); next.setAttribute('aria-label', 'Next'); cl.setAttribute('aria-label', 'Close');
    [im, prev, next, cl].forEach(function (n) { lb.appendChild(n); }); document.body.appendChild(lb);
    var idx = 0; function show(i) { idx = (i + shots.length) % shots.length; im.src = shots[idx].dataset.full; im.alt = shots[idx].alt; lb.hidden = false; }
    shots.forEach(function (s, i) { s.addEventListener('click', function () { show(i); }); });
    prev.addEventListener('click', function () { show(idx - 1); }); next.addEventListener('click', function () { show(idx + 1); }); cl.addEventListener('click', function () { lb.hidden = true; });
    lb.addEventListener('click', function (e) { if (e.target === lb) lb.hidden = true; });
    document.addEventListener('keydown', function (e) { if (lb.hidden) return; if (e.key === 'Escape') lb.hidden = true; if (e.key === 'ArrowRight') show(idx + 1); if (e.key === 'ArrowLeft') show(idx - 1); });
  }

  // ---------- 360 panorama viewer ----------
  $$('.pano').forEach(function (p) {
    var src = p.dataset.src; if (!src) return;
    var img = new Image(); img.onload = function () {
      var h = p.clientHeight, w = img.naturalWidth * (h / img.naturalHeight), x = 0, v = reduce ? 0 : -0.25, drag = false, sx = 0, lx = 0;
      p.style.backgroundImage = 'url("' + src + '")'; p.style.backgroundSize = w + 'px ' + h + 'px';
      function paintP() { p.style.backgroundPositionX = (((x % w) + w) % w) + 'px'; }
      p.addEventListener('pointerdown', function (e) { drag = true; sx = e.clientX; lx = x; p.setPointerCapture(e.pointerId); });
      p.addEventListener('pointermove', function (e) { if (!drag) return; var nx = lx + (e.clientX - sx); v = (nx - x) * 0.6; x = nx; paintP(); });
      p.addEventListener('pointerup', function () { drag = false; if (!reduce && Math.abs(v) < 0.2) v = -0.25; });
      p.addEventListener('keydown', function (e) { if (e.key === 'ArrowLeft') { x += 40; paintP(); } if (e.key === 'ArrowRight') { x -= 40; paintP(); } });
      (function loop() { if (!drag) { x += v; v *= 0.985; if (!reduce && Math.abs(v) < 0.25) v = v < 0 ? -0.25 : 0.25; paintP(); } requestAnimationFrame(loop); })();
    };
    img.src = src;
  });

  // ---------- map tooltip ----------
  var tip = el('div'); tip.id = 'tip'; document.body.appendChild(tip);
  $$('.mroom').forEach(function (r) {
    r.addEventListener('mousemove', function (e) { tip.textContent = r.dataset.tip || ''; tip.style.display = 'block'; tip.style.left = (e.clientX + 14) + 'px'; tip.style.top = (e.clientY + 14) + 'px'; });
    r.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
  });
  // ---------- roommate bars ----------
  $$('.mbar i').forEach(function (b) { setTimeout(function () { b.style.width = b.dataset.w + '%'; }, 150); });
})();
