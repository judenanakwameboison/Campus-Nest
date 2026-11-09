// assets/js/rooms.js - live room search using Fetch (no page reload)
(function () {
  var form = document.getElementById('roomFilter');
  var grid = document.getElementById('roomGrid');
  if (!form || !grid) return;
  var base = grid.dataset.base || '';
  var timer = null;

  function make(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;     // textContent prevents XSS
    return n;
  }
  function render(rooms) {
    grid.textContent = '';
    if (!rooms.length) { grid.appendChild(make('p', 'muted', 'No rooms match your search.')); return; }
    rooms.forEach(function (r) {
      var card = make('div', 'card room tilt reveal in');
      var ph = make('div', 'ph');
      if (r.image_path) {
        var im = document.createElement('img');
        im.src = base + '/' + r.image_path; im.alt = 'Room ' + r.public_name;
        im.style.cssText = 'width:100%;height:100%;object-fit:cover';
        ph.appendChild(im);
      } else { ph.textContent = 'Room photo'; }
      card.appendChild(ph);
      var inn = make('div', 'in');
      var top = make('div', ''); top.style.cssText = 'display:flex;justify-content:space-between;align-items:center';
      top.appendChild(make('h2', '', 'Room ' + r.public_name));
      var pend = Number(r.pending) > 0;
      var label = r.status === 'vacant' ? (pend ? 'Pending request' : 'Available') : (r.status === 'occupied' ? 'Occupied' : 'Maintenance');
      top.appendChild(make('span', 'b ' + (r.status === 'vacant' && !pend ? 'ok' : (r.status === 'occupied' ? 'bad' : 'warn')), label));
      inn.appendChild(top);
      inn.appendChild(make('p', 'muted', r.type_name + ' (sleeps ' + r.capacity + ') · Floor ' + r.floor));
      var rl = make('p', 'rating-line');
      if (Number(r.reviews) > 0) { rl.appendChild(make('span', 'stars', '\u2605'.repeat(Math.round(r.rating)) + '\u2606'.repeat(5 - Math.round(r.rating)))); rl.appendChild(document.createTextNode(' ' + r.rating + ' (' + r.reviews + ')')); } else { rl.textContent = 'No reviews yet'; }
      inn.appendChild(rl);
      inn.appendChild(make('p', 'price', 'GHS ' + Number(r.price_per_month).toLocaleString('en-GH', { minimumFractionDigits: 2 }) + ' / month'));
      var acts = make('div', 'actions');
      var dt = make('a', 'btn btn2', 'Details'); dt.href = base + '/room.php?id=' + encodeURIComponent(r.room_id); acts.appendChild(dt);
      if (r.status === 'vacant') {
        var a = make('a', 'btn', 'Request booking'); a.href = base + '/book.php?room_id=' + encodeURIComponent(r.room_id); acts.appendChild(a);
      }
      inn.appendChild(acts);
      card.appendChild(inn); grid.appendChild(card);
    });
  }
  function load() {
    var qs = new URLSearchParams(new FormData(form)).toString();
    fetch(base + '/api/rooms.php?' + qs)
      .then(function (res) { if (!res.ok) throw new Error('Request failed'); return res.json(); })
      .then(render)
      .catch(function (err) { console.error(err); });
  }
  form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 250); });
  form.addEventListener('submit', function (ev) { ev.preventDefault(); load(); });
  // type chips: set the hidden select and reload
  document.querySelectorAll('.chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      document.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('on'); c.setAttribute('aria-pressed', 'false'); });
      chip.classList.add('on'); chip.setAttribute('aria-pressed', 'true');
      var sel = document.getElementById('type'); sel.value = chip.dataset.type; load();
    });
  });
})();
