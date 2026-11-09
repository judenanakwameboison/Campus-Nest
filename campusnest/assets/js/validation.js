// assets/js/validation.js - client-side form validation (the server validates again)
(function () {
  function check(f) {
    if (f.type === 'hidden' || f.type === 'submit' || f.disabled) return '';
    var v = f.value.trim();
    if (f.required && v === '') return 'This field is required.';
    if (v === '') return '';
    if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Enter a valid email address.';
    if (f.dataset.rule === 'phone' && !/^[0-9+\s-]{9,15}$/.test(v)) return 'Enter a valid phone number (9 to 15 digits).';
    if (f.minLength > 0 && v.length < f.minLength) return 'Use at least ' + f.minLength + ' characters.';
    if (f.type === 'number') {
      var n = parseFloat(v);
      if (isNaN(n)) return 'Enter a number.';
      if (f.min !== '' && n < parseFloat(f.min)) return 'Value must be at least ' + f.min + '.';
      if (f.max !== '' && n > parseFloat(f.max)) return 'Value must be at most ' + f.max + '.';
    }
    if (f.type === 'date' && f.min && v < f.min) return 'Date cannot be earlier than ' + f.min + '.';
    if (f.dataset.match) {
      var other = f.form.querySelector(f.dataset.match);
      if (other && other.value !== f.value) return 'Values do not match.';
    }
    return '';
  }
  function show(f, msg) {
    f.classList.add('invalid');
    var s = document.createElement('span');
    s.className = 'err'; s.textContent = msg; s.setAttribute('role', 'alert');
    f.parentNode.appendChild(s);
  }
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
      form.setAttribute('novalidate', 'novalidate');
      form.addEventListener('submit', function (ev) {
        var ok = true;
        form.querySelectorAll('.err').forEach(function (n) { n.remove(); });
        form.querySelectorAll('.invalid').forEach(function (n) { n.classList.remove('invalid'); });
        form.querySelectorAll('input,select,textarea').forEach(function (f) {
          var m = check(f);
          if (m) { ok = false; show(f, m); }
        });
        if (!ok) { ev.preventDefault(); var first = form.querySelector('.invalid'); if (first) first.focus(); }
      });
    });
  });
})();
