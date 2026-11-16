/* =============================================================================
   Démo du site de location du BDE (publiée avec GitHub Pages).

   Tout se passe dans le navigateur, sans serveur :
   - les données sont fictives et enregistrées dans le localStorage du visiteur ;
   - rien n'est envoyé nulle part, aucun e-mail, aucun paiement ;
   - les mots de passe ne sont PAS protégés comme sur un vrai site.
   Ce fichier sert à présenter le projet. Ce n'est pas un site de production.
   ========================================================================== */
(function () {
  'use strict';

  /* ======================= Utilitaires ======================= */

  var DAY = 86400000;
  var STORE_KEY = 'bde-location-demo-v1';

  function e(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function isoLocal(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function isoUTC(d) { return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()); }
  function today() { return isoLocal(new Date()); }
  function nowStr() {
    var d = new Date();
    return isoLocal(d) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
  }
  function parseDate(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
    if (!m) return null;
    var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
    return isoUTC(d) === s ? d : null;
  }
  function addDays(s, n) {
    var d = parseDate(s);
    if (!d) return s;
    d.setUTCDate(d.getUTCDate() + n);
    return isoUTC(d);
  }
  function daysBetween(a, b) {
    var x = parseDate(a), y = parseDate(b);
    return x && y ? Math.round((y - x) / DAY) + 1 : 0;
  }
  var MONTHS_S = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  var WD = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
  function dateFr(s, weekday) {
    var d = parseDate(s);
    if (!d) return '?';
    var t = d.getUTCDate() + ' ' + MONTHS_S[d.getUTCMonth()];
    return weekday ? WD[d.getUTCDay()] + ' ' + t : t;
  }
  function plural(n, w) { return n + ' ' + w + (n > 1 ? 's' : ''); }
  function round2(n) { return Math.round(n * 100) / 100; }
  function money(n) {
    n = Number(n) || 0;
    var dec = Math.abs(n - Math.round(n)) < 0.005 ? 0 : 2;
    var parts = n.toFixed(dec).split('.');
    var i = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return i + (parts[1] ? ',' + parts[1] : '') + ' €';
  }
  function decimalInput(n) {
    var s = (Number(n) || 0).toFixed(2).replace('.', ',');
    s = s.replace(/,?0+$/, '');
    return s === '' ? '0' : s;
  }
  function parseDecimal(s) {
    s = String(s == null ? '' : s).trim().replace(/,/g, '.').replace(/[\s ]/g, '');
    return s !== '' && isFinite(+s) ? +s : null;
  }
  function cleanLine(s, max) { return String(s == null ? '' : s).replace(/[\r\n\t]+/g, ' ').trim().slice(0, max); }
  function safeUrl(u) {
    u = String(u || '').trim();
    if (!u) return '';
    try {
      var x = new URL(u);
      return (x.protocol === 'https:' || x.protocol === 'http:') ? u : '';
    } catch (_) { return ''; }
  }
  function randInt(n) {
    if (window.crypto && window.crypto.getRandomValues) {
      var a = new Uint32Array(1);
      window.crypto.getRandomValues(a);
      return a[0] % n;
    }
    return Math.floor(Math.random() * n);
  }
  var ALPHA = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  function randomCode(len) { var s = ''; for (var i = 0; i < len; i++) s += ALPHA[randInt(ALPHA.length)]; return s; }
  function makeInviteCode() { var c = randomCode(10); return c.slice(0, 5) + '-' + c.slice(5); }
  function normalizeCode(c) { return String(c || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase(); }
  function normalizeEmail(s) { return String(s || '').trim().toLowerCase(); }
  function validEmail(s) { return s !== '' && s.length <= 120 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s); }
  function validPassword(p) { return String(p).length < 10 ? 'Le mot de passe doit faire au moins 10 caractères.' : null; }
  function initial(name) { return String(name || '?').charAt(0).toUpperCase(); }
  function byName(a, b) { return a.name.localeCompare(b.name, 'fr', { sensitivity: 'base' }); }
  function str(fd, k) { var v = fd.get(k); return v == null ? '' : String(v).trim(); }

  /* Empreinte de mot de passe pour la démo seulement : évite de garder le texte en
     clair dans le navigateur, mais ce n'est PAS une protection sérieuse. Un vrai
     site doit vérifier les mots de passe côté serveur (bcrypt, argon2…). */
  function cyrb53(s, seed) {
    var h1 = 0xdeadbeef ^ seed, h2 = 0x41c6ce57 ^ seed;
    for (var i = 0, ch; i < s.length; i++) {
      ch = s.charCodeAt(i);
      h1 = Math.imul(h1 ^ ch, 2654435761);
      h2 = Math.imul(h2 ^ ch, 1597334677);
    }
    h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
    h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909);
    return (4294967296 * (2097151 & h2) + (h1 >>> 0)).toString(16);
  }
  function hashPw(p) { return 'demo$' + cyrb53('bde:' + p, 7) + cyrb53('loc:' + p, 13); }

  /* ======================= Données (localStorage) ======================= */

  var S = null;
  var storageOk = true;

  function storageGet() { try { return window.localStorage.getItem(STORE_KEY); } catch (_) { return null; } }
  function storageSet(v) { try { window.localStorage.setItem(STORE_KEY, v); return true; } catch (_) { return false; } }
  function storageDel() { try { window.localStorage.removeItem(STORE_KEY); } catch (_) { /* rien */ } }

  function save() { storageOk = storageSet(JSON.stringify(S)); return storageOk; }
  function load() {
    var raw = storageGet();
    if (raw) {
      try {
        var s = JSON.parse(raw);
        if (s && s.v === 1) { S = s; return; }
      } catch (_) { /* données illisibles : on repart de zéro */ }
    }
    S = seed();
    save();
  }
  function nextId(kind) { S.seq[kind] = (S.seq[kind] || 0) + 1; return S.seq[kind]; }
  function setting(k, def) { var v = S.settings[k]; return v !== undefined && v !== '' ? String(v) : (def || ''); }
  function siteTitle() { return setting('site_name', 'Location BDE'); }
  function findItem(id) { for (var i = 0; i < S.items.length; i++) if (S.items[i].id === id) return S.items[i]; return null; }
  function findUser(id) { for (var i = 0; i < S.users.length; i++) if (S.users[i].id === id) return S.users[i]; return null; }
  function findUserByEmail(em) { em = normalizeEmail(em); for (var i = 0; i < S.users.length; i++) if (S.users[i].email === em) return S.users[i]; return null; }
  function findResa(id) { for (var i = 0; i < S.reservations.length; i++) if (S.reservations[i].id === id) return S.reservations[i]; return null; }
  function uniqueRef() {
    var r;
    do { r = randomCode(6); } while (S.reservations.some(function (x) { return x.ref === r; }));
    return r;
  }
  function addEvent(resaId, status, by, at) {
    S.events.push({ id: nextId('event'), reservation_id: resaId, status: status, by_user: by, at: at || nowStr() });
  }

  var DEMO_ACCOUNTS = {
    bureau: { name: 'Bureau Démo', email: 'bureau.demo@ipsa.fr', password: 'demo-bureau', is_admin: true, phone: '' },
    etudiant: { name: 'Camille Démo', email: 'etudiant.demo@ipsa.fr', password: 'demo-etudiant', is_admin: false, phone: '06 39 98 00 01' }
  };

  /* Jeu de données de départ. Les dates sont calculées à partir d'aujourd'hui pour que
     la démo montre toujours un peu de tout : demandes à traiter, prêt en cours, retard… */
  function seed() {
    var T = today();
    var d = function (n) { return addDays(T, n); };
    var at = function (n, h) { return d(n) + ' ' + (h || '10:00') + ':00'; };
    var st = {
      v: 1,
      seededAt: T,
      seq: { item: 10, user: 0, resa: 0, event: 0 },
      settings: {
        site_name: "Location BDE Atmosph'air",
        max_days: '30',
        helloasso_url: '',
        swikly_url: '',
        mail_from: '',
        notify_email: '',
        email_domains: 'ipsa.fr',
        pickup_info: 'Permanence du local BDE - B002 - 8h30 à 17h30'
      },
      items: [
        { id: 4, name: 'Appareil à raclette', description: '8 personnes', stock: 2, caution: 34.99, price_day: 3, image: 'assets/img/raclette.webp' },
        { id: 5, name: 'Crêpière électrique', description: 'Taille : grande et petite x6', stock: 1, caution: 39.98, price_day: 1, image: 'assets/img/crepiere.webp' },
        { id: 6, name: 'Diable de transport', description: 'Option : pliable', stock: 1, caution: 35.9, price_day: 3, image: 'assets/img/diable.webp' },
        { id: 7, name: "Kit d'outils de bricolage", description: '', stock: 1, caution: 29.9, price_day: 0, image: 'assets/img/kit-outils.webp' },
        { id: 8, name: 'Multiprises/rallonges', description: 'Par lot de plusieurs', stock: 2, caution: 9.56, price_day: 0, image: 'assets/img/multiprises.webp' },
        { id: 9, name: 'Vidéoprojecteur', description: 'HDMI', stock: 1, caution: 89.9, price_day: 0, image: 'assets/img/videoprojecteur.webp' },
        { id: 10, name: 'Gaufrier électrique', description: '', stock: 1, caution: 39.76, price_day: 0, image: 'assets/img/gaufrier.webp' }
      ].map(function (it) {
        it.helloasso_url = ''; it.swikly_url = ''; it.active = true; it.created_at = at(-40);
        return it;
      }),
      users: [],
      invites: [],
      reservations: [],
      events: [],
      session: null,
      flashes: [],
      demoIds: {}
    };
    S = st;  // les aides ci-dessous travaillent sur S

    function user(name, email, pw, admin, phone, created, last) {
      var u = { id: nextId('user'), email: email, name: name, password_hash: hashPw(pw), is_admin: !!admin,
        created_at: at(created), last_login: last == null ? null : at(last, '18:42'), phone: phone || '', notify: true };
      S.users.push(u);
      return u;
    }
    var bureau = user(DEMO_ACCOUNTS.bureau.name, DEMO_ACCOUNTS.bureau.email, DEMO_ACCOUNTS.bureau.password, true, '', -45, -1);
    var camille = user(DEMO_ACCOUNTS.etudiant.name, DEMO_ACCOUNTS.etudiant.email, DEMO_ACCOUNTS.etudiant.password, false, DEMO_ACCOUNTS.etudiant.phone, -30, -2);
    var jules = user('Jules Exemple', 'jules.exemple@ipsa.fr', randomCode(16), false, '06 39 98 00 02', -28, -3);
    var ines = user('Inès Exemple', 'ines.exemple@ipsa.fr', randomCode(16), false, '06 39 98 00 03', -21, -4);
    var theo = user('Théo Exemple', 'theo.exemple@ipsa.fr', randomCode(16), false, '', -18, -7);
    S.demoIds = { bureau: bureau.id, etudiant: camille.id };
    S.invites.push({ email: 'nouveau.bureau@ipsa.fr', code_hash: hashPw(normalizeCode(makeInviteCode())), created_by: bureau.name, created_at: at(-2, '12:15') });

    function resa(u, itemId, qty, start, end, status, createdDay, note) {
      var it = findItem(itemId);
      var days = daysBetween(d(start), d(end));
      var r = {
        id: nextId('resa'), ref: uniqueRef(), user_id: u.id, item_id: it.id, item_name: it.name, qty: qty,
        start_date: d(start), end_date: d(end), days: days, borrower: u.name, email: u.email, phone: u.phone,
        note: note || '', total: round2(it.price_day * days * qty), caution: round2(it.caution * qty),
        helloasso_url: '', swikly_url: '', status: status, admin_note: '', created_at: at(createdDay, '09:12'), updated_at: at(createdDay, '09:12')
      };
      S.reservations.push(r);
      addEvent(r.id, 'demandee', u.name, r.created_at);
      var path = { demandee: [], payee: ['payee'], remis: ['payee', 'remis'], rendu: ['payee', 'remis', 'rendu'], annulee: ['annulee'] }[status];
      var day = createdDay;
      path.forEach(function (s) {
        if (s === 'remis') day = start;
        else if (s === 'rendu') day = end;
        else day = Math.min(day + 1, 0);
        var when = at(day, s === 'rendu' ? '17:05' : '13:30');
        addEvent(r.id, s, s === 'annulee' ? u.name : bureau.name, when);
        r.updated_at = when;
      });
      return r;
    }
    resa(camille, 4, 1, 3, 4, 'demandee', 0, 'Pour une soirée raclette de promo');
    resa(camille, 8, 2, 1, 2, 'payee', -2);
    resa(camille, 9, 1, -10, -8, 'rendu', -14, 'Projection en salle B105');
    resa(jules, 4, 1, 3, 5, 'payee', -3);
    resa(jules, 10, 1, 8, 8, 'demandee', -1);
    resa(ines, 6, 1, -2, 1, 'remis', -5, 'Déménagement');
    resa(ines, 7, 1, -15, -14, 'annulee', -17);
    resa(theo, 5, 1, -6, -1, 'remis', -9);
    S.reservations[3].admin_note = 'Paiement HelloAsso vérifié.';

    S = null;
    return st;
  }

  /* ======================= Règles de réservation ======================= */

  var STATUS_ORDER = ['demandee', 'payee', 'caution', 'remis', 'rendu'];
  var STATUS_BLOCKING = ['demandee', 'payee', 'caution', 'remis'];
  var STATUSES = {
    demandee: ['Demandée', 'wait'],
    payee: ['Réglée', 'info'],
    caution: ['Caution reçue', 'info'],
    remis: ['Remis', 'out'],
    rendu: ['Rendu', 'ok'],
    annulee: ['Annulée', 'off']
  };
  var NEXT_LABELS = { payee: 'Paiement et caution reçus', remis: 'Objet remis', rendu: 'Objet rendu' };

  function isLate(r) { return r.status === 'remis' && r.end_date < today(); }
  function stepsFor(r) {
    var s = [['demandee', 'Demande']];
    if (r.total > 0 || r.caution > 0) s.push(['payee', 'Paiement et caution']);
    s.push(['remis', 'Remis'], ['rendu', 'Rendu']);
    return s;
  }
  function nextStatus(r) {
    var cur = STATUS_ORDER.indexOf(r.status);
    if (cur < 0) return null;
    var steps = stepsFor(r);
    for (var i = 0; i < steps.length; i++) if (STATUS_ORDER.indexOf(steps[i][0]) > cur) return steps[i][0];
    return null;
  }
  function statusPill(r) {
    if (isLate(r)) return '<span class="pill p-late">À rendre</span>';
    var s = STATUSES[r.status] || ['?', 'off'];
    return '<span class="pill p-' + e(s[1]) + '">' + e(s[0]) + '</span>';
  }
  function effEnd(r) { var t = today(); return r.status === 'remis' && r.end_date < t ? t : r.end_date; }
  function blockingFor(itemId) {
    return S.reservations.filter(function (r) {
      return r.item_id === itemId && STATUS_BLOCKING.indexOf(r.status) >= 0;
    }).map(function (r) { return { qty: r.qty, start: r.start_date, end: effEnd(r) }; });
  }
  function usageByDay(itemId, from, to) {
    var out = {};
    blockingFor(itemId).forEach(function (r) {
      if (r.start > to || r.end < from) return;
      var a = r.start > from ? r.start : from, b = r.end < to ? r.end : to;
      var n = daysBetween(a, b);
      for (var i = 0; i < n; i++) { var dd = addDays(a, i); out[dd] = (out[dd] || 0) + r.qty; }
    });
    return out;
  }
  function itemConflict(item, start, end, qty) {
    var stock = Math.max(0, item.stock | 0);
    if (qty > stock) return { msg: 'Seulement ' + plural(stock, 'exemplaire') + ' en stock.' };
    var rows = blockingFor(item.id).filter(function (r) { return r.start <= end && r.end >= start; });
    if (!rows.length) return null;
    var n = daysBetween(start, end);
    for (var i = 0; i < n; i++) {
      var dd = addDays(start, i), used = 0;
      rows.forEach(function (r) { if (r.start <= dd && r.end >= dd) used += r.qty; });
      if (used + qty > stock) return { date: dd, used: used, stock: stock };
    }
    return null;
  }
  function conflictMessage(c) {
    if (c.msg) return c.msg;
    return 'Plus de disponibilité le ' + dateFr(c.date, true) + ' (' + c.used + ' sur ' + c.stock +
      ' déjà réservé' + (c.used > 1 ? 's' : '') + "). Essaie d'autres dates.";
  }
  function upcomingBlocks(itemId) {
    var t = today();
    return blockingFor(itemId).filter(function (r) { return r.end >= t; })
      .sort(function (a, b) { return a.start < b.start ? -1 : a.start > b.start ? 1 : 0; }).slice(0, 8);
  }
  function itemAvailableToday(item) {
    var t = today(), used = 0;
    blockingFor(item.id).forEach(function (r) { if (r.start <= t && r.end >= t) used += r.qty; });
    return { used: used, free: Math.max(0, (item.stock | 0) - used) };
  }
  function maxDays() { return Math.max(1, Math.min(365, parseInt(setting('max_days', '30'), 10) || 30)); }

  function createReservation(item, inp, user) {
    var errors = [];
    var start = inp.start, end = inp.end;
    var qty = Math.max(1, parseInt(inp.qty, 10) || 1);
    var phone = cleanLine(inp.phone, 30);
    var note = String(inp.note || '').trim().slice(0, 300);
    var days = 0;
    if (!parseDate(start) || !parseDate(end)) errors.push('Choisis une date de début et une date de fin.');
    else if (start < today()) errors.push('La date de début est déjà passée.');
    else if (end < start) errors.push('La date de fin est avant la date de début.');
    else {
      days = daysBetween(start, end);
      if (days > maxDays()) errors.push('La durée maximale est de ' + plural(maxDays(), 'jour') + '. Pour plus long, contacte le bureau.');
    }
    if (phone !== '' && !/^[0-9+().\s-]{6,30}$/.test(phone)) errors.push("Le numéro de téléphone n'est pas valide.");
    if (errors.length) return { ok: false, errors: errors };

    var pending = S.reservations.filter(function (r) { return r.user_id === user.id && r.status === 'demandee'; }).length;
    if (pending >= 5) return { ok: false, errors: ['Tu as déjà plusieurs demandes en attente. Attends que le bureau les traite.'] };

    var c = itemConflict(item, start, end, qty);
    if (c) return { ok: false, errors: [conflictMessage(c)] };

    var now = nowStr();
    var r = {
      id: nextId('resa'), ref: uniqueRef(), user_id: user.id, item_id: item.id, item_name: item.name, qty: qty,
      start_date: start, end_date: end, days: days, borrower: user.name, email: user.email, phone: phone, note: note,
      total: round2(item.price_day * days * qty), caution: round2(item.caution * qty),
      helloasso_url: item.helloasso_url || '', swikly_url: item.swikly_url || '',
      status: 'demandee', admin_note: '', created_at: now, updated_at: now
    };
    S.reservations.push(r);
    addEvent(r.id, 'demandee', user.name, now);
    return { ok: true, reservation: r };
  }
  function changeStatus(r, status, by) {
    if (!STATUSES[status]) return false;
    var now = nowStr();
    r.status = status;
    r.updated_at = now;
    addEvent(r.id, status, by, now);
    return true;
  }
  function payLinks(r) {
    return {
      helloasso: safeUrl(r.helloasso_url) || safeUrl(setting('helloasso_url')),
      swikly: safeUrl(r.swikly_url) || safeUrl(setting('swikly_url'))
    };
  }
  function adminTodoCount() {
    var t = today();
    return S.reservations.filter(function (r) {
      return ['demandee', 'payee', 'caution', 'remis'].indexOf(r.status) >= 0 && (r.status !== 'remis' || r.end_date < t);
    }).length;
  }

  /* ======================= Comptes ======================= */

  function currentUser() { return S.session ? findUser(S.session) : null; }
  function allowedDomains() {
    var raw = setting('email_domains', 'ipsa.fr');
    if (raw === '*') return [];
    return raw.toLowerCase().split(/[\s,;]+/).map(function (d) { return d.replace(/^@+/, ''); }).filter(Boolean);
  }
  function emailDomainOk(email) {
    var ds = allowedDomains();
    if (!ds.length) return true;
    var host = email.slice(email.lastIndexOf('@') + 1);
    return ds.some(function (d) { return host === d || host.slice(-(d.length + 1)) === '.' + d; });
  }
  function domainsLabel() { return allowedDomains().map(function (d) { return '@' + d; }).join(' ou '); }
  function loginUser(u) { S.session = u.id; u.last_login = nowStr(); }
  function adminCount() { return S.users.filter(function (u) { return u.is_admin; }).length; }
  function safeNext(n) {
    n = String(n || '');
    return /^\/[A-Za-z0-9_\-\/]*(\?[A-Za-z0-9_=&%.\-]*)?$/.test(n) && n.length <= 200 ? n : '';
  }
  function afterLoginTarget(u, next) { return safeNext(next) || (u.is_admin ? '/bureau' : '/compte'); }

  function registerUser(name, email, pw, pw2, inviteCode) {
    var errors = [];
    name = cleanLine(name, 80);
    email = normalizeEmail(email);
    if (name.length < 2) errors.push('Indique ton prénom et ton nom.');
    if (!validEmail(email)) errors.push('Indique une adresse e-mail valide.');
    var pe = validPassword(pw);
    if (pe) errors.push(pe);
    if (pw !== pw2) errors.push('Les deux mots de passe ne correspondent pas.');
    if (errors.length) return { ok: false, errors: errors };

    var invite = null;
    S.invites.forEach(function (i) { if (i.email === email) invite = i; });
    if (!invite && !emailDomainOk(email)) {
      return { ok: false, errors: ["Cette adresse n'est pas autorisée. Utilise ton adresse e-mail de l'école (" + domainsLabel() + ').'] };
    }
    if (findUserByEmail(email)) {
      return { ok: false, errors: ['Un compte existe déjà avec cette adresse e-mail. Connecte-toi, ou demande au bureau de réinitialiser ton mot de passe.'] };
    }
    var isAdmin = false;
    if (invite) {
      var code = normalizeCode(inviteCode);
      if (code === '' || hashPw(code) !== invite.code_hash) {
        return { ok: false, errors: ["Cette adresse est réservée au bureau. Saisis le code d'invitation qu'on t'a remis (rubrique « Tu es membre du bureau ? »)."] };
      }
      isAdmin = true;
      S.invites = S.invites.filter(function (i) { return i !== invite; });
    }
    var u = { id: nextId('user'), email: email, name: name, password_hash: hashPw(pw), is_admin: isAdmin,
      created_at: nowStr(), last_login: null, phone: '', notify: true };
    S.users.push(u);
    return { ok: true, user: u };
  }

  /* Comptes de démo : on les recrée s'ils ont été supprimés pendant la visite. */
  function demoUser(kind) {
    var acc = DEMO_ACCOUNTS[kind];
    var u = findUser(S.demoIds[kind]) || findUserByEmail(acc.email);
    if (!u) {
      u = { id: nextId('user'), email: acc.email, name: acc.name, password_hash: hashPw(acc.password), is_admin: acc.is_admin,
        created_at: nowStr(), last_login: null, phone: acc.phone, notify: true };
      S.users.push(u);
    }
    if (kind === 'bureau') u.is_admin = true;
    S.demoIds[kind] = u.id;
    return u;
  }

  /* ======================= Navigation ======================= */

  var transient = {};      // valeurs et erreurs d'un formulaire refusé, pour un seul affichage
  var afterRender = [];    // actions à faire une fois la page affichée (défilement, détails ouverts…)

  function flash(type, text) { S.flashes.push({ type: type, text: text }); }
  function setTransient(key, val) { transient = { key: key, val: val }; }
  function takeTransient(key) {
    if (transient.key === key) { var v = transient.val; transient = {}; return v; }
    return null;
  }
  /* La page courante est gardée en mémoire et recopiée dans l'adresse (#/…) quand c'est
     possible : la démo marche aussi dans un cadre qui ne laisse pas modifier l'adresse. */
  var memPath = null;
  function hashPath() { return location.hash.replace(/^#/, ''); }
  function route() {
    var h = memPath != null ? memPath : hashPath();
    if (h.charAt(0) !== '/') h = '/' + h;
    var i = h.indexOf('?');
    var path = i >= 0 ? h.slice(0, i) : h;
    var q = new URLSearchParams(i >= 0 ? h.slice(i + 1) : '');
    return { full: h, path: path, parts: path.split('/').filter(Boolean), q: q };
  }
  function go(path) {
    save();
    var changed = route().full !== path;
    memPath = path;
    if (hashPath() !== path) {
      try { location.hash = '#' + path; } catch (_) { /* adresse non modifiable : on reste en mémoire */ }
    }
    render();
    if (changed) window.scrollTo(0, 0);
  }
  function refresh(anchor) {
    save();
    if (anchor) afterRender.push(function () { var el = document.getElementById(anchor); if (el) el.scrollIntoView({ block: 'start' }); });
    render(true);
  }
  function requireLogin() {
    var u = currentUser();
    if (!u) {
      go('/connexion?next=' + encodeURIComponent(route().full));
      return null;
    }
    return u;
  }
  function requireAdmin() {
    var u = requireLogin();
    if (!u) return null;
    if (!u.is_admin) {
      flash('bad', "L'espace bureau est réservé aux membres du bureau.");
      go('/compte');
      return null;
    }
    return u;
  }

  /* ======================= Gabarit ======================= */

  var USER_ICON = '<svg class="user-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="4" fill="currentColor"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7z" fill="currentColor"/></svg>';

  function demoBar(user) {
    var as = user ? (user.id === S.demoIds.bureau ? 'bureau' : user.id === S.demoIds.etudiant ? 'etudiant' : '') : '';
    return '<div class="demo-bar" role="note"><div class="demo-bar-in">' +
      '<span><span class="demo-bar-tag">Démo</span>Données fictives, enregistrées seulement dans ce navigateur.</span>' +
      '<span class="demo-bar-act">' +
      '<button type="button" data-click="quick" data-as="etudiant"' + (as === 'etudiant' ? ' aria-pressed="true"' : '') + '>Vue étudiant</button>' +
      '<button type="button" data-click="quick" data-as="bureau"' + (as === 'bureau' ? ' aria-pressed="true"' : '') + '>Vue bureau</button>' +
      '<button type="button" data-click="reset">Réinitialiser</button>' +
      '</span></div></div>';
  }

  function layout(page) {
    var admin = !!page.admin, user = currentUser(), site = siteTitle();
    var nav, right = [];
    if (admin) {
      var todo = adminTodoCount();
      nav = [
        ['suivi', '#/bureau', 'Suivi' + (todo ? ' <span class="badge">' + todo + '</span>' : '')],
        ['items', '#/bureau/materiel', 'Matériel'],
        ['settings', '#/bureau/reglages', 'Réglages'],
        ['comptes', '#/bureau/comptes', 'Comptes']
      ];
      right = [['#/', 'Voir le site'], ['#/parametres', 'Paramètres']];
    } else if (user) {
      nav = [['catalogue', '#/', 'Matériel'], ['compte', '#/compte', 'Mes demandes'], ['parametres', '#/parametres', 'Paramètres']];
      if (user.is_admin) right.push(['#/bureau', 'Espace bureau']);
    } else {
      nav = [['catalogue', '#/', 'Matériel']];
    }
    var h = demoBar(user) + '<div class="wrap"><header class="top"><div class="top-row">' +
      '<a class="brand" href="' + (admin ? '#/bureau' : '#/') + '">' + e(site) + (admin ? ' <span class="brand-sub">Bureau</span>' : '') + '</a>' +
      '<div class="top-user">' +
      (user
        ? '<button class="user-link" type="button" data-click="logout" title="Se déconnecter">' + USER_ICON + '<span>Déconnexion</span></button>'
        : '<a class="user-link user-link-alt" href="#/inscription">Créer un compte</a><a class="user-link" href="#/connexion">' + USER_ICON + '<span>Se connecter</span></a>') +
      '</div></div><nav class="tabs" aria-label="Sections">';
    nav.forEach(function (n) {
      h += '<a class="tab" href="' + n[1] + '"' + (page.active === n[0] ? ' aria-current="page"' : '') + '>' + n[2] + '</a>';
    });
    right.forEach(function (r, i) {
      h += '<a class="tab' + (i === 0 ? ' tab-right' : '') + '" href="' + r[0] + '">' + e(r[1]) + '</a>';
    });
    h += '</nav></header><main>';
    if (!storageOk) h += '<p class="msg warn" role="status">Ton navigateur bloque l\'enregistrement local : la démo fonctionne, mais tout sera perdu au rechargement de la page.</p>';
    S.flashes.forEach(function (f) {
      var cls = ['ok', 'bad', 'warn', 'muted'].indexOf(f.type) >= 0 ? f.type : 'muted';
      h += '<p class="msg ' + cls + '" role="status">' + e(f.text) + '</p>';
    });
    S.flashes = [];
    h += page.html + '</main><footer class="foot-site">' +
      (admin ? '' : '<p class="muted small">&copy; ' + new Date().getFullYear() + ' ' + e(site) + '. Tous droits réservés.</p>') +
      '<p class="muted small">Version de démonstration : les comptes et les réservations sont fictifs et restent dans ton navigateur. Aucun paiement, aucun e-mail.</p>' +
      '</footer></div>';
    document.title = (page.title ? page.title + ' · ' : '') + site + ' · Démo';
    document.getElementById('app').innerHTML = h;
  }

  /* ======================= Pages publiques ======================= */

  function pageCatalogue() {
    var me = currentUser();
    var items = S.items.filter(function (i) { return i.active; }).sort(byName);
    var anyPhoto = items.some(function (i) { return i.image; });
    var h = '<div class="sec-head"><div><h1>Matériel à emprunter</h1><p class="lede">' + (me
      ? 'Choisis un objet, fixe tes dates, puis suis ta demande dans « Mes demandes ».'
      : "Choisis un objet et fixe tes dates. Il faut un compte avec ton adresse e-mail de l'école pour réserver : <a href=\"#/inscription\">crée-le</a> ou <a href=\"#/connexion\">connecte-toi</a>.") +
      '</p></div></div>';
    if (!items.length) {
      return { active: 'catalogue', html: h + '<div class="empty"><h2>Le catalogue est vide pour l\'instant</h2><p class="muted">Le bureau ajoute bientôt les objets disponibles. Repasse un peu plus tard.</p></div>' };
    }
    h += '<div class="grid">';
    items.forEach(function (it) {
      var av = itemAvailableToday(it), next = upcomingBlocks(it.id)[0], stock = it.stock | 0;
      h += '<article class="card">' +
        (it.image ? '<img class="thumb" src="' + e(it.image) + '" alt="' + e(it.name) + '" loading="lazy" width="400" height="300">'
          : anyPhoto ? '<div class="thumb thumb-empty" aria-hidden="true">' + e(initial(it.name)) + '</div>' : '') +
        '<span class="tag">Caution ' + e(money(it.caution)) + '</span>' +
        '<h2 class="card-title">' + e(it.name) + '</h2>' +
        (it.description ? '<p class="desc">' + e(it.description) + '</p>' : '') +
        '<div class="meta"><span class="price">' + (it.price_day > 0 ? e(money(it.price_day)) + ' / jour' : 'Gratuit') + '</span>' +
        (stock <= 0 ? '<span class="pill p-off">Hors stock</span>' : av.free > 0 ? '<span class="pill p-ok">Disponible</span>' : '<span class="pill p-late">Tout est pris aujourd\'hui</span>') +
        (stock > 0 ? '<span class="muted"><b>' + av.free + '</b> libre' + (av.free > 1 ? 's' : '') + ' sur ' + stock + '</span>' : '') +
        '</div><div class="foot-card">' +
        (next ? '<p class="muted small">Prochaine réservation : ' + e(dateFr(next.start)) + ' → ' + e(dateFr(next.end)) + (next.qty > 1 ? ' (' + next.qty + ' ex.)' : '') + '</p>' : '') +
        (stock > 0 ? '<a class="btn primary" href="#/reserver/' + it.id + '">Réserver</a>' : '<span class="btn" aria-disabled="true">Indisponible</span>') +
        '</div></article>';
    });
    return { active: 'catalogue', html: h + '</div>' };
  }

  function pageReserver(id) {
    var user = requireLogin();
    if (!user) return null;
    var item = findItem(id);
    if (!item || !item.active) {
      return { title: 'Objet introuvable', active: 'catalogue', html: '<div class="empty"><h1>Cet objet n\'est plus au catalogue</h1><a class="btn primary" href="#/">Voir le matériel</a></div>' };
    }
    var t = takeTransient('reserve:' + id);
    var v = t ? t.values : { start: today(), end: today(), qty: '1', phone: user.phone || '', note: '' };
    var errors = t ? t.errors : [];
    var usage = usageByDay(item.id, today(), addDays(today(), 400));
    var stock = Math.max(0, item.stock | 0), md = maxDays();

    var h = '<div class="page-narrow"><p><a class="btn small" href="#/">← Matériel</a></p><section class="panel">' +
      (item.image ? '<img class="thumb thumb-wide" src="' + e(item.image) + '" alt="' + e(item.name) + '" width="400" height="300">' : '') +
      '<div class="panel-head"><span class="tag">Caution ' + e(money(item.caution)) + '</span><h1>' + e(item.name) + '</h1>' +
      (item.description ? '<p class="muted">' + e(item.description) + '</p>' : '') + '</div>';
    errors.forEach(function (err) { h += '<p class="msg bad" role="alert">' + e(err) + '</p>'; });
    h += '<div class="cal-wrap"><p class="lbl">Choisis tes dates</p>' +
      '<div id="cal" class="cal" hidden data-stock="' + stock + '" data-today="' + e(today()) + '" data-max="' + md + '" data-used="' + e(JSON.stringify(usage)) + '"></div>' +
      '<p class="muted small" id="cal-help">' + (stock > 1
        ? "Le chiffre sur chaque jour est le nombre d'exemplaires libres (" + stock + " au total). Un jour est bloqué quand il n'en reste plus."
        : 'Les jours barrés sont déjà pris.') + '</p>' +
      '<p class="msg" id="cal-msg" role="status" hidden></p></div>' +
      '<form class="form" id="booking-form" data-form="reserve" autocomplete="on" data-price="' + e(item.price_day) + '" data-caution="' + e(item.caution) + '" data-max="' + md + '">' +
      '<input type="hidden" name="item_id" value="' + item.id + '">' +
      '<div class="hp" aria-hidden="true"><label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>' +
      '<div class="form-grid">' +
      '<div class="field"><label class="lbl" for="start">Du</label><input id="start" name="start" type="date" min="' + e(today()) + '" value="' + e(v.start) + '" required></div>' +
      '<div class="field"><label class="lbl" for="end">Au (inclus)</label><input id="end" name="end" type="date" min="' + e(today()) + '" value="' + e(v.end) + '" required></div>' +
      (stock > 1 ? '<div class="field"><label class="lbl" for="qty">Quantité</label><input id="qty" name="qty" type="number" min="1" max="' + stock + '" value="' + e(v.qty) + '" inputmode="numeric"></div>' : '') +
      '<div class="field' + (stock > 1 ? '' : ' span2') + '"><label class="lbl" for="phone">Téléphone (facultatif)</label><input id="phone" name="phone" type="tel" maxlength="30" value="' + e(v.phone) + '" autocomplete="tel"></div>' +
      '<div class="field span2"><label class="lbl" for="note">Remarque (facultatif)</label><textarea id="note" name="note" maxlength="300">' + e(v.note) + '</textarea></div>' +
      '</div>' +
      '<p class="muted small">Réservation au nom de <b>' + e(user.name) + '</b> (' + e(user.email) + ').</p>' +
      '<dl class="sum" id="summary" hidden><div><dt>Durée</dt><dd id="sum-days">-</dd></div><div><dt>Location</dt><dd id="sum-total">-</dd></div><div><dt>Caution</dt><dd id="sum-caution">-</dd></div></dl>' +
      '<p class="muted small">Durée maximale : ' + e(plural(md, 'jour')) + ". Tu règles la location et la caution en même temps, juste après l'envoi. L'objet t'est réservé dès la demande.</p>" +
      '<div class="actions"><button class="btn primary" type="submit">Envoyer la demande</button></div>' +
      '</form></section></div>';
    return { title: item.name, active: 'catalogue', html: h, after: initBooking };
  }

  function pageDemande(ref, q) {
    var me = currentUser();
    if (!me) { requireLogin(); return null; }
    var r = null;
    S.reservations.forEach(function (x) { if (x.ref === String(ref).toUpperCase()) r = x; });
    if (!r || (r.user_id !== me.id && !me.is_admin)) {
      return { title: 'Demande introuvable', active: 'compte', html: '<div class="empty"><h1>Demande introuvable</h1><p class="muted">Cette demande n\'existe pas, ou elle n\'est pas liée à ton compte.</p><a class="btn primary" href="#/compte">Mes demandes</a></div>' };
    }
    var links = payLinks(r), status = r.status, steps = stepsFor(r), curIdx = STATUS_ORDER.indexOf(status);
    var needPay = r.total > 0, needCau = r.caution > 0;
    var showPay = status === 'demandee' && (needPay || needCau);
    var owner = r.user_id === me.id;

    var h = '<div class="page-narrow">';
    if (q.get('nouvelle') === '1') h += '<p class="msg ok" role="status">Demande envoyée. Tu la retrouveras à tout moment dans « Mes demandes ».</p>';
    h += '<p>' + (owner || !me.is_admin ? '<a class="btn small" href="#/compte">← Mes demandes</a>' : '<a class="btn small" href="#/bureau?f=all">← Suivi des prêts</a>') + '</p>';
    h += '<section class="panel"><div class="panel-head"><div class="row-title"><h1>' + e(r.item_name) + (r.qty > 1 ? ' <span class="muted">×' + r.qty + '</span>' : '') + '</h1>' + statusPill(r) + '</div>' +
      '<p class="muted">' + e(dateFr(r.start_date, true)) + ' → ' + e(dateFr(r.end_date, true)) + ' · ' + e(plural(r.days, 'jour')) + ' · Réf. <span class="ref">' + e(r.ref) + '</span></p></div>' +
      '<dl class="sum"><div><dt>Location</dt><dd>' + (needPay ? e(money(r.total)) : 'Gratuit') + '</dd></div><div><dt>Caution</dt><dd>' + e(money(r.caution)) + '</dd></div><div><dt>Emprunteur</dt><dd class="dd-text">' + e(r.borrower) + '</dd></div></dl>';
    if (status !== 'annulee') {
      h += '<ol class="steps" aria-label="Avancement">';
      steps.forEach(function (s) {
        var i = STATUS_ORDER.indexOf(s[0]);
        h += '<li class="' + (i < curIdx ? 'done' : i === curIdx ? 'cur' : '') + '">' + e(s[1]) + '</li>';
      });
      h += '</ol>';
    }
    function payButton(kind, label) {
      return links[kind]
        ? '<a class="btn primary" href="' + e(links[kind]) + '" target="_blank" rel="noopener noreferrer">' + label + '</a>'
        : '<button type="button" class="btn primary" data-click="demo-pay" data-kind="' + kind + '">' + label + '</button>';
    }
    if (showPay) {
      h += '<p class="msg warn"><b>À faire maintenant :</b> ' + (needPay && needCau
        ? 'règle la location et dépose la caution en même temps. Ta demande est confirmée quand le bureau a reçu les deux.'
        : needPay ? 'règle la location.' : 'dépose la caution.') + '</p><ol class="todo">';
      if (needPay) {
        h += '<li><div><b>Paie la location : ' + e(money(r.total)) + '</b>' + payButton('helloasso', 'Payer sur HelloAsso') +
          '<span class="muted small">Indique ta référence ' + e(r.ref) + ' si le formulaire le permet.</span></div></li>';
      }
      if (needCau) h += '<li><div><b>Dépose la caution : ' + e(money(r.caution)) + '</b>' + payButton('swikly', 'Déposer la caution sur Swikly') + '</div></li>';
      h += '</ol><p class="msg muted" id="pay-demo" role="status" hidden></p>' +
        '<p class="muted small">Le bureau confirme à la main : la page se met à jour quand c\'est fait. L\'objet t\'est réservé dès maintenant.</p>';
    } else if (status === 'remis') {
      h += '<p class="msg ' + (isLate(r) ? 'bad' : 'ok') + '">' + (isLate(r) ? "L'objet aurait dû être rendu le " + e(dateFr(r.end_date, true)) + ' : rapporte-le dès que possible.' : "L'objet est chez toi. À rendre le " + e(dateFr(r.end_date, true)) + '.') + '</p>';
    } else if (status === 'rendu') {
      h += '<p class="msg ok">Objet rendu, merci !</p>';
    } else if (status === 'annulee') {
      h += '<p class="msg muted">Cette demande est annulée.</p>';
    } else if (status === 'caution' || status === 'payee') {
      h += '<p class="msg muted">Tout est en ordre de ton côté. Le bureau te dit où et quand récupérer l\'objet.</p>';
    }
    var pickup = setting('pickup_info');
    if (pickup && status !== 'annulee' && status !== 'rendu') {
      h += '<div class="note-box"><p class="lbl">Récupération et retour</p><p>' + e(pickup).replace(/\n/g, '<br>') + '</p></div>';
    }
    if (status === 'demandee' && owner) {
      h += '<form class="actions" data-form="cancel-resa" data-confirm="Annuler cette demande ?"><input type="hidden" name="id" value="' + r.id + '"><button class="btn small danger" type="submit">Annuler la demande</button></form>';
    }
    if (me.is_admin && status === 'demandee') {
      h += '<div class="demo-box"><p class="lbl">Astuce démo</p><p class="small">Passe en <b>vue bureau</b> (bandeau en haut) et clique « Paiement et caution reçus » dans le Suivi : cette page se met à jour.</p></div>';
    } else if (!me.is_admin && status === 'demandee') {
      h += '<div class="demo-box"><p class="lbl">Astuce démo</p><p class="small">Passe en <b>vue bureau</b> (bandeau en haut) pour valider cette demande comme le ferait un membre du bureau, puis reviens ici.</p></div>';
    }
    return { title: 'Demande ' + r.ref, active: 'compte', html: h + '</section></div>' };
  }

  function todoHint(r) {
    var pay = r.total > 0, cau = r.caution > 0;
    if (r.status === 'demandee' && (pay || cau)) {
      var parts = [];
      if (pay) parts.push('payer la location (' + money(r.total) + ')');
      if (cau) parts.push('déposer la caution (' + money(r.caution) + ')');
      return 'À faire : ' + parts.join(' et ') + (parts.length > 1 ? ', en même temps.' : '.');
    }
    if (r.status === 'remis') return isLate(r) ? 'À rendre dès que possible.' : 'À rendre le ' + dateFr(r.end_date, true) + '.';
    if (r.status === 'demandee' || r.status === 'payee' || r.status === 'caution') return 'Le bureau prépare ton objet.';
    return '';
  }
  function requestCard(r) {
    var hint = todoHint(r);
    return '<article class="card row"><div class="row-main"><div class="row-title"><h3 class="card-title">' + e(r.item_name) + (r.qty > 1 ? ' <span class="muted">×' + r.qty + '</span>' : '') + '</h3>' + statusPill(r) + '</div>' +
      '<p class="meta-line">' + e(dateFr(r.start_date, true)) + ' → ' + e(dateFr(r.end_date, true)) + ' · ' + e(plural(r.days, 'jour')) + ' · Réf. <span class="ref">' + e(r.ref) + '</span></p>' +
      '<p class="meta-line muted">Location ' + (r.total > 0 ? e(money(r.total)) : 'gratuite') + ' · Caution ' + e(money(r.caution)) + '</p>' +
      (hint ? '<p class="meta-line"><b>' + e(hint) + '</b></p>' : '') +
      '</div><div class="row-act"><a class="btn small' + (hint.indexOf('À faire') === 0 ? ' primary' : '') + ' block" href="#/demande/' + e(r.ref) + '">Ouvrir</a></div></article>';
  }

  function pageCompte() {
    var me = requireLogin();
    if (!me) return null;
    var mine = S.reservations.filter(function (r) { return r.user_id === me.id; })
      .sort(function (a, b) { return a.created_at < b.created_at ? 1 : a.created_at > b.created_at ? -1 : b.id - a.id; });
    var done = function (r) { return r.status === 'rendu' || r.status === 'annulee'; };
    var active = mine.filter(function (r) { return !done(r); }), past = mine.filter(done);
    var h = '<div class="sec-head"><div><h1>Bonjour ' + e(me.name.split(' ')[0]) + '</h1><p class="lede">' + e(me.email) + (me.is_admin ? ' · <a href="#/bureau">Espace bureau</a>' : '') + '</p></div>' +
      '<a class="btn primary small" href="#/">Réserver du matériel</a></div><h2 class="h-section">Mes demandes</h2>';
    if (!mine.length) {
      h += '<div class="empty"><h3>Aucune demande pour l\'instant</h3><p class="muted">Choisis un objet dans le catalogue : ta demande apparaîtra ici, avec les liens de paiement et l\'avancement.</p><a class="btn primary" href="#/">Voir le matériel</a></div>';
    } else {
      h += active.length ? '<div class="list">' + active.map(requestCard).join('') + '</div>' : '<p class="muted">Aucune demande en cours.</p>';
      if (past.length) h += '<h2 class="h-section">Historique</h2><div class="list">' + past.map(requestCard).join('') + '</div>';
    }
    return { title: 'Mon compte', active: 'compte', html: h };
  }

  function pageParametres() {
    var me = requireLogin();
    if (!me) return null;
    var h = '<div class="page-narrow"><div class="sec-head"><div><h1>Paramètres</h1><p class="lede">Gère les informations de ton compte.</p></div></div>' +
      '<form class="form stack" data-form="profile"><section class="panel"><h2 class="h-small">Profil</h2><div class="form-grid">' +
      '<div class="field span2"><label class="lbl" for="name">Prénom et nom</label><input id="name" name="name" type="text" maxlength="80" autocomplete="name" value="' + e(me.name) + '" required></div>' +
      '<div class="field span2"><label class="lbl" for="phone">Téléphone (facultatif)</label><input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="' + e(me.phone) + '"><span class="hint">Pré-rempli quand tu fais une demande.</span></div>' +
      '<div class="field span2"><label class="check"><input type="checkbox" name="notify" value="1"' + (me.notify ? ' checked' : '') + '> Recevoir un e-mail de confirmation à chaque demande</label></div>' +
      '</div><div class="actions"><button class="btn primary" type="submit">Enregistrer</button></div></section></form>' +

      '<form class="form stack" data-form="change-email"><section class="panel"><h2 class="h-small">Adresse e-mail</h2>' +
      '<p class="muted small">Adresse actuelle : <b>' + e(me.email) + '</b>. C\'est ton identifiant de connexion.</p><div class="form-grid">' +
      '<div class="field"><label class="lbl" for="new_email">Nouvelle adresse</label><input id="new_email" name="new_email" type="email" maxlength="120" autocomplete="off" autocapitalize="none" required></div>' +
      '<div class="field"><label class="lbl" for="pw_email">Mot de passe actuel</label><input id="pw_email" name="current_password" type="password" autocomplete="current-password" required></div>' +
      '</div><div class="actions"><button class="btn" type="submit">Changer l\'adresse</button></div></section></form>' +

      '<form class="form stack" data-form="change-password"><section class="panel"><h2 class="h-small">Mot de passe</h2><div class="form-grid">' +
      '<div class="field span2"><label class="lbl" for="current_password">Mot de passe actuel</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>' +
      '<div class="field"><label class="lbl" for="new_password">Nouveau mot de passe (10 caractères minimum)</label><input id="new_password" name="new_password" type="password" autocomplete="new-password" required></div>' +
      '<div class="field"><label class="lbl" for="new_password2">Confirmer</label><input id="new_password2" name="new_password2" type="password" autocomplete="new-password" required></div>' +
      '</div><div class="actions"><button class="btn" type="submit">Changer le mot de passe</button></div></section></form>' +

      '<form class="form stack" data-form="delete-account" data-confirm="Supprimer définitivement ton compte ?"><section class="panel"><h2 class="h-small">Supprimer mon compte</h2>' +
      '<p class="muted small">Ton compte est supprimé. L\'historique de tes anciens prêts reste au bureau, sans lien avec ton compte. Impossible tant que tu as des demandes en cours.</p><div class="form-grid">' +
      '<div class="field"><label class="lbl" for="pw_del">Mot de passe actuel</label><input id="pw_del" name="current_password" type="password" autocomplete="current-password" required></div>' +
      '<div class="field"><label class="check"><input type="checkbox" name="confirm" value="1"> Je confirme vouloir supprimer mon compte</label></div>' +
      '</div><div class="actions"><button class="btn danger" type="submit">Supprimer mon compte</button></div></section></form></div>';
    return { title: 'Paramètres', active: 'parametres', html: h };
  }

  function demoAccountsBox() {
    return '<div class="demo-box"><p class="lbl">Comptes de démonstration</p>' +
      '<p class="small">Étudiant : <code>' + DEMO_ACCOUNTS.etudiant.email + '</code> / <code>' + DEMO_ACCOUNTS.etudiant.password + '</code><br>' +
      'Bureau : <code>' + DEMO_ACCOUNTS.bureau.email + '</code> / <code>' + DEMO_ACCOUNTS.bureau.password + '</code></p>' +
      '<div class="actions"><button class="btn small" type="button" data-click="quick" data-as="etudiant">Entrer comme étudiant</button>' +
      '<button class="btn small" type="button" data-click="quick" data-as="bureau">Entrer comme bureau</button></div></div>';
  }

  function pageConnexion(q) {
    var next = safeNext(q.get('next') || '');
    var me = currentUser();
    if (me) { go(afterLoginTarget(me, next)); return null; }
    var t = takeTransient('login') || { email: '', error: '' };
    var nq = next ? '?next=' + encodeURIComponent(next) : '';
    var h = '<section class="panel narrow"><div class="panel-head"><h1>Connexion</h1><p class="muted">Avec ton adresse e-mail de l\'école et ton mot de passe.</p></div>' +
      (t.error ? '<p class="msg bad" role="alert">' + e(t.error) + '</p>' : '') +
      demoAccountsBox() +
      '<form class="form" data-form="login"><input type="hidden" name="next" value="' + e(next) + '">' +
      '<div class="field"><label class="lbl" for="email">Adresse e-mail</label><input id="email" name="email" type="email" autocomplete="username" autocapitalize="none" value="' + e(t.email) + '" required></div>' +
      '<div class="field"><label class="lbl" for="password">Mot de passe</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>' +
      '<div class="actions"><button class="btn primary" type="submit">Se connecter</button></div></form>' +
      '<p class="muted small">Pas encore de compte ? <a href="#/inscription' + nq + '">Créer un compte</a>. Mot de passe oublié ? Demande au bureau de le réinitialiser.</p></section>';
    return { title: 'Connexion', html: h };
  }

  function pageInscription(q) {
    var next = safeNext(q.get('next') || '');
    var me = currentUser();
    if (me) { go(afterLoginTarget(me, next)); return null; }
    var t = takeTransient('register') || { values: { name: '', email: '' }, errors: [] };
    var domains = domainsLabel();
    var nq = next ? '?next=' + encodeURIComponent(next) : '';
    var h = '<section class="panel narrow"><div class="panel-head"><h1>Créer un compte</h1><p class="muted">' +
      (domains ? "Utilise ton adresse e-mail de l'école (" + e(domains) + '). Un seul compte par adresse.' : 'Un seul compte par adresse e-mail.') + '</p></div>';
    t.errors.forEach(function (err) { h += '<p class="msg bad" role="alert">' + e(err) + '</p>'; });
    h += '<div class="demo-box"><p class="small">Dans la démo, invente une adresse (ex. <code>prenom.nom@ipsa.fr</code>) et n\'utilise pas un vrai mot de passe : la démo ne le protège pas comme un vrai site.</p></div>' +
      '<form class="form" data-form="register"><input type="hidden" name="next" value="' + e(next) + '">' +
      '<div class="hp" aria-hidden="true"><label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>' +
      '<div class="field"><label class="lbl" for="name">Prénom et nom</label><input id="name" name="name" type="text" maxlength="80" autocomplete="name" value="' + e(t.values.name) + '" required></div>' +
      '<div class="field"><label class="lbl" for="email">Adresse e-mail</label><input id="email" name="email" type="email" maxlength="120" autocomplete="username" autocapitalize="none" value="' + e(t.values.email) + '" required></div>' +
      '<div class="field"><label class="lbl" for="password">Mot de passe (10 caractères minimum)</label><input id="password" name="password" type="password" autocomplete="new-password" required></div>' +
      '<div class="field"><label class="lbl" for="password2">Confirmer le mot de passe</label><input id="password2" name="password2" type="password" autocomplete="new-password" required></div>' +
      '<details><summary>Tu es membre du bureau ?</summary><div class="field"><label class="lbl" for="invite">Code d\'invitation</label>' +
      '<input id="invite" name="invite" type="text" autocomplete="off" autocapitalize="characters" placeholder="XXXXX-XXXXX">' +
      '<span class="hint">Le bureau te le remet en main propre. Utilise l\'adresse e-mail pour laquelle il a été créé.</span></div></details>' +
      '<div class="actions"><button class="btn primary" type="submit">Créer mon compte</button></div></form>' +
      '<p class="muted small">Déjà un compte ? <a href="#/connexion' + nq + '">Se connecter</a>.</p></section>';
    return { title: 'Créer un compte', html: h };
  }

  /* ======================= Espace bureau ======================= */

  var FILTERS = {
    todo: ['À préparer', function (r) { return ['demandee', 'payee', 'caution'].indexOf(r.status) >= 0; }],
    out: ['Prêtés', function (r) { return r.status === 'remis'; }],
    late: ['En retard', function (r) { return r.status === 'remis' && r.end_date < today(); }],
    done: ['Terminées', function (r) { return r.status === 'rendu' || r.status === 'annulee'; }],
    all: ['Toutes', function () { return true; }]
  };

  function pageSuivi(q) {
    var admin = requireAdmin();
    if (!admin) return null;
    var f = FILTERS[q.get('f')] ? q.get('f') : 'todo';
    var search = cleanLine(q.get('q') || '', 60);
    var needle = search.toLowerCase();
    var rows = S.reservations.filter(FILTERS[f][1]).filter(function (r) {
      return !needle || [r.borrower, r.email, r.ref, r.item_name].some(function (x) { return String(x).toLowerCase().indexOf(needle) >= 0; });
    });
    var desc = f === 'done' || f === 'all';
    rows.sort(function (a, b) {
      var c = a.start_date < b.start_date ? -1 : a.start_date > b.start_date ? 1 : a.id - b.id;
      return desc ? -c : c;
    });
    var h = '<div class="sec-head"><h1>Suivi des prêts</h1><button class="btn small" type="button" data-click="export">Exporter en CSV</button></div>' +
      '<div class="chips" role="group" aria-label="Filtrer">';
    Object.keys(FILTERS).forEach(function (k) {
      h += '<a class="chip" href="#/bureau?f=' + k + '"' + (f === k ? ' aria-current="true"' : '') + '>' + e(FILTERS[k][0]) + ' <b>' + S.reservations.filter(FILTERS[k][1]).length + '</b></a>';
    });
    h += '</div><form class="search" data-form="search-resa"><input type="hidden" name="f" value="' + e(f) + '">' +
      '<label class="lbl sr" for="q">Rechercher</label><input id="q" name="q" type="search" placeholder="Nom, e-mail, référence, objet" value="' + e(search) + '">' +
      '<button class="btn" type="submit">Chercher</button></form>';
    if (!rows.length) {
      return { title: 'Suivi', admin: true, active: 'suivi', html: h + '<div class="empty"><h2>Rien dans cette liste</h2><p class="muted">' +
        (search ? 'Aucun résultat pour cette recherche.' : f === 'todo' ? 'Aucune demande à préparer pour le moment.' : 'Aucune réservation ne correspond.') + '</p></div>' };
    }
    h += '<div class="list">';
    rows.forEach(function (r) {
      var next = nextStatus(r);
      var events = S.events.filter(function (ev) { return ev.reservation_id === r.id; }).sort(function (a, b) { return a.id - b.id; });
      h += '<article class="card row" id="r' + r.id + '"><div class="row-main"><div class="row-title"><h2 class="card-title">' + e(r.item_name) + (r.qty > 1 ? ' <span class="muted">×' + r.qty + '</span>' : '') + '</h2>' + statusPill(r) + '</div>' +
        '<p class="meta-line">' + e(dateFr(r.start_date, true)) + ' → ' + e(dateFr(r.end_date, true)) + ' · ' + e(plural(r.days, 'jour')) + ' · <b>' + e(r.borrower) + '</b></p>' +
        '<p class="meta-line"><a href="mailto:' + e(r.email) + '">' + e(r.email) + '</a>' + (r.phone ? ' · <a href="tel:' + e(r.phone.replace(/[^0-9+]/g, '')) + '">' + e(r.phone) + '</a>' : '') + '</p>' +
        '<p class="meta-line muted">Réf. <span class="ref">' + e(r.ref) + '</span> · Location ' + e(money(r.total)) + ' · Caution ' + e(money(r.caution)) + (r.note ? ' · « ' + e(r.note) + ' »' : '') + '</p>' +
        '<details id="d' + r.id + '"><summary>Note interne et historique</summary>' +
        '<form class="note-form" data-form="resa-note"><input type="hidden" name="id" value="' + r.id + '">' +
        '<label class="lbl sr" for="n' + r.id + '">Note interne</label><textarea id="n' + r.id + '" name="admin_note" maxlength="500" placeholder="Visible seulement par le bureau">' + e(r.admin_note) + '</textarea>' +
        '<button class="btn small" type="submit">Enregistrer la note</button></form><ul class="plain history">';
      events.forEach(function (ev) {
        h += '<li>' + e((STATUSES[ev.status] || [ev.status])[0]) + ' · ' + e(dateFr(ev.at.slice(0, 10))) + ' ' + e(ev.at.slice(11, 16)) + ' · ' + e(ev.by_user) + '</li>';
      });
      h += '</ul><p class="small"><a href="#/demande/' + e(r.ref) + '">Voir la page de suivi de l\'emprunteur</a></p></details></div><div class="row-act">';
      if (next) {
        h += '<form data-form="resa-next"><input type="hidden" name="id" value="' + r.id + '"><input type="hidden" name="status" value="' + next + '">' +
          '<button class="btn primary small block" type="submit">' + e(NEXT_LABELS[next]) + '</button></form>';
      }
      h += '<form class="inline-status" data-form="resa-status"><input type="hidden" name="id" value="' + r.id + '"><label class="lbl sr" for="s' + r.id + '">Statut</label><select id="s' + r.id + '" name="status">';
      Object.keys(STATUSES).forEach(function (k) { h += '<option value="' + k + '"' + (k === r.status ? ' selected' : '') + '>' + e(STATUSES[k][0]) + '</option>'; });
      h += '</select><button class="btn small" type="submit">OK</button></form>' +
        '<form data-form="resa-delete" data-confirm="Supprimer définitivement cette demande ?"><input type="hidden" name="id" value="' + r.id + '"><button class="btn small danger block" type="submit">Supprimer</button></form>' +
        '</div></article>';
    });
    return { title: 'Suivi', admin: true, active: 'suivi', html: h + '</div>' };
  }

  function pageMateriel(q) {
    if (!requireAdmin()) return null;
    var t = takeTransient('item');
    var editing = null, isNew = false, form = null, errors = [];
    if (t) {
      editing = t.editing; isNew = !editing.id; form = t.form; errors = t.errors;
    } else if (q.get('new') === '1') {
      isNew = true; editing = { id: 0, image: '' };
    } else if (q.get('edit')) {
      var row = findItem(parseInt(q.get('edit'), 10));
      if (row) {
        editing = row;
        form = { name: row.name, description: row.description, stock: String(row.stock), caution: decimalInput(row.caution), price_day: decimalInput(row.price_day), helloasso_url: row.helloasso_url, swikly_url: row.swikly_url };
      }
    }
    form = form || { name: '', description: '', stock: '1', caution: '0', price_day: '0', helloasso_url: '', swikly_url: '' };
    var items = S.items.slice().sort(function (a, b) { return (b.active ? 1 : 0) - (a.active ? 1 : 0) || byName(a, b); });

    var h = '<div class="sec-head"><h1>Matériel</h1>' + (editing ? '' : '<a class="btn primary small" href="#/bureau/materiel?new=1">Ajouter un objet</a>') + '</div>';
    if (editing) {
      var cur = editing.image || '';
      h += '<section class="panel"><h2>' + (isNew ? 'Nouvel objet' : 'Modifier l\'objet') + '</h2>';
      errors.forEach(function (err) { h += '<p class="msg bad" role="alert">' + e(err) + '</p>'; });
      h += '<form class="form" data-form="item-save"><input type="hidden" name="id" value="' + (editing.id || 0) + '"><div class="form-grid">' +
        '<div class="field span2"><label class="lbl" for="name">Nom</label><input id="name" name="name" type="text" maxlength="80" value="' + e(form.name) + '" required></div>' +
        '<div class="field span2"><label class="lbl" for="description">Description (facultatif)</label><input id="description" name="description" type="text" maxlength="200" value="' + e(form.description) + '"></div>' +
        '<div class="field span2"><label class="lbl" for="photo">Photo (facultatif)</label>' +
        (cur ? '<img class="thumb thumb-form" src="' + e(cur) + '" alt="Photo actuelle de l\'objet" width="400" height="300"><label class="check"><input type="checkbox" name="remove_photo" value="1"> Retirer la photo actuelle</label>' : '') +
        '<input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">' +
        '<span class="hint">JPG, PNG ou WebP. Elle est réduite automatiquement. ' + (cur ? 'Choisis un fichier pour remplacer la photo actuelle.' : '') + '</span></div>' +
        '<div class="field"><label class="lbl" for="stock">Exemplaires en stock</label><input id="stock" name="stock" type="text" inputmode="numeric" value="' + e(form.stock) + '" required></div>' +
        '<div class="field"><label class="lbl" for="caution">Caution (€)</label><input id="caution" name="caution" type="text" inputmode="decimal" value="' + e(form.caution) + '" required></div>' +
        '<div class="field"><label class="lbl" for="price_day">Prix par jour (€, 0 = gratuit)</label><input id="price_day" name="price_day" type="text" inputmode="decimal" value="' + e(form.price_day) + '" required></div>' +
        '<div class="field span2"><label class="lbl" for="helloasso_url">Lien HelloAsso de cet objet (facultatif)</label><input id="helloasso_url" name="helloasso_url" type="url" inputmode="url" placeholder="Sinon, le lien général des Réglages est utilisé" value="' + e(form.helloasso_url) + '"></div>' +
        '<div class="field span2"><label class="lbl" for="swikly_url">Lien Swikly de cet objet (facultatif)</label><input id="swikly_url" name="swikly_url" type="url" inputmode="url" placeholder="Utile si chaque caution a son propre lien" value="' + e(form.swikly_url) + '"></div>' +
        '</div><div class="actions"><button class="btn primary" type="submit">Enregistrer</button><a class="btn" href="#/bureau/materiel">Annuler</a></div></form></section>';
    }
    if (!items.length && !editing) {
      h += '<div class="empty"><h2>Aucun objet</h2><p class="muted">Ajoute les objets à prêter avec leur caution et leur stock.</p></div>';
    } else {
      h += '<div class="list">';
      items.forEach(function (it) {
        var nb = S.reservations.filter(function (r) { return r.item_id === it.id; }).length;
        h += '<article class="card row row-img"><div class="thumb-sm">' + (it.image ? '<img src="' + e(it.image) + '" alt="" loading="lazy" width="72" height="72">' : '<span aria-hidden="true">' + e(initial(it.name)) + '</span>') + '</div>' +
          '<div class="row-main"><div class="row-title"><h2 class="card-title">' + e(it.name) + '</h2>' + (it.active ? '' : '<span class="pill p-off">Masqué</span>') + '</div>' +
          '<p class="meta-line muted">Stock ' + it.stock + ' · Caution ' + e(money(it.caution)) + ' · ' + (it.price_day > 0 ? e(money(it.price_day)) + ' / jour' : 'Gratuit') + ' · ' + e(plural(nb, 'réservation')) + '</p>' +
          (it.description ? '<p class="meta-line muted">' + e(it.description) + '</p>' : '') + '</div>' +
          '<div class="row-act"><a class="btn small block" href="#/bureau/materiel?edit=' + it.id + '">Modifier</a>' +
          '<form data-form="item-toggle"><input type="hidden" name="id" value="' + it.id + '"><button class="btn small block" type="submit">' + (it.active ? 'Masquer du site' : 'Réafficher') + '</button></form>' +
          '<form data-form="item-delete" data-confirm="Supprimer cet objet ? S\'il a déjà des réservations, il sera seulement masqué."><input type="hidden" name="id" value="' + it.id + '"><button class="btn small danger block" type="submit">Supprimer</button></form>' +
          '</div></article>';
      });
      h += '</div>';
    }
    return { title: 'Matériel', admin: true, active: 'items', html: h };
  }

  function pageReglages() {
    if (!requireAdmin()) return null;
    var t = takeTransient('settings');
    var val = function (k, d) { return t ? (t.values[k] || '') : setting(k, d); };
    var dom = t ? t.values.email_domains : (setting('email_domains', 'ipsa.fr') === '*' ? '' : setting('email_domains', 'ipsa.fr'));
    var h = '<div class="sec-head"><h1>Réglages</h1></div>';
    (t ? t.errors : []).forEach(function (err) { h += '<p class="msg bad" role="alert">' + e(err) + '</p>'; });
    h += '<form class="form stack" data-form="settings">' +
      '<section class="panel"><h2>Le site</h2><div class="form-grid">' +
      '<div class="field span2"><label class="lbl" for="site_name">Nom du site</label><input id="site_name" name="site_name" type="text" maxlength="60" value="' + e(val('site_name', 'Location BDE')) + '" required></div>' +
      '<div class="field"><label class="lbl" for="max_days">Durée maximale d\'une location (jours)</label><input id="max_days" name="max_days" type="text" inputmode="numeric" value="' + e(val('max_days', '30')) + '" required></div>' +
      '<div class="field span2"><label class="lbl" for="pickup_info">Récupération et retour (affiché à l\'emprunteur)</label><textarea id="pickup_info" name="pickup_info" maxlength="600" placeholder="Ex : permanence du BDE, local B12, du lundi au jeudi de 12h à 14h.">' + e(val('pickup_info')) + '</textarea></div>' +
      '</div></section>' +
      '<section class="panel"><h2>Inscriptions</h2><div class="form-grid"><div class="field span2"><label class="lbl" for="email_domains">Adresses e-mail autorisées à créer un compte</label>' +
      '<input id="email_domains" name="email_domains" type="text" autocomplete="off" autocapitalize="none" placeholder="ipsa.fr" value="' + e(dom) + '">' +
      '<span class="hint">Un ou plusieurs domaines séparés par une virgule. Les sous-domaines sont acceptés (ipsa.fr accepte aussi prenom@etu.ipsa.fr). Laisse vide pour accepter toutes les adresses. Les membres invités au bureau ne sont pas concernés.</span></div></div></section>' +
      '<section class="panel"><h2>Paiement et caution</h2><p class="muted small">Ces liens s\'affichent à l\'emprunteur après sa demande. Chaque objet peut avoir ses propres liens (onglet Matériel) : ils passent alors avant ceux-ci.</p><div class="form-grid">' +
      '<div class="field span2"><label class="lbl" for="helloasso_url">Lien HelloAsso général</label><input id="helloasso_url" name="helloasso_url" type="url" inputmode="url" placeholder="https://www.helloasso.com/…" value="' + e(val('helloasso_url')) + '"></div>' +
      '<div class="field span2"><label class="lbl" for="swikly_url">Lien Swikly général</label><input id="swikly_url" name="swikly_url" type="url" inputmode="url" placeholder="https://…" value="' + e(val('swikly_url')) + '"></div>' +
      '</div></section>' +
      '<section class="panel"><h2>E-mails automatiques</h2>' +
      '<p class="msg muted">Démo : aucun e-mail n\'est envoyé (il faudrait un serveur). Les champs ci-dessous montrent les réglages prévus.</p>' +
      '<p class="muted small">Facultatif. Tant que l\'adresse d\'expédition est vide, aucun e-mail n\'est envoyé : les demandes restent visibles dans « Mes demandes ».</p><div class="form-grid">' +
      '<div class="field"><label class="lbl" for="mail_from">Adresse d\'expédition</label><input id="mail_from" type="email" placeholder="contact@ton-domaine.fr" disabled></div>' +
      '<div class="field"><label class="lbl" for="notify_email">Prévenir le bureau à chaque demande</label><input id="notify_email" type="email" placeholder="bde@ton-domaine.fr" disabled></div>' +
      '</div><h3 class="h-small">Serveur SMTP (recommandé)</h3><div class="form-grid">' +
      '<div class="field"><label class="lbl" for="smtp_host">Serveur SMTP</label><input id="smtp_host" type="text" placeholder="smtp.gmail.com" disabled></div>' +
      '<div class="field"><label class="lbl" for="smtp_port">Port</label><input id="smtp_port" type="text" value="587" disabled></div>' +
      '<div class="field"><label class="lbl" for="smtp_secure">Sécurité</label><select id="smtp_secure" disabled><option>STARTTLS (port 587)</option></select></div>' +
      '<div class="field"><label class="lbl" for="smtp_user">Identifiant</label><input id="smtp_user" type="text" disabled></div>' +
      '<div class="field span2"><label class="lbl" for="smtp_pass">Mot de passe SMTP</label><input id="smtp_pass" type="password" disabled></div>' +
      '</div></section>' +
      '<div class="actions"><button class="btn primary" type="submit">Enregistrer les réglages</button></div></form>';
    return { title: 'Réglages', admin: true, active: 'settings', html: h };
  }

  function pageComptes(q) {
    var me = requireAdmin();
    if (!me) return null;
    var search = cleanLine(q.get('q') || '', 60).toLowerCase();
    var users = S.users.filter(function (u) {
      return !search || u.name.toLowerCase().indexOf(search) >= 0 || u.email.indexOf(search) >= 0;
    }).sort(function (a, b) { return (b.is_admin ? 1 : 0) - (a.is_admin ? 1 : 0) || byName(a, b); });
    var h = '<div class="sec-head"><h1>Comptes</h1><p class="muted">' + e(plural(S.users.length, 'compte')) + '</p></div>' +
      '<section class="panel"><h2 class="h-small">Inviter un membre du bureau</h2>' +
      '<p class="muted small">Saisis son adresse e-mail. Le site génère un code à lui remettre en main propre : à l\'inscription avec cette adresse, il sera reconnu comme membre du bureau et arrivera sur l\'espace administrateur. Sans ce code, personne ne peut s\'inscrire avec cette adresse.</p>' +
      '<form class="form" data-form="invite"><div class="field"><label class="lbl" for="invite_email">Adresse e-mail</label><input id="invite_email" name="email" type="email" maxlength="120" autocomplete="off" autocapitalize="none" required></div>' +
      '<div class="actions"><button class="btn primary" type="submit">Créer l\'invitation</button></div></form>';
    if (S.invites.length) {
      h += '<p class="lbl">Invitations en attente</p><ul class="plain accounts">';
      S.invites.slice().sort(function (a, b) { return a.created_at < b.created_at ? 1 : -1; }).forEach(function (inv) {
        h += '<li><span><b>' + e(inv.email) + '</b> <span class="muted small">invité par ' + e(inv.created_by) + ' le ' + e(dateFr(inv.created_at.slice(0, 10))) + '</span></span>' +
          '<form data-form="cancel-invite" data-confirm="Annuler cette invitation ?"><input type="hidden" name="email" value="' + e(inv.email) + '"><button class="btn small danger" type="submit">Annuler</button></form></li>';
      });
      h += '</ul>';
    }
    h += '</section><div class="spacer"></div><div class="sec-head"><h2 class="h-section">Tous les comptes</h2></div>' +
      '<form class="search" role="search" data-form="search-users"><label class="sr" for="q">Rechercher un compte</label><input id="q" type="search" name="q" value="' + e(q.get('q') || '') + '" placeholder="Nom ou e-mail" autocomplete="off"><button class="btn" type="submit">Chercher</button></form>';
    if (!users.length) return { title: 'Comptes', admin: true, active: 'comptes', html: h + '<div class="empty"><h3>Aucun compte ne correspond</h3></div>' };
    h += '<div class="list">';
    users.forEach(function (u) {
      var isMe = u.id === me.id;
      h += '<article class="card row"><div class="row-main"><div class="row-title"><h3 class="card-title">' + e(u.name) + (isMe ? ' <span class="muted">(toi)</span>' : '') + '</h3>' +
        (u.is_admin ? '<span class="pill p-info">Bureau</span>' : '<span class="pill p-off">Membre</span>') + '</div>' +
        '<p class="meta-line"><span class="sel">' + e(u.email) + '</span></p>' +
        '<p class="meta-line muted">Inscrit le ' + e(dateFr(u.created_at.slice(0, 10))) + (u.last_login ? ' · dernière connexion le ' + e(dateFr(u.last_login.slice(0, 10))) : '') + '</p>' +
        '<details><summary>Nouveau mot de passe</summary><form class="note-form" data-form="reset-password"><input type="hidden" name="id" value="' + u.id + '">' +
        '<label class="sr" for="np' + u.id + '">Nouveau mot de passe pour ' + e(u.name) + '</label><input id="np' + u.id + '" name="new_password" type="text" autocomplete="off" placeholder="10 caractères minimum" required>' +
        '<button class="btn small" type="submit">Enregistrer</button></form></details></div><div class="row-act">';
      if (!isMe) {
        h += '<form data-form="' + (u.is_admin ? 'revoke' : 'grant') + '" data-confirm="' + e((u.is_admin ? "Retirer les droits d'administration à " : "Donner les droits d'administration à ") + u.name + ' ?') + '">' +
          '<input type="hidden" name="id" value="' + u.id + '"><button class="btn small block' + (u.is_admin ? ' danger' : '') + '" type="submit">' + (u.is_admin ? 'Retirer les droits' : 'Donner les droits') + '</button></form>';
      }
      h += '</div></article>';
    });
    return { title: 'Comptes', admin: true, active: 'comptes', html: h + '</div>' };
  }

  function pageIntrouvable() {
    return { title: 'Page introuvable', html: '<div class="empty"><h1>Page introuvable</h1><p class="muted">Cette page n\'existe pas dans la démo.</p><a class="btn primary" href="#/">Voir le matériel</a></div>' };
  }

  /* ======================= Rendu ======================= */

  function render(keepScroll) {
    var r = route(), p = r.parts, page;
    if (!p.length) page = pageCatalogue();
    else if (p[0] === 'reserver' && p[1]) page = pageReserver(parseInt(p[1], 10));
    else if (p[0] === 'demande' && p[1]) page = pageDemande(p[1], r.q);
    else if (p[0] === 'compte') page = pageCompte();
    else if (p[0] === 'parametres') page = pageParametres();
    else if (p[0] === 'connexion') page = pageConnexion(r.q);
    else if (p[0] === 'inscription') page = pageInscription(r.q);
    else if (p[0] === 'bureau' && !p[1]) page = pageSuivi(r.q);
    else if (p[0] === 'bureau' && p[1] === 'materiel') page = pageMateriel(r.q);
    else if (p[0] === 'bureau' && p[1] === 'reglages') page = pageReglages();
    else if (p[0] === 'bureau' && p[1] === 'comptes') page = pageComptes(r.q);
    else page = pageIntrouvable();
    if (!page) return;            // la page a demandé une redirection
    var y = window.scrollY;
    layout(page);
    save();                       // les messages affichés sont consommés
    if (page.after) page.after();
    if (keepScroll) window.scrollTo(0, y);
    var todo = afterRender;
    afterRender = [];
    todo.forEach(function (fn) { fn(); });
  }

  /* ======================= Actions des formulaires ======================= */

  function resaFromForm(fd) { return findResa(parseInt(str(fd, 'id'), 10)); }
  function suiviAnchor(r) { return 'r' + r.id; }

  var forms = {
    reserve: function (fd) {
      var user = currentUser();
      if (!user) return go('/connexion');
      if (str(fd, 'website') !== '') return go('/');
      var item = findItem(parseInt(str(fd, 'item_id'), 10));
      if (!item || !item.active) { flash('bad', "Cet objet n'est plus disponible à la location."); return go('/'); }
      var v = { start: str(fd, 'start'), end: str(fd, 'end'), qty: str(fd, 'qty') || '1', phone: str(fd, 'phone'), note: String(fd.get('note') || '').slice(0, 300) };
      var res = createReservation(item, v, user);
      if (!res.ok) { setTransient('reserve:' + item.id, { values: v, errors: res.errors }); render(); window.scrollTo(0, 0); return; }
      if (user.notify) flash('muted', 'Démo : sur le site final, un e-mail de confirmation partirait à ' + user.email + ' et le bureau serait prévenu.');
      go('/demande/' + res.reservation.ref + '?nouvelle=1');
    },
    'cancel-resa': function (fd) {
      var me = currentUser(), r = resaFromForm(fd);
      if (me && r && r.user_id === me.id && r.status === 'demandee') { changeStatus(r, 'annulee', me.name); flash('ok', 'La demande est annulée.'); }
      refresh();
    },
    login: function (fd) {
      var email = cleanLine(str(fd, 'email'), 120), next = str(fd, 'next');
      var u = findUserByEmail(email);
      if (u && u.password_hash === hashPw(String(fd.get('password') || ''))) { loginUser(u); return go(afterLoginTarget(u, next)); }
      setTransient('login', { email: email, error: 'E-mail ou mot de passe incorrect.' });
      render();
    },
    register: function (fd) {
      if (str(fd, 'website') !== '') return go('/');
      var v = { name: cleanLine(str(fd, 'name'), 80), email: cleanLine(str(fd, 'email'), 120) };
      var res = registerUser(v.name, v.email, String(fd.get('password') || ''), String(fd.get('password2') || ''), str(fd, 'invite'));
      if (!res.ok) { setTransient('register', { values: v, errors: res.errors }); render(); window.scrollTo(0, 0); return; }
      loginUser(res.user);
      flash('ok', res.user.is_admin ? "Compte créé : tu as accès à l'espace bureau." : 'Compte créé. Tu peux maintenant réserver du matériel.');
      go(afterLoginTarget(res.user, str(fd, 'next')));
    },
    profile: function (fd) {
      var me = currentUser();
      var name = cleanLine(str(fd, 'name'), 80), phone = cleanLine(str(fd, 'phone'), 30);
      if (name.length < 2) flash('bad', 'Indique ton prénom et ton nom.');
      else if (phone !== '' && !/^[0-9+().\s-]{6,30}$/.test(phone)) flash('bad', "Le numéro de téléphone n'est pas valide.");
      else { me.name = name; me.phone = phone; me.notify = fd.get('notify') === '1'; flash('ok', 'Paramètres enregistrés.'); }
      refresh();
    },
    'change-email': function (fd) {
      var me = currentUser();
      if (me.password_hash !== hashPw(String(fd.get('current_password') || ''))) { flash('bad', 'Le mot de passe actuel est incorrect.'); return refresh(); }
      var nw = normalizeEmail(str(fd, 'new_email'));
      var taken = S.users.some(function (u) { return u.email === nw && u.id !== me.id; }) || S.invites.some(function (i) { return i.email === nw; });
      if (!validEmail(nw)) flash('bad', 'Indique une adresse e-mail valide.');
      else if (nw === me.email) flash('bad', "C'est déjà ton adresse actuelle.");
      else if (!me.is_admin && !emailDomainOk(nw)) flash('bad', "Cette adresse n'est pas autorisée. Utilise ton adresse e-mail de l'école (" + domainsLabel() + ').');
      else if (taken) flash('bad', 'Cette adresse est déjà utilisée par un autre compte.');
      else { me.email = nw; flash('ok', 'Adresse e-mail changée : connecte-toi désormais avec ' + nw + '.'); }
      refresh();
    },
    'change-password': function (fd) {
      var me = currentUser();
      if (me.password_hash !== hashPw(String(fd.get('current_password') || ''))) { flash('bad', 'Le mot de passe actuel est incorrect.'); return refresh(); }
      var nw = String(fd.get('new_password') || ''), err = validPassword(nw);
      if (err) flash('bad', err);
      else if (nw !== String(fd.get('new_password2') || '')) flash('bad', 'Les deux mots de passe ne correspondent pas.');
      else { me.password_hash = hashPw(nw); flash('ok', 'Mot de passe changé.'); }
      refresh();
    },
    'delete-account': function (fd) {
      var me = currentUser();
      if (me.password_hash !== hashPw(String(fd.get('current_password') || ''))) { flash('bad', 'Le mot de passe actuel est incorrect.'); return refresh(); }
      var open = S.reservations.some(function (r) { return r.user_id === me.id && r.status !== 'rendu' && r.status !== 'annulee'; });
      if (fd.get('confirm') !== '1') flash('bad', 'Coche la case pour confirmer la suppression.');
      else if (open) flash('bad', 'Tu as encore des demandes en cours. Termine-les ou annule-les avant de supprimer ton compte.');
      else if (me.is_admin && adminCount() <= 1) flash('bad', "Tu es le dernier administrateur : donne d'abord les droits à quelqu'un d'autre.");
      else {
        S.reservations.forEach(function (r) { if (r.user_id === me.id) r.user_id = null; });
        S.users = S.users.filter(function (u) { return u.id !== me.id; });
        S.session = null;
        flash('ok', 'Ton compte est supprimé.');
        return go('/');
      }
      refresh();
    },
    'search-resa': function (fd) {
      var q = str(fd, 'q');
      go('/bureau?f=' + encodeURIComponent(str(fd, 'f') || 'todo') + (q ? '&q=' + encodeURIComponent(q) : ''));
    },
    'resa-next': function (fd) {
      var admin = currentUser(), r = resaFromForm(fd), target = str(fd, 'status');
      if (r && target === nextStatus(r) && changeStatus(r, target, admin.name)) flash('ok', r.borrower + ' : ' + STATUSES[target][0] + '.');
      refresh(r && suiviAnchor(r));
    },
    'resa-status': function (fd) {
      var admin = currentUser(), r = resaFromForm(fd), target = str(fd, 'status');
      if (r && changeStatus(r, target, admin.name)) flash('ok', r.borrower + ' : statut « ' + STATUSES[target][0] + ' ».');
      refresh(r && suiviAnchor(r));
    },
    'resa-note': function (fd) {
      var r = resaFromForm(fd);
      if (r) {
        r.admin_note = String(fd.get('admin_note') || '').slice(0, 500);
        r.updated_at = nowStr();
        flash('ok', 'Note enregistrée.');
        afterRender.push(function () { var d = document.getElementById('d' + r.id); if (d) d.open = true; });
      }
      refresh(r && suiviAnchor(r));
    },
    'resa-delete': function (fd) {
      var r = resaFromForm(fd);
      if (r) {
        S.events = S.events.filter(function (ev) { return ev.reservation_id !== r.id; });
        S.reservations = S.reservations.filter(function (x) { return x !== r; });
        flash('ok', 'Demande supprimée.');
      }
      refresh();
    },
    'item-save': function (fd, formEl) {
      var id = parseInt(str(fd, 'id'), 10) || 0;
      var existing = id ? findItem(id) : null;
      var form = {};
      ['name', 'description', 'stock', 'caution', 'price_day', 'helloasso_url', 'swikly_url'].forEach(function (k) { form[k] = str(fd, k); });
      var name = cleanLine(form.name, 80), desc = cleanLine(form.description, 200);
      var stock = /^\d+$/.test(form.stock) ? parseInt(form.stock, 10) : null;
      var caution = parseDecimal(form.caution), price = parseDecimal(form.price_day);
      var ha = form.helloasso_url === '' ? '' : safeUrl(form.helloasso_url);
      var sw = form.swikly_url === '' ? '' : safeUrl(form.swikly_url);
      var errors = [];
      if (name.length < 2) errors.push("Donne un nom à l'objet.");
      if (stock === null || stock > 999) errors.push("Le stock doit être un nombre entier (0 pour retirer l'objet de la location).");
      if (caution === null || caution < 0 || caution > 100000) errors.push('La caution doit être un montant positif ou nul.');
      if (price === null || price < 0 || price > 100000) errors.push('Le prix par jour doit être un montant positif ou nul.');
      if (form.helloasso_url !== '' && ha === '') errors.push('Le lien HelloAsso doit commencer par https://');
      if (form.swikly_url !== '' && sw === '') errors.push('Le lien Swikly doit commencer par https://');
      var editing = existing || { id: 0, image: '' };
      var fail = function (errs) { setTransient('item', { editing: editing, form: form, errors: errs }); render(); window.scrollTo(0, 0); };
      if (errors.length) return fail(errors);

      var file = formEl.querySelector('#photo').files[0];
      var photo = file ? readImage(file) : Promise.resolve(null);
      return photo.then(function (img) {
        var image = existing ? existing.image : '';
        if (img) image = img;
        else if (fd.get('remove_photo') === '1') image = '';
        var it = existing;
        if (!it) {
          it = { id: nextId('item'), active: true, created_at: nowStr() };
          S.items.push(it);
        }
        it.name = name; it.description = desc; it.stock = stock; it.caution = round2(caution); it.price_day = round2(price);
        it.helloasso_url = ha; it.swikly_url = sw; it.image = image;
        flash('ok', '« ' + name + (existing ? ' » mis à jour.' : ' » ajouté au catalogue.'));
        if (!save()) flash('warn', "Le navigateur n'a plus de place pour garder cette photo : elle sera perdue au rechargement. Essaie une image plus légère.");
        go('/bureau/materiel');
      }, function (err) { fail([err.message]); });
    },
    'item-toggle': function (fd) {
      var it = findItem(parseInt(str(fd, 'id'), 10));
      if (it) { it.active = !it.active; flash('ok', 'Visibilité modifiée.'); }
      refresh();
    },
    'item-delete': function (fd) {
      var it = findItem(parseInt(str(fd, 'id'), 10));
      if (it) {
        if (S.reservations.some(function (r) { return r.item_id === it.id; })) {
          it.active = false;
          flash('ok', "Cet objet a déjà des réservations : il est masqué plutôt que supprimé, pour garder l'historique.");
        } else {
          S.items = S.items.filter(function (x) { return x !== it; });
          flash('ok', 'Objet supprimé.');
        }
      }
      refresh();
    },
    settings: function (fd) {
      var v = {};
      ['site_name', 'max_days', 'helloasso_url', 'swikly_url', 'email_domains', 'pickup_info'].forEach(function (k) { v[k] = str(fd, k); });
      var errors = [], domains = [];
      var siteName = cleanLine(v.site_name, 60);
      v.email_domains.toLowerCase().split(/[\s,;]+/).filter(Boolean).forEach(function (d) {
        d = d.replace(/^@+/, '');
        if (!/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/.test(d)) errors.push('Le domaine « ' + d + ' » n\'est pas valide (exemple : ipsa.fr).');
        else if (domains.indexOf(d) < 0) domains.push(d);
      });
      if (!siteName) errors.push('Le nom du site ne peut pas être vide.');
      if (!/^\d+$/.test(v.max_days) || +v.max_days < 1 || +v.max_days > 365) errors.push('La durée maximale doit être comprise entre 1 et 365 jours.');
      if (v.helloasso_url && !safeUrl(v.helloasso_url)) errors.push('Le lien HelloAsso doit commencer par https://');
      if (v.swikly_url && !safeUrl(v.swikly_url)) errors.push('Le lien Swikly doit commencer par https://');
      if (errors.length) { setTransient('settings', { values: v, errors: errors }); render(); window.scrollTo(0, 0); return; }
      S.settings.site_name = siteName;
      S.settings.max_days = String(parseInt(v.max_days, 10));
      S.settings.helloasso_url = safeUrl(v.helloasso_url);
      S.settings.swikly_url = safeUrl(v.swikly_url);
      S.settings.email_domains = domains.length ? domains.join(', ') : '*';
      S.settings.pickup_info = String(fd.get('pickup_info') || '').trim().slice(0, 600);
      flash('ok', 'Réglages enregistrés.');
      refresh();
      window.scrollTo(0, 0);
    },
    invite: function (fd) {
      var me = currentUser(), email = normalizeEmail(str(fd, 'email')), existing = findUserByEmail(email);
      if (!validEmail(email)) flash('bad', 'Indique une adresse e-mail valide.');
      else if (existing && existing.is_admin) flash('bad', 'Cette personne est déjà membre du bureau.');
      else if (existing) flash('bad', 'Cette personne a déjà un compte : utilise « Donner les droits » dans la liste des comptes.');
      else {
        var code = makeInviteCode();
        S.invites = S.invites.filter(function (i) { return i.email !== email; });
        S.invites.push({ email: email, code_hash: hashPw(normalizeCode(code)), created_by: me.name, created_at: nowStr() });
        flash('ok', 'Invitation créée pour ' + email + '. Code à lui remettre en main propre : ' + code + ' (il ne sera plus affiché ; pour en obtenir un autre, refais une invitation).');
      }
      refresh();
      window.scrollTo(0, 0);
    },
    'cancel-invite': function (fd) {
      var email = normalizeEmail(str(fd, 'email'));
      S.invites = S.invites.filter(function (i) { return i.email !== email; });
      flash('ok', 'Invitation annulée.');
      refresh();
    },
    grant: function (fd) {
      var u = findUser(parseInt(str(fd, 'id'), 10));
      if (u) { u.is_admin = true; flash('ok', "Droits d'administration donnés."); }
      refresh();
    },
    revoke: function (fd) {
      var me = currentUser(), u = findUser(parseInt(str(fd, 'id'), 10));
      if (!u) return refresh();
      if (u.id === me.id) flash('bad', 'Tu ne peux pas te retirer toi-même les droits.');
      else if (adminCount() <= 1) flash('bad', 'Il doit rester au moins un administrateur.');
      else { u.is_admin = false; flash('ok', "Droits d'administration retirés."); }
      refresh();
    },
    'reset-password': function (fd) {
      var u = findUser(parseInt(str(fd, 'id'), 10)), pw = String(fd.get('new_password') || ''), err = validPassword(pw);
      if (err) flash('bad', err);
      else if (u) { u.password_hash = hashPw(pw); flash('ok', 'Nouveau mot de passe enregistré. Communique-le à la personne : elle pourra le changer dans « Paramètres ».'); }
      refresh();
      window.scrollTo(0, 0);
    },
    'search-users': function (fd) {
      var q = str(fd, 'q');
      go('/bureau/comptes' + (q ? '?q=' + encodeURIComponent(q) : ''));
    }
  };

  var clicks = {
    logout: function () { S.session = null; go('/'); },
    quick: function (btn) {
      var u = demoUser(btn.getAttribute('data-as'));
      loginUser(u);
      var r = route();
      var next = r.path === '/connexion' || r.path === '/inscription' ? safeNext(r.q.get('next') || '') : '';
      if (next && !u.is_admin && next.indexOf('/bureau') === 0) next = '';
      go(afterLoginTarget(u, next));
    },
    reset: function () {
      askConfirm('Remettre la démo dans son état de départ ? Tes essais seront effacés.').then(function (ok) {
        if (!ok) return;
        storageDel();
        S = seed();
        flash('ok', 'Démo réinitialisée.');
        go('/');
      });
    },
    'demo-pay': function (btn) {
      var box = document.getElementById('pay-demo');
      if (!box) return;
      box.hidden = false;
      box.textContent = btn.getAttribute('data-kind') === 'helloasso'
        ? "Démo : ici, le bouton ouvre le formulaire de paiement HelloAsso du BDE (lien à coller dans Bureau → Réglages). Le bureau vérifie ensuite le paiement sur HelloAsso et le valide dans le Suivi."
        : "Démo : ici, le bouton ouvre la page de caution Swikly du BDE (lien à coller dans Bureau → Réglages). La caution est une empreinte bancaire : elle n'est encaissée qu'en cas de problème.";
    },
    'export': function () { exportCsv(); }
  };

  /* ======================= Export CSV ======================= */

  function csvCell(v) {
    var s = String(v == null ? '' : v);
    if (s !== '' && '=+-@\t\r'.indexOf(s.charAt(0)) >= 0) s = "'" + s;
    return '"' + s.replace(/"/g, '""') + '"';
  }
  function exportCsv() {
    var dec = function (n) { return String(Number(n) || 0).replace('.', ','); };
    var lines = [['Réf.', 'Objet', 'Quantité', 'Début', 'Fin', 'Jours', 'Emprunteur', 'E-mail', 'Téléphone', 'Statut', 'Location (€)', 'Caution (€)', 'Remarque', 'Note interne', 'Demandée le']];
    S.reservations.slice().sort(function (a, b) { return a.start_date < b.start_date ? -1 : a.start_date > b.start_date ? 1 : a.id - b.id; }).forEach(function (r) {
      lines.push([r.ref, r.item_name, r.qty, r.start_date, r.end_date, r.days, r.borrower, r.email, r.phone, (STATUSES[r.status] || [r.status])[0],
        dec(r.total), dec(r.caution), r.note, r.admin_note, r.created_at.slice(0, 10)]);
    });
    var csv = '﻿' + lines.map(function (l) { return l.map(csvCell).join(';'); }).join('\r\n') + '\r\n';
    var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    var a = document.createElement('a');
    a.href = url;
    a.download = 'location-bde-' + today() + '.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }

  /* ======================= Photos (réduites dans le navigateur) ======================= */

  function readImage(file) {
    return new Promise(function (resolve, reject) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return reject(new Error('La photo doit être au format JPG, PNG ou WebP.'));
      if (file.size > 8 * 1024 * 1024) return reject(new Error('La photo est trop lourde (8 Mo maximum dans la démo).'));
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () {
        var k = Math.min(1, 800 / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(img.naturalWidth * k));
        c.height = Math.max(1, Math.round(img.naturalHeight * k));
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        var data = c.toDataURL('image/webp', 0.8);
        if (data.indexOf('data:image/webp') !== 0) data = c.toDataURL('image/jpeg', 0.8);
        resolve(data);
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('Cette image est illisible. Essaie un autre fichier.')); };
      img.src = url;
    });
  }

  /* ======================= Calendrier et aperçu du prix ======================= */

  function initBooking() {
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
    var fmt = function (n) { return money(n); };

    function parse(v) {
      var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || '');
      return m ? Date.UTC(+m[1], +m[2] - 1, +m[3]) : NaN;
    }
    function update() {
      var a = parse(start.value), b = parse(end.value);
      var q = qty ? Math.max(1, parseInt(qty.value, 10) || 1) : 1;
      if (isNaN(a) || isNaN(b) || b < a) { box.hidden = true; return; }
      var days = Math.round((b - a) / DAY) + 1;
      box.hidden = false;
      if (days > max) {
        outDays.textContent = days + ' jours (max ' + max + ')';
        outTotal.textContent = outCaution.textContent = '-';
        return;
      }
      outDays.textContent = days + (days > 1 ? ' jours' : ' jour');
      outTotal.textContent = price > 0 ? fmt(price * days * q) : 'Gratuit';
      outCaution.textContent = fmt(caution * q);
    }
    start.addEventListener('change', function () {
      if (!end.value || parse(end.value) < parse(start.value)) end.value = start.value;
      end.min = start.value;
      update();
    });
    end.addEventListener('change', update);
    if (qty) qty.addEventListener('input', update);
    update();

    var cal = document.getElementById('cal');
    if (!cal) return;
    var stock = parseInt(cal.getAttribute('data-stock'), 10) || 1;
    var todayIso = cal.getAttribute('data-today');
    var used = {};
    try { used = JSON.parse(cal.getAttribute('data-used') || '{}') || {}; } catch (_) { used = {}; }
    var msg = document.getElementById('cal-msg');
    var MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    var DOW = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'];
    var tp = parse(todayIso);
    var v0 = new Date(tp);
    var view = { y: v0.getUTCFullYear(), m: v0.getUTCMonth() };
    var minView = { y: view.y, m: view.m };
    var picking = false;

    function iso(t) { return isoUTC(new Date(t)); }
    function wantedQty() { return qty ? Math.max(1, parseInt(qty.value, 10) || 1) : 1; }
    function freeOn(t) { return stock - (used[iso(t)] || 0); }
    function rangeProblem(a, b) {
      var days = Math.round((b - a) / DAY) + 1, q = wantedQty();
      if (days > max) return 'La durée maximale est de ' + max + ' jours.';
      for (var t = a; t <= b; t += DAY) {
        if (freeOn(t) < q) return "Pas assez d'exemplaires libres le " + new Date(t).getUTCDate() + ' ' + MONTHS[new Date(t).getUTCMonth()] + '.';
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
      if (problem) { say(problem + " Choisis d'autres dates.", true); return; }
      var f = minFree(a, b);
      if (qty) { qty.max = String(f); if (wantedQty() > f) qty.value = String(Math.max(1, f)); }
      say(stock > 1 ? 'Sur ces dates, ' + f + ' exemplaire' + (f > 1 ? 's sont libres' : ' est libre') + ' (sur ' + stock + ').' : 'Ces dates sont libres.', false);
    }
    function draw() {
      var a = parse(start.value), b = parse(end.value), q = wantedQty();
      var first = Date.UTC(view.y, view.m, 1);
      var lead = (new Date(first).getUTCDay() + 6) % 7;
      var nDays = new Date(Date.UTC(view.y, view.m + 1, 0)).getUTCDate();
      var atMin = view.y === minView.y && view.m === minView.m;
      var atMax = (view.y - minView.y) * 12 + (view.m - minView.m) >= 12;
      var h = '<div class="cal-head"><button type="button" class="btn small" data-nav="-1" aria-label="Mois précédent"' + (atMin ? ' disabled' : '') + '>‹</button>' +
        '<b>' + MONTHS[view.m] + ' ' + view.y + '</b>' +
        '<button type="button" class="btn small" data-nav="1" aria-label="Mois suivant"' + (atMax ? ' disabled' : '') + '>›</button></div>' +
        '<div class="cal-grid" role="grid">';
      for (var i = 0; i < 7; i++) h += '<span class="cal-dow">' + DOW[i] + '</span>';
      for (i = 0; i < lead; i++) h += '<span></span>';
      for (var dd = 1; dd <= nDays; dd++) {
        var t = Date.UTC(view.y, view.m, dd), f = freeOn(t), past = t < tp;
        var blocked = past || f < q;
        var cls = 'cal-day' + (blocked ? ' is-blocked' : '') + (!blocked && f < stock ? ' is-part' : '') + (t === tp ? ' is-today' : '');
        if (!isNaN(a) && !isNaN(b) && t >= a && t <= b) cls += ' is-sel' + (t === a ? ' is-first' : '') + (t === b ? ' is-last' : '');
        var label = dd + ' ' + MONTHS[view.m] + (past ? ', passé' : f <= 0 ? ', complet' : ', ' + f + ' libre' + (f > 1 ? 's' : ''));
        h += '<button type="button" class="' + cls + '" data-t="' + t + '"' + (blocked ? ' disabled' : '') + ' aria-label="' + label + '">' +
          '<span>' + dd + '</span>' + (!past && stock > 1 ? '<small>' + Math.max(0, f) + '</small>' : '') + '</button>';
      }
      cal.innerHTML = h + '</div>';
    }
    cal.addEventListener('click', function (ev) {
      var nav = ev.target.closest('[data-nav]');
      if (nav) {
        var n = view.m + parseInt(nav.getAttribute('data-nav'), 10);
        view = { y: view.y + Math.floor(n / 12), m: ((n % 12) + 12) % 12 };
        draw();
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
      draw();
      describe();
    });
    function resync() { draw(); describe(); }
    start.addEventListener('change', resync);
    end.addEventListener('change', resync);
    if (qty) qty.addEventListener('input', resync);

    cal.hidden = false;
    var s0 = parse(start.value);
    if (!isNaN(s0)) { var d0 = new Date(s0); view = { y: d0.getUTCFullYear(), m: d0.getUTCMonth() }; }
    draw();
    var s1 = parse(start.value), e1 = parse(end.value);
    if (!isNaN(s1) && !isNaN(e1) && s1 === tp && e1 === tp && freeOn(tp) < wantedQty()) say("Aujourd'hui est complet : choisis tes dates dans le calendrier.", true);
  }

  /* ======================= Confirmation (remplace window.confirm, bloqué dans certains cadres) ======================= */

  function askConfirm(text) {
    return new Promise(function (resolve) {
      var back = document.createElement('div');
      back.className = 'confirm-back';
      back.innerHTML = '<div class="confirm-box" role="alertdialog" aria-modal="true" aria-labelledby="confirm-text">' +
        '<p id="confirm-text">' + e(text) + '</p><div class="actions">' +
        '<button type="button" class="btn" data-ans="0">Annuler</button>' +
        '<button type="button" class="btn primary" data-ans="1">Confirmer</button></div></div>';
      var before = document.activeElement;
      function done(v) {
        document.removeEventListener('keydown', onKey);
        back.remove();
        if (before && before.focus) before.focus();
        resolve(v);
      }
      function onKey(ev) { if (ev.key === 'Escape') done(false); }
      back.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-ans]');
        if (b) done(b.getAttribute('data-ans') === '1');
        else if (ev.target === back) done(false);
      });
      document.addEventListener('keydown', onKey);
      document.body.appendChild(back);
      back.querySelector('[data-ans="1"]').focus();
    });
  }

  /* ======================= Démarrage ======================= */

  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    var name = f && f.getAttribute && f.getAttribute('data-form');
    if (!name || !forms[name]) return;
    ev.preventDefault();
    var fd = new FormData(f);
    var confirmMsg = f.getAttribute('data-confirm');
    (confirmMsg ? askConfirm(confirmMsg) : Promise.resolve(true)).then(function (ok) {
      if (ok) return forms[name](fd, f);
    }).catch(function (err) { console.error(err); });
  });
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest) return;
    var link = ev.target.closest('a[href^="#/"]');
    if (link && !ev.ctrlKey && !ev.metaKey && !ev.shiftKey && ev.button === 0) {
      ev.preventDefault();
      go(link.getAttribute('href').slice(1));
      return;
    }
    var el = ev.target.closest('[data-click]');
    if (!el || !clicks[el.getAttribute('data-click')]) return;
    ev.preventDefault();
    clicks[el.getAttribute('data-click')](el);
  });
  window.addEventListener('hashchange', function () {
    var h = hashPath() || '/';
    if (h === memPath) return;       // changement fait par go() : déjà affiché
    memPath = h;
    render();
    window.scrollTo(0, 0);
  });

  load();
  memPath = hashPath() || '/';
  render();
})();
