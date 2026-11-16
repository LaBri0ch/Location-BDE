/* Aides à l'écran : aperçu du prix dans le formulaire de réservation, et
   confirmation avant les actions destructrices. Le serveur recalcule et
   vérifie tout : rien ici n'est une règle. */
(function () {
  'use strict';

  /* Confirmation (formulaires marqués data-confirm) */
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    var msg = f && f.getAttribute && f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });

  /* Aperçu de la réservation */
  var form = document.getElementById('booking-form');
  if (!form) return;

  var start = document.getElementById('start');
  var end = document.getElementById('end');
  var qty = document.getElementById('qty');
  var box = document.getElementById('summary');
  var outDays = document.getElementById('sum-days');
  var outTotal = document.getElementById('sum-total');
  var outCaution = document.getElementById('sum-caution');
  var price = parseFloat(form.getAttribute('data-price')) || 0;
  var caution = parseFloat(form.getAttribute('data-caution')) || 0;
  var max = parseInt(form.getAttribute('data-max'), 10) || 90;
  var DAY = 86400000;
  var fmt = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 2 });

  function parse(v) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || '');
    return m ? Date.UTC(+m[1], +m[2] - 1, +m[3]) : NaN;
  }

  function update() {
    var a = parse(start.value), b = parse(end.value);
    var q = qty ? Math.max(1, parseInt(qty.value, 10) || 1) : 1;
    if (isNaN(a) || isNaN(b) || b < a) { box.hidden = true; return; }
    var days = Math.round((b - a) / DAY) + 1;
    if (days > max) {
      box.hidden = false;
      outDays.textContent = days + ' jours (max ' + max + ')';
      outTotal.textContent = outCaution.textContent = '-';
      return;
    }
    box.hidden = false;
    outDays.textContent = days + (days > 1 ? ' jours' : ' jour');
    outTotal.textContent = price > 0 ? fmt.format(price * days * q) : 'Gratuit';
    outCaution.textContent = fmt.format(caution * q);
  }

  start.addEventListener('change', function () {
    if (!end.value || parse(end.value) < parse(start.value)) end.value = start.value;
    end.min = start.value;
    update();
  });
  end.addEventListener('change', update);
  if (qty) qty.addEventListener('input', update);
  update();

  /* Calendrier de disponibilité : un clic pour le premier jour, un clic pour le dernier.
     Le serveur revérifie tout à l'envoi. */
  var cal = document.getElementById('cal');
  if (!cal) return;
  var stock = parseInt(cal.getAttribute('data-stock'), 10) || 1;
  var todayIso = cal.getAttribute('data-today');
  var used = {};
  try { used = JSON.parse(cal.getAttribute('data-used') || '{}') || {}; } catch (e) { used = {}; }
  var msg = document.getElementById('cal-msg');
  var MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
  var DOW = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'];
  var tp = parse(todayIso);
  var view = new Date(tp);
  view = { y: view.getUTCFullYear(), m: view.getUTCMonth() };
  var minView = { y: view.y, m: view.m };
  var picking = false;   // vrai après le premier clic : on attend le dernier jour

  function iso(t) {
    var d = new Date(t);
    return d.getUTCFullYear() + '-' + ('0' + (d.getUTCMonth() + 1)).slice(-2) + '-' + ('0' + d.getUTCDate()).slice(-2);
  }
  function wantedQty() { return qty ? Math.max(1, parseInt(qty.value, 10) || 1) : 1; }
  function freeOn(t) { return stock - (used[iso(t)] || 0); }
  function rangeProblem(a, b) {
    var days = Math.round((b - a) / DAY) + 1, q = wantedQty();
    if (days > max) return 'La durée maximale est de ' + max + ' jours.';
    for (var t = a; t <= b; t += DAY) {
      if (freeOn(t) < q) return 'Pas assez d\'exemplaires libres le ' + new Date(t).getUTCDate() + ' ' + MONTHS[new Date(t).getUTCMonth()] + '.';
    }
    return '';
  }
  function say(text, bad) {
    msg.hidden = !text;
    msg.textContent = text || '';
    msg.className = 'msg' + (bad ? ' bad' : ' ok');
  }
  function setRange(a, b) {
    start.value = iso(a);
    end.value = iso(b);
    end.min = start.value;
    start.dispatchEvent(new Event('change'));
    end.value = iso(b);
    end.dispatchEvent(new Event('change'));
  }
  function minFree(a, b) {
    var m = stock;
    for (var t = a; t <= b; t += DAY) m = Math.min(m, freeOn(t));
    return Math.max(0, m);
  }
  function describe() {
    var a = parse(start.value), b = parse(end.value);
    if (isNaN(a) || isNaN(b) || b < a) { say(''); return; }
    var problem = rangeProblem(a, b);
    if (problem) { say(problem + ' Choisis d\'autres dates.', true); return; }
    var f = minFree(a, b);
    if (qty) { qty.max = String(f); if (wantedQty() > f) qty.value = String(Math.max(1, f)); }
    say(stock > 1 ? 'Sur ces dates, ' + f + ' exemplaire' + (f > 1 ? 's sont libres' : ' est libre') + ' (sur ' + stock + ').' : 'Ces dates sont libres.', false);
  }

  function render() {
    var a = parse(start.value), b = parse(end.value), q = wantedQty();
    var first = Date.UTC(view.y, view.m, 1);
    var lead = (new Date(first).getUTCDay() + 6) % 7;
    var nDays = new Date(Date.UTC(view.y, view.m + 1, 0)).getUTCDate();
    var atMin = view.y === minView.y && view.m === minView.m;
    var atMax = (view.y - minView.y) * 12 + (view.m - minView.m) >= 12;
    var h = '<div class="cal-head"><button type="button" class="btn small" data-nav="-1" aria-label="Mois précédent"' + (atMin ? ' disabled' : '') + '>‹</button>'
      + '<b>' + MONTHS[view.m] + ' ' + view.y + '</b>'
      + '<button type="button" class="btn small" data-nav="1" aria-label="Mois suivant"' + (atMax ? ' disabled' : '') + '>›</button></div>'
      + '<div class="cal-grid" role="grid">';
    for (var i = 0; i < 7; i++) h += '<span class="cal-dow">' + DOW[i] + '</span>';
    for (i = 0; i < lead; i++) h += '<span></span>';
    for (var d = 1; d <= nDays; d++) {
      var t = Date.UTC(view.y, view.m, d), f = freeOn(t), past = t < tp;
      var blocked = past || f < q;
      var cls = 'cal-day' + (blocked ? ' is-blocked' : '') + (!blocked && f < stock ? ' is-part' : '') + (t === tp ? ' is-today' : '');
      if (!isNaN(a) && !isNaN(b) && t >= a && t <= b) cls += ' is-sel' + (t === a ? ' is-first' : '') + (t === b ? ' is-last' : '');
      var label = d + ' ' + MONTHS[view.m] + (past ? ', passé' : f <= 0 ? ', complet' : ', ' + f + ' libre' + (f > 1 ? 's' : ''));
      h += '<button type="button" class="' + cls + '" data-t="' + t + '"' + (blocked ? ' disabled' : '') + ' aria-label="' + label + '">'
        + '<span>' + d + '</span>' + (!past && stock > 1 ? '<small>' + Math.max(0, f) + '</small>' : '') + '</button>';
    }
    h += '</div>';
    cal.innerHTML = h;
  }

  cal.addEventListener('click', function (ev) {
    var nav = ev.target.closest('[data-nav]');
    if (nav) {
      var n = view.m + parseInt(nav.getAttribute('data-nav'), 10);
      view = { y: view.y + Math.floor(n / 12), m: ((n % 12) + 12) % 12 };
      render();
      return;
    }
    var btn = ev.target.closest('.cal-day');
    if (!btn || btn.disabled) return;
    var t = parseInt(btn.getAttribute('data-t'), 10);
    if (!picking) {
      picking = true;
      setRange(t, t);
    } else {
      var a = parse(start.value);
      if (t < a) {
        setRange(t, t);
      } else {
        var problem = rangeProblem(a, t);
        if (problem) { say(problem + ' Choisis un autre dernier jour.', true); return; }
        setRange(a, t);
        picking = false;
      }
    }
    render();
    describe();
  });

  function resync() { render(); describe(); }
  start.addEventListener('change', resync);
  end.addEventListener('change', resync);
  if (qty) qty.addEventListener('input', resync);

  cal.hidden = false;
  var s0 = parse(start.value);
  if (!isNaN(s0)) { var dd = new Date(s0); view = { y: dd.getUTCFullYear(), m: dd.getUTCMonth() }; }
  render();
  var s1 = parse(start.value), e1 = parse(end.value);
  if (!isNaN(s1) && !isNaN(e1) && s1 === tp && e1 === tp && freeOn(tp) < wantedQty()) say('Aujourd\'hui est complet : choisis tes dates dans le calendrier.', true);
})();
