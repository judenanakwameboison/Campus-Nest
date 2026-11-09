// assets/js/ui.js - parallax, scroll reveal, counters, tilt, charts, calculator
(function () {
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var fmt = function (n, d) { return Number(n).toLocaleString('en-GH', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 }); };

  // ---------- count-up numbers ----------
  function countUp(el) {
    var to = parseFloat(el.dataset.count) || 0, d = parseInt(el.dataset.decimals || '0', 10);
    var pre = el.dataset.prefix || '', suf = el.dataset.suffix || '';
    if (reduce) { el.textContent = pre + fmt(to, d) + suf; return; }
    var t0 = null, dur = 1400;
    function step(t) {
      if (!t0) t0 = t;
      var p = Math.min((t - t0) / dur, 1), e = 1 - Math.pow(1 - p, 3);
      el.textContent = pre + fmt(to * e, d) + suf;
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  // ---------- progress ring ----------
  function buildRing(el) {
    var pct = Math.max(0, Math.min(100, parseFloat(el.dataset.pct) || 0)), R = 50, C = 2 * Math.PI * R;
    el.innerHTML = '<svg width="120" height="120" viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="' + R + '" fill="none" stroke="#E3F8EE" stroke-width="12"/>' +
      '<circle class="arc" cx="60" cy="60" r="' + R + '" fill="none" stroke="#1FAE74" stroke-width="12" stroke-linecap="round" stroke-dasharray="' + C + '" stroke-dashoffset="' + C + '" style="transition:stroke-dashoffset 1.4s cubic-bezier(.2,.7,.2,1)"/></svg><div class="c">' + Math.round(pct) + '%</div>';
    el.setAttribute('role', 'img'); el.setAttribute('aria-label', Math.round(pct) + ' percent paid');
    setTimeout(function () { $('.arc', el).style.strokeDashoffset = C * (1 - pct / 100); }, 80);
  }
  // ---------- income bars ----------
  function buildBars(el) {
    var data = []; try { data = JSON.parse(el.dataset.json || '[]'); } catch (e) {}
    var max = Math.max.apply(null, data.map(function (d) { return d.v; }).concat([1]));
    el.innerHTML = '';
    data.forEach(function (d) {
      var c = document.createElement('div'); c.className = 'bar-col';
      var tip = document.createElement('em'); tip.textContent = 'GHS ' + fmt(d.v);
      var bar = document.createElement('i'); bar.title = d.l + ': GHS ' + fmt(d.v);
      var lab = document.createElement('span'); lab.textContent = d.l;
      c.appendChild(tip); c.appendChild(bar); c.appendChild(lab); el.appendChild(c);
      setTimeout(function () { bar.style.height = Math.max(4, d.v / max * 78) + '%'; }, 100);
    });
    if (!data.length) el.innerHTML = '<p class="muted">No payments yet.</p>';
  }
  // ---------- donut ----------
  function buildDonut(el) {
    var parts = []; try { parts = JSON.parse(el.dataset.json || '[]'); } catch (e) {}
    var total = parts.reduce(function (a, p) { return a + p.v; }, 0) || 1, R = 54, C = 2 * Math.PI * R, off = 0;
    var svg = '<svg width="150" height="150" viewBox="0 0 150 150" role="img" aria-label="Room status chart"><g transform="rotate(-90 75 75)">';
    parts.forEach(function (p, i) {
      var len = p.v / total * C;
      svg += '<circle cx="75" cy="75" r="' + R + '" fill="none" stroke="' + p.c + '" stroke-width="22" stroke-dasharray="0 ' + C + '" data-len="' + len + '" data-off="' + off + '" style="transition:stroke-dasharray 1.2s ' + (i * 0.2) + 's"/>';
      off += len;
    });
    el.innerHTML = svg + '</g></svg>';
    setTimeout(function () { $$('circle', el).forEach(function (c) { c.setAttribute('stroke-dasharray', c.dataset.len + ' ' + C); c.setAttribute('stroke-dashoffset', -c.dataset.off); }); }, 100);
  }
  // ---------- reveal on scroll ----------
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (es) {
    es.forEach(function (e) {
      if (!e.isIntersecting) return;
      var el = e.target; el.classList.add('in');
      $$('[data-count]', el).concat(el.matches('[data-count]') ? [el] : []).forEach(countUp);
      $$('.ring', el).concat(el.matches('.ring') ? [el] : []).forEach(buildRing);
      $$('.bars', el).concat(el.matches('.bars') ? [el] : []).forEach(buildBars);
      $$('.donut-chart', el).concat(el.matches('.donut-chart') ? [el] : []).forEach(buildDonut);
      io.unobserve(el);
    });
  }, { threshold: 0.15 }) : null;
  function watch() {
    $$('.reveal,[data-count],.ring,.bars,.donut-chart').forEach(function (el) {
      if (el.dataset.seen) return; el.dataset.seen = '1';
      if (io) io.observe(el); else { el.classList.add('in'); }
    });
  }
  // ---------- scroll: progress bar, nav, parallax, to-top ----------
  var nav = $('.nav'), prog = $('#progress'), top = $('#toTop'), layers = $$('[data-speed]'), ticking = false;
  function onScroll() {
    var y = window.pageYOffset, h = document.documentElement.scrollHeight - window.innerHeight;
    if (prog) prog.style.width = (h > 0 ? y / h * 100 : 0) + '%';
    if (nav) nav.classList.toggle('scrolled', y > 40);
    if (top) top.classList.toggle('show', y > 600);
    if (!reduce) layers.forEach(function (l) {
      var r = l.getBoundingClientRect(); if (r.bottom < -300 || r.top > window.innerHeight + 300) return;
      l.style.transform = 'translate3d(0,' + (y * parseFloat(l.dataset.speed)).toFixed(1) + 'px,0)';
    });
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  if (top) top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });
  // ---------- mouse parallax in hero ----------
  var hero = $('.hero');
  if (hero && !reduce) hero.addEventListener('mousemove', function (e) {
    var x = (e.clientX / window.innerWidth - 0.5), y = (e.clientY / window.innerHeight - 0.5);
    $$('[data-depth]', hero).forEach(function (l) { var d = parseFloat(l.dataset.depth); l.style.translate = (x * d * -40) + 'px ' + (y * d * -40) + 'px'; });
  });
  // ---------- 3D tilt on room cards ----------
  document.addEventListener('mousemove', function (e) {
    if (reduce) return;
    var c = e.target.closest ? e.target.closest('.tilt') : null;
    $$('.tilt.tilting').forEach(function (t) { if (t !== c) { t.style.transform = ''; t.classList.remove('tilting'); } });
    if (!c) return;
    var r = c.getBoundingClientRect(), px = (e.clientX - r.left) / r.width - .5, py = (e.clientY - r.top) / r.height - .5;
    c.classList.add('tilting'); c.style.transform = 'perspective(800px) rotateY(' + (px * 9).toFixed(2) + 'deg) rotateX(' + (-py * 9).toFixed(2) + 'deg) translateY(-6px)';
  });
  // ---------- mobile menu, alerts ----------
  var tg = $('#navToggle'), links = $('.links');
  if (tg && links) tg.addEventListener('click', function () { var o = links.classList.toggle('open'); tg.setAttribute('aria-expanded', o); });
  $$('.alert').forEach(function (a) {
    var b = document.createElement('button'); b.type = 'button'; b.setAttribute('aria-label', 'Dismiss'); b.textContent = '\u00d7';
    b.addEventListener('click', function () { a.remove(); }); a.appendChild(b);
  });
  // ---------- rent calculator ----------
  var calc = $('#calc');
  if (calc) {
    var types = []; try { types = JSON.parse(calc.dataset.types); } catch (e) {}
    var sel = $('#calcType'), rng = $('#calcMonths'), out = $('#calcTotal'), per = $('#calcPer'), mo = $('#calcMo'), cur = 0, tween;
    function show(v) { out.textContent = 'GHS ' + fmt(v, 2); }
    function update() {
      var t = types.filter(function (x) { return String(x.id) === sel.value; })[0] || types[0]; if (!t) return;
      var m = parseInt(rng.value, 10), total = t.price * m;
      mo.textContent = m + (m === 1 ? ' month' : ' months'); per.textContent = 'GHS ' + fmt(t.price, 2) + ' per month';
      if (reduce) { show(total); return; }
      var from = cur, t0 = null; cancelAnimationFrame(tween);
      (function step(ts) { if (!t0) t0 = ts; var p = Math.min((ts - t0) / 500, 1); show(from + (total - from) * (1 - Math.pow(1 - p, 3))); if (p < 1) tween = requestAnimationFrame(step); })(performance.now());
      cur = total;
    }
    sel.addEventListener('change', update); rng.addEventListener('input', update); update();
  }
  window.UI = { watch: watch };
  document.addEventListener('DOMContentLoaded', function () { watch(); onScroll(); });
})();
