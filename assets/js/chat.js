// assets/js/chat.js - live chat (polls every 3 seconds)
(function () {
  var box = document.getElementById('chat'); if (!box) return;
  var base = document.body.dataset.base || '', tid = box.dataset.tenant || '';
  var msgs = document.getElementById('msgs'), form = document.getElementById('compose'), input = document.getElementById('body');
  var csrf = document.querySelector('meta[name=csrf]').content, lastId = 0, busy = false;
  var url = function (extra) { return base + '/api/chat.php?' + (tid ? 'tenant_id=' + encodeURIComponent(tid) + '&' : '') + extra; };
  function add(m) {
    if (m.id <= lastId) return; lastId = m.id;
    var d = document.createElement('div'); d.className = 'm ' + (m.mine ? 'me' : 'them');
    d.appendChild(document.createTextNode(m.body));                  // text only: no HTML injection
    var t = document.createElement('time'); t.textContent = m.time; d.appendChild(t); msgs.appendChild(d);
  }
  function load() {
    if (busy) return; busy = true;
    fetch(url('after=' + lastId), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      busy = false; if (!Array.isArray(d) || !d.length) return;
      var near = msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 120;
      d.forEach(add); if (near || lastId === d[d.length - 1].id) msgs.scrollTop = msgs.scrollHeight;
    }).catch(function () { busy = false; });
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault(); var text = input.value.trim(); if (!text) return;
    var f = new FormData(); f.append('csrf', csrf); f.append('body', text); if (tid) f.append('tenant_id', tid);
    input.value = '';
    fetch(base + '/api/chat.php', { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.error) { alert(d.error); return; } load();
    }).catch(function () { alert('Could not send. Check your connection.'); });
  });
  load(); setInterval(load, 3000);
})();
