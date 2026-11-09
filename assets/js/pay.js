// assets/js/pay.js - animated phone for the simulated mobile money prompt
(function () {
  var screen = document.getElementById('screen'); if (!screen) return;
  var form = document.getElementById('pinForm'), pinIn = document.getElementById('pin');
  function el(t, c, x) { var n = document.createElement(t); if (c) n.className = c; if (x !== undefined) n.textContent = x; return n; }
  function waiting() {
    screen.textContent = ''; screen.appendChild(el('div', 'spin')); screen.appendChild(el('strong', '', screen.dataset.provider));
    screen.appendChild(el('p', '', 'Sending a request to ' + screen.dataset.phone + '...')); setTimeout(prompt, 2200);
  }
  function prompt() {
    var pin = '';
    screen.textContent = ''; screen.appendChild(el('strong', '', screen.dataset.provider));
    screen.appendChild(el('p', '', 'Approve payment of GHS ' + screen.dataset.amount + ' to CampusNest?'));
    var dots = el('div', 'pin'); for (var i = 0; i < 4; i++) dots.appendChild(el('i')); screen.appendChild(dots);
    screen.appendChild(el('p', '', 'Enter your PIN'));
    var keys = el('div', 'keys');
    ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'C', '0', '\u232B'].forEach(function (k) {
      var b = el('button', '', k); b.type = 'button'; b.setAttribute('aria-label', k === 'C' ? 'Clear' : (k === '\u232B' ? 'Delete' : 'Digit ' + k));
      b.addEventListener('click', function () {
        if (k === 'C') pin = ''; else if (k === '\u232B') pin = pin.slice(0, -1); else if (pin.length < 4) pin += k;
        Array.prototype.forEach.call(dots.children, function (d, j) { d.className = j < pin.length ? 'f' : ''; });
        if (pin.length === 4) { pinIn.value = pin; screen.appendChild(el('p', '', 'Processing...')); setTimeout(function () { form.submit(); }, 700); }
      });
      keys.appendChild(b);
    });
    screen.appendChild(keys);
  }
  waiting();
})();
