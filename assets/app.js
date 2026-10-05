/* ManRisk ERM — purwarupa UI/UX (vanilla JS, tanpa build step). */
(function () {
  'use strict';
  const D = window.D;

  /* ======================= Utilitas ======================= */
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  const fmtDate = (iso) => { if (!iso || iso === '—') return '—'; const [y, m, d] = iso.split('-'); return `${+d} ${MON[+m - 1]} ${y}`; };
  const fmtNum = (n, dg = 0) => Number(n).toLocaleString('id-ID', { minimumFractionDigits: dg, maximumFractionDigits: dg });
  const rp = (jt) => (jt >= 1000 ? `Rp${fmtNum(jt / 1000, 2)} M` : `Rp${fmtNum(jt)} jt`);
  const sc = (a) => a[0] * a[1];
  const level = (s) => D.LEVELS.find((l) => s >= l.min && s <= l.max);
  const lvChip = (s) => { const l = level(s); return `<span class="lv lv-${l.k}"><i></i>${l.n}</span>`; };
  const scoreB = (s) => `<span class="score lv-${level(s).k}" title="Skor ${s} · ${level(s).n}">${s}</span>`;
  const tax = (cat) => D.TAXONOMY.find((t) => t.k === cat) || { app: 6, tol: 9 };
  const unitName = (i) => D.UNITS[i] || i;
  const person = (k) => D.PEOPLE[k] || { n: k, j: '' };
  const riskById = (id) => S.risks.find((r) => r.id === id);
  const days = (a, b) => Math.round((new Date(a) - new Date(b)) / 864e5);
  const initials = (n) => n.split(' ').filter((w) => /^[A-Z]/.test(w)).slice(0, 2).map((w) => w[0]).join('');

  function evalStatus(s, cat) {
    const t = tax(cat);
    if (s >= 20) return { n: 'Kritis', c: 'bad' };
    if (s >= 16) return { n: 'Perlu Eskalasi', c: 'bad' };
    if (s > t.tol) return { n: 'Perlu Penanganan', c: 'warn' };
    if (s > t.app) return { n: 'Dipantau', c: 'run' };
    return { n: 'Dapat Diterima', c: 'ok' };
  }
  const evalPill = (s, cat) => { const e = evalStatus(s, cat); return `<span class="pill ${e.c}">${e.n}</span>`; };

  function actStatus(a) {
    if (a.cancel) return { n: 'Dibatalkan', c: 'off', k: 'cancel' };
    if (a.prog >= 100) return { n: 'Selesai', c: 'ok', k: 'done' };
    if (a.due < D.TODAY) return { n: 'Terlambat', c: 'bad', k: 'late' };
    if (a.prog === 0) return { n: 'Belum Mulai', c: '', k: 'todo' };
    return { n: 'Berjalan', c: 'run', k: 'run' };
  }
  const riskStatusPill = (st) => {
    const m = { 'Draft': '', 'Menunggu Persetujuan': 'warn', 'Dalam Penanganan': 'run', 'Dipantau': 'ok', 'Ditutup': '' };
    return `<span class="pill ${m[st] || ''}">${esc(st)}</span>`;
  };
  const trendB = (t) => ({
    up: '<span class="trend up" title="Meningkat">▲ Naik</span>',
    down: '<span class="trend down" title="Menurun">▼ Turun</span>',
    flat: '<span class="trend flat" title="Stabil">▶ Stabil</span>'
  }[t]);
  function kriStatus(k) {
    const v = k.v[k.v.length - 1];
    if (k.inv) return v < k.r ? { n: 'Kritis', c: 'bad', col: 'var(--lv-vh)' } : v < k.g ? { n: 'Waspada', c: 'warn', col: 'var(--lv-m)' } : { n: 'Normal', c: 'ok', col: 'var(--lv-l)' };
    return v > k.r ? { n: 'Kritis', c: 'bad', col: 'var(--lv-vh)' } : v > k.g ? { n: 'Waspada', c: 'warn', col: 'var(--lv-m)' } : { n: 'Normal', c: 'ok', col: 'var(--lv-l)' };
  }
  const kriVal = (k, v) => fmtNum(v, k.fmt);

  /* ======================= Ikon ======================= */
  const IC = {
    grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    pulse: '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
    gauge: '<path d="M4 18a8 8 0 1 1 16 0"/><path d="m12 18 4-6"/>',
    compass: '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    list: '<path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
    scale: '<path d="M12 3v18M7 21h10M5 7h14"/><path d="M5 7 2 14a3.5 3.5 0 0 0 6 0zM19 7l-3 7a3.5 3.5 0 0 0 6 0z"/>',
    refresh: '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
    tasks: '<path d="m9 11 3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    up: '<path d="m3 17 6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
    shield: '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
    alert: '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
    flag: '<path d="M5 21V4h11l-2 4 2 4H5"/>',
    layers: '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
    book: '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5M8 7h7"/>',
    inbox: '<path d="M3 13h5l2 3h4l2-3h5"/><path d="M5.5 5h13L21 13v6H3v-6z"/>',
    file: '<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
    folder: '<path d="M3 6h6l2 2h10v11H3z"/>',
    users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    spark: '<path d="M12 3c.6 4.5 2.5 6.4 7 7-4.5.6-6.4 2.5-7 7-.6-4.5-2.5-6.4-7-7 4.5-.6 6.4-2.5 7-7z"/><path d="M19 15c.3 1.8 1 2.6 2.8 2.9-1.8.3-2.5 1-2.8 2.8-.3-1.8-1-2.5-2.8-2.8 1.8-.3 2.5-1.1 2.8-2.9z"/>',
    bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
    x: '<path d="M6 6l12 12M18 6 6 18"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    down: '<path d="M12 4v12m0 0-5-5m5 5 5-5M5 20h14"/>',
    moon: '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/>',
    target: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    bolt: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    repeat: '<path d="M17 2l4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14M7 22l-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
    send: '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
    upload: '<path d="M12 16V4m0 0-5 5m5-5 5 5M5 20h14"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    key: '<circle cx="8" cy="15" r="4"/><path d="m10.9 12.1 9.1-9.1M15 7l3 3M12 10l3 3"/>',
    copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>'
  };
  const ic = (n) => `<svg class="ic" viewBox="0 0 24 24" aria-hidden="true">${IC[n] || ''}</svg>`;

  /* ======================= State ======================= */
  /* AUTH terisi bila server mengonfirmasi sesi (index.php + api/session.php). Null = mode demo (pratinjau/file lokal). */
  let AUTH = null;
  let lastActivity = Date.now();
  const S = {
    role: 'Risk Manager',
    risks: D.RISKS.map((r) => Object.assign({}, r)),
    actions: D.ACTIONS.map((a) => Object.assign({}, a)),
    controls: D.CONTROLS.map((c) => Object.assign({}, c)),
    approvals: D.APPROVALS.map((a) => Object.assign({ st: 'Proses' }, a)),
    audit: D.AUDIT.slice(),
    docs: D.DOCS.slice(),
    heat: 'res',
    reg: { q: '', lv: '', unit: '', cat: '', st: '', cell: '', sort: 'res' },
    dtab: 'sum',
    treatView: 'list', treatFilter: '',
    ctxTab: 'scope',
    anaL: 4, anaI: 5, anaCat: 'Teknologi Informasi',
    wz: null,
    ai: null, aiBusy: false,
    wfSel: 'WF-0410',
    incSel: 'INC-2026-014',
    objSel: 'SS-1',
    orgTab: 'tree',
    report: null, reportBusy: false, reportPeriod: 'TW III 2026',
    revPeriod: 'TW III 2026',
    auditQ: '',
    nav: false, overlay: null, ctrlSel: null, forcePwd: false,
    um: { tab: 'list', list: null, log: null, roles: [], me: null, busy: false, err: '', edit: null, result: null, q: '' },
    lastRoute: null,
    pg: { reg: 1, act: 1, audit: 1 }
  };
  const roleCfg = () => D.ROLES[S.role];
  const canWrite = () => roleCfg().write;
  const canApprove = () => ['Super Admin', 'Risk Manager', 'Risk Owner', 'Management'].includes(S.role);
  const ME = () => (AUTH ? AUTH.user.name : S.role === 'Risk Manager' ? 'Osmond' : ({ 'Super Admin': 'Admin Sistem', 'Risk Administrator': 'Yudi Pratama', 'Risk Officer': 'Fajar Nugroho', 'Risk Owner': 'Hendra Wijaya', 'Management': 'Ir. Taufik Rahman', 'Auditor': 'Nurul Hidayah' })[S.role]);
  const nowStamp = () => '2026-10-05 ' + new Date().toTimeString().slice(0, 5);
  function log(a, ref, f, p, n) { S.audit.unshift({ u: ME(), a, ref, f, p, n, t: nowStamp(), ip: '10.12.4.21' }); }

  function newWizard(example) {
    return {
      step: 1, aiOpen: false, example: !!example,
      d: example ? {
        name: 'Keterlambatan pembayaran kepada vendor', unit: 1, proc: 'Pembayaran vendor', obj: 'SS-2', cat: 'Keuangan', src1: 'Internal', src2: 'Process',
        cause: 'dokumen tagihan vendor diverifikasi manual dan sering tidak lengkap', event: 'keterlambatan pembayaran melebihi 30 hari kalender', impact: 'denda keterlambatan, sengketa kontrak, dan terganggunya pasokan layanan',
        iL: 4, iI: 3, rL: 3, rI: 3, treat: 'Kurangi', owner: 'keu', due: '2026-12-31',
        actions: [{ t: 'Digitalisasi checklist kelengkapan tagihan', pic: 'Subbag Perbendaharaan', due: '2026-11-30' }, { t: 'Notifikasi otomatis tagihan mendekati jatuh tempo', pic: 'Tim Aplikasi', due: '2026-12-15' }]
      } : { name: '', unit: 0, proc: '', obj: 'SS-1', cat: 'Operasional', src1: 'Internal', src2: 'Process', cause: '', event: '', impact: '', iL: 3, iI: 3, rL: 2, rI: 3, treat: 'Kurangi', owner: 'ti', due: '2026-12-31', actions: [{ t: '', pic: '', due: '' }] }
    };
  }
  S.wz = newWizard(true);

  /* Ringkasan angka langsung dari data (ikut berubah saat risiko baru diajukan) */
  function stats() {
    const rs = S.risks, acts = S.actions, live = acts.filter((a) => !a.cancel);
    const vh = rs.filter((r) => sc(r.res) >= 16);
    const byUnit = {}; vh.forEach((r) => { byUnit[r.unit] = (byUnit[r.unit] || 0) + 1; });
    const top = Object.entries(byUnit).sort((a, b) => b[1] - a[1])[0] || ['0', 0];
    const late = acts.filter((a) => actStatus(a).k === 'late');
    const krit = D.KRIS.filter((k) => kriStatus(k).n === 'Kritis');
    const inc = D.INCIDENTS.filter((i) => i.date.startsWith('2026'));
    const big = inc.slice().sort((a, b) => b.loss - a.loss)[0];
    return {
      total: rs.length, vh: vh.length, topUnit: unitName(+top[0]), topUnitN: top[1], late, krit, inc, big,
      loss: inc.reduce((t, i) => t + i.loss, 0), acts: acts.length, done: acts.filter((a) => actStatus(a).k === 'done').length,
      mitig: Math.round(live.reduce((t, a) => t + a.prog, 0) / (live.length || 1)),
      up: rs.filter((r) => r.trend === 'up'), avg: Math.round((rs.reduce((t, r) => t + sc(r.res), 0) / rs.length) * 10) / 10
    };
  }
  const PER = 25;
  function paged(key, rows) { const n = Math.max(1, Math.ceil(rows.length / PER)); if (S.pg[key] > n) S.pg[key] = n; const p = S.pg[key]; return { rows: rows.slice((p - 1) * PER, p * PER), p, n, total: rows.length }; }
  function pager(key, pg) {
    if (pg.n <= 1) return '';
    const from = (pg.p - 1) * PER + 1, to = Math.min(pg.total, pg.p * PER);
    const nums = []; for (let i = 1; i <= pg.n; i++) if (i === 1 || i === pg.n || Math.abs(i - pg.p) <= 1) nums.push(i); else if (nums[nums.length - 1] !== '…') nums.push('…');
    return `<div class="row between" style="padding:12px 16px;border-top:1px solid var(--line)"><span class="hint">Menampilkan ${from}–${to} dari ${pg.total}</span><div class="row" style="gap:4px">${`<button class="btn sm ghost c-blue" data-act="page" data-v="${key}:${pg.p - 1}" ${pg.p === 1 ? 'disabled' : ''} aria-label="Halaman sebelumnya">‹</button>`}${nums.map((i) => (i === '…' ? '<span class="muted" style="padding:0 4px">…</span>' : `<button class="btn sm c-blue ${i === pg.p ? 'pri' : 'ghost'}" data-act="page" data-v="${key}:${i}" aria-current="${i === pg.p}">${i}</button>`)).join('')}<button class="btn sm ghost c-blue" data-act="page" data-v="${key}:${pg.p + 1}" ${pg.p === pg.n ? 'disabled' : ''} aria-label="Halaman berikutnya">›</button></div></div>`;
  }

  /* ======================= Navigasi ======================= */
  const NAV = [
    { g: 'Dashboard', items: [['exec', 'Executive Dashboard', 'grid'], ['risk-dash', 'Risk Dashboard', 'pulse'], ['kri', 'KRI & Early Warning', 'gauge']] },
    { g: 'Manajemen Risiko', items: [['context', 'Konteks & Kriteria', 'compass'], ['identify', 'Identifikasi Risiko', 'search'], ['register', 'Risk Register', 'list'], ['analysis', 'Analisis & Evaluasi', 'scale'], ['review', 'Risk Review', 'refresh']] },
    { g: 'Penanganan & Kontrol', items: [['treatment', 'Mitigasi & Action Plan', 'tasks'], ['controls', 'Kontrol & Efektivitas', 'shield'], ['improve', 'Perbaikan Berkelanjutan', 'up']] },
    { g: 'Pemantauan', items: [['incidents', 'Insiden & Loss Event', 'alert']] },
    { g: 'Tata Kelola', items: [['objective', 'Pemetaan Sasaran', 'flag'], ['governance', 'Taksonomi & Appetite', 'layers'], ['iso', 'Kerangka ISO 31000', 'book'], ['workflow', 'Persetujuan', 'inbox']] },
    { g: 'Pelaporan & Dokumen', items: [['reports', 'Laporan', 'file'], ['documents', 'Dokumen & Bukti', 'folder']] },
    { g: 'Administrasi', items: [['org', 'Organisasi & Pengguna', 'users'], ['users', 'Pengguna & Akun', 'key'], ['audit', 'Audit Trail', 'clock']] },
    { g: 'AI', items: [['ai', 'AI Risk Assistant', 'spark']] }
  ];
  const allowed = (id) => { if (id === 'users') return S.role === 'Super Admin'; const n = roleCfg().nav; return n === 'all' || n.includes(id); };
  const firstAllowed = () => { for (const g of NAV) for (const it of g.items) if (allowed(it[0])) return it[0]; return 'register'; };

  function route() {
    const h = (location.hash || '').slice(1);
    if (/^risk-R-\d+$/.test(h)) return { id: 'risk', arg: h.slice(5) };
    return { id: h || 'exec', arg: null };
  }

  /* ======================= Komponen ======================= */
  function ph(eyebrow, title, desc, actions) {
    return `<div class="ph"><div class="ph-txt"><div class="eyebrow">${eyebrow}</div><h1>${title}</h1>${desc ? `<p>${desc}</p>` : ''}</div>${actions ? `<div class="ph-act">${actions}</div>` : ''}</div>`;
  }
  function card(title, body, opt = {}) {
    return `<section class="card ${opt.cls || ''}">${title ? `<div class="card-h"><h3>${title}${opt.sub ? ` <span class="sub">${opt.sub}</span>` : ''}</h3>${opt.extra || ''}</div>` : ''}<div class="card-b ${opt.flush ? 'flush' : ''}">${body}</div></section>`;
  }
  const kpi = (l, v, s, cls = '', style = '') => `<div class="kpi ${cls}" style="${style}"><div class="k-l">${l}</div><div class="k-v">${v}</div>${s ? `<div class="k-s">${s}</div>` : ''}</div>`;
  const prog = (p, st) => `<div class="prog" role="progressbar" aria-valuenow="${p}" aria-valuemin="0" aria-valuemax="100"><div class="bar"><i class="${st === 'late' ? 'late' : st === 'done' ? 'done' : ''}" style="width:${p}%"></i></div><span>${p}%</span></div>`;
  const seg = (act, opts, cur) => `<div class="seg" role="group">${opts.map(([v, l]) => `<button type="button" class="${v === cur ? 'on' : ''}" data-act="${act}" data-v="${v}" aria-pressed="${v === cur}">${l}</button>`).join('')}</div>`;
  const tabs = (act, opts, cur) => `<div class="tabs" role="tablist">${opts.map(([v, l, c]) => `<button type="button" role="tab" class="${v === cur ? 'on' : ''}" data-act="${act}" data-v="${v}" aria-selected="${v === cur}">${l}${c != null ? ` <span class="c">${c}</span>` : ''}</button>`).join('')}</div>`;
  const opt = (v, l, cur) => `<option value="${esc(v)}" ${String(v) === String(cur) ? 'selected' : ''}>${esc(l)}</option>`;
  const W = (label, extra = '') => `data-w ${extra}`; // tombol yang memerlukan hak tulis

  function legendLv() {
    return `<div class="legend">${D.LEVELS.map((l) => `<span style="--c:var(--lv-${l.k})"><i></i>${l.n} <span class="muted mono">${l.min}–${l.max}</span></span>`).join('')}</div>`;
  }

  /* Heatmap 5×5 */
  function heatmap(mode, o = {}) {
    const data = o.markers ? {} : D.HEAT[mode];
    let h = `<div class="hm" role="grid" aria-label="Risk heatmap ${mode === 'res' ? 'residual' : 'inheren'}"><div class="yt">Kemungkinan</div>`;
    for (let L = 5; L >= 1; L--) {
      h += `<div class="ax y"><span><b>${L}</b> ${D.LIKELIHOOD[L - 1].n}</span></div>`;
      for (let I = 1; I <= 5; I++) {
        const s = L * I, lv = level(s), key = `${L}-${I}`;
        if (o.markers) {
          const m = o.markers[key] || [];
          h += `<div class="cell lv-${lv.k} ${m.length ? '' : 'zero'}" style="cursor:default" data-tip="Kemungkinan ${L} × Dampak ${I} = <b>${s}</b> · ${lv.n}${m.length ? '<br>' + m.map((x) => x.t).join(', ') : ''}"><small>${s}</small>${m.length ? `<b style="font-size:13px;display:flex;gap:3px">${m.map((x) => `<span class="score" style="--c:var(--fg);min-width:22px;height:20px;background:var(--surface)">${x.k}</span>`).join('')}</b>` : '<b>·</b>'}</div>`;
        } else {
          const c = data[key] || 0, sel = S.reg.cell === key && o.selectable;
          h += `<button type="button" class="cell lv-${lv.k} ${c ? '' : 'zero'} ${sel ? 'sel' : ''}" data-act="hmcell" data-v="${key}" data-tip="Kemungkinan ${L} (${D.LIKELIHOOD[L - 1].n}) × Dampak ${I} (${D.IMPACT[I - 1].n})<br>Skor <b>${s}</b> · ${lv.n}<br><b>${c}</b> risiko ${mode === 'res' ? 'residual' : 'inheren'}" aria-label="Skor ${s}, ${c} risiko"><small>${s}</small><b>${c || '·'}</b></button>`;
        }
      }
    }
    h += '<div></div><div></div>';
    for (let I = 1; I <= 5; I++) h += `<div class="ax x"><b>${I}</b><span>${D.IMPACT[I - 1].n}</span></div>`;
    h += '<div class="xt">Dampak</div></div>';
    return h;
  }

  /* Grafik garis dengan crosshair */
  function lineChart(values, labels, o = {}) {
    const Wd = 640, H = o.h || 220, pl = 34, pr = 46, pt = 12, pb = 26;
    const y0 = o.min, y1 = o.max, n = values.length;
    const x = (i) => pl + (i * (Wd - pl - pr)) / (n - 1);
    const y = (v) => pt + (1 - (v - y0) / (y1 - y0)) * (H - pt - pb);
    let g = '';
    for (let t = y0; t <= y1 + 1e-9; t += o.step) g += `<line class="gl" x1="${pl}" x2="${Wd - pr}" y1="${y(t)}" y2="${y(t)}"/><text class="tk" x="${pl - 8}" y="${y(t) + 4}" text-anchor="end">${fmtNum(t)}</text>`;
    const pts = values.map((v, i) => `${x(i).toFixed(1)},${y(v).toFixed(1)}`);
    const area = `M${x(0)},${y(y0)} L${pts.join(' L')} L${x(n - 1)},${y(y0)} Z`;
    const xl = labels.map((l, i) => `<text class="tk" x="${x(i)}" y="${H - 6}" text-anchor="middle">${l}</text>`).join('');
    const stepW = (Wd - pl - pr) / (n - 1);
    const hits = values.map((v, i) => `<rect class="hit" x="${x(i) - stepW / 2}" y="${pt}" width="${stepW}" height="${H - pt - pb}" data-gx="${x(i)}" data-gy="${y(v)}" data-tip="${labels[i]} ${o.year ? o.year(i) : ''}<br>${o.label || 'Nilai'}: <b>${fmtNum(v, o.dg || 0)}</b>"/>`).join('');
    const last = values[n - 1];
    return `<div class="chart"><svg viewBox="0 0 ${Wd} ${H}" role="img" aria-label="${esc(o.aria || '')}">${g}<path class="ar" d="${area}"/><polyline class="ln" points="${pts.join(' ')}"/>${xl}<circle class="ep" cx="${x(n - 1)}" cy="${y(last)}" r="4.5"/><text class="lbl-end" x="${x(n - 1) + 9}" y="${y(last) + 4}">${fmtNum(last, o.dg || 0)}</text><line class="guide" x1="0" x2="0" y1="${pt}" y2="${H - pb}" visibility="hidden"/><circle class="gdot ep" r="4" cx="0" cy="0" visibility="hidden"/>${hits}</svg></div>`;
  }

  /* Kolom bertumpuk per level */
  function stackCols(rows) {
    const Wd = 640, H = 240, pl = 34, pr = 10, pt = 22, pb = 28, max = 140, keys = ['l', 'm', 'h', 'vh'];
    const y = (v) => pt + (1 - v / max) * (H - pt - pb);
    const bw = 64, gapW = (Wd - pl - pr) / rows.length;
    let g = '';
    for (let t = 0; t <= max; t += 35) g += `<line class="gl" x1="${pl}" x2="${Wd - pr}" y1="${y(t)}" y2="${y(t)}"/><text class="tk" x="${pl - 8}" y="${y(t) + 4}" text-anchor="end">${t}</text>`;
    rows.forEach((r, i) => {
      const cx = pl + gapW * i + gapW / 2; let acc = 0; const tot = keys.reduce((s, k) => s + r[k], 0);
      keys.forEach((k, j) => {
        const v = r[k]; const yt = y(acc + v), yb = y(acc); const lv = D.LEVELS.find((l) => l.k === k);
        const top = j === keys.length - 1;
        g += `<path d="${top ? `M${cx - bw / 2},${yb} V${yt + 4} q0,-4 4,-4 H${cx + bw / 2 - 4} q4,0 4,4 V${yb} Z` : `M${cx - bw / 2},${yb} V${yt} H${cx + bw / 2} V${yb} Z`}" style="fill:var(--lv-${k});stroke:var(--surface);stroke-width:2" data-tip="${r.q}<br>${lv.n}: <b>${v}</b> risiko (${Math.round((v / tot) * 100)}%)"/>`;
        acc += v;
      });
      g += `<text class="tk" x="${cx}" y="${y(tot) - 7}" text-anchor="middle" style="fill:var(--fg);font-weight:600">${tot}</text><text class="tk" x="${cx}" y="${H - 8}" text-anchor="middle">${r.q}</text>`;
    });
    return `<div class="chart"><svg viewBox="0 0 ${Wd} ${H}" role="img" aria-label="Distribusi level risiko per triwulan">${g}</svg></div>`;
  }

  function barList(rows, o = {}) {
    const max = o.max || Math.max(...rows.map((r) => r[1]));
    return `<div class="bars">${rows.map((r) => `<div class="brow" data-tip="${esc(r[0])}: <b>${r[1]}</b> ${o.unit || 'risiko'}${r[2] != null ? `<br>Sangat Tinggi ${r[2]} · Tinggi ${r[3]}` : ''}"><span class="bl" title="${esc(r[0])}">${esc(r[0])}</span><span class="bt"><i style="width:${(r[1] / max) * 100}%"></i></span><span class="bv">${r[1]}</span></div>`).join('')}</div>`;
  }

  function spark(k) {
    const Wd = 240, H = 44, v = k.v;
    let lo = Math.min(...v, k.g, k.r), hi = Math.max(...v, k.g, k.r); const pad = (hi - lo) * 0.12 || 1; lo -= pad; hi += pad;
    const x = (i) => (i * Wd) / (v.length - 1), y = (n) => 3 + (1 - (n - lo) / (hi - lo)) * (H - 6);
    const st = kriStatus(k);
    const zone = k.inv ? `<rect class="zone" x="0" y="${y(k.r)}" width="${Wd}" height="${H - y(k.r)}"/>` : `<rect class="zone" x="0" y="0" width="${Wd}" height="${y(k.r)}"/>`;
    return `<svg class="spark" viewBox="0 0 ${Wd} ${H}" preserveAspectRatio="none" role="img" aria-label="Tren 12 bulan ${esc(k.n)}">${zone}<line class="thr" x1="0" x2="${Wd}" y1="${y(k.g)}" y2="${y(k.g)}" style="stroke:var(--lv-m)"/><line class="thr" x1="0" x2="${Wd}" y1="${y(k.r)}" y2="${y(k.r)}" style="stroke:var(--lv-vh)"/><polyline class="sl" points="${v.map((n, i) => `${x(i).toFixed(1)},${y(n).toFixed(1)}`).join(' ')}" vector-effect="non-scaling-stroke"/><circle cx="${x(v.length - 1) - 3}" cy="${y(v[v.length - 1])}" r="3.5" style="fill:${st.col};stroke:var(--surface);stroke-width:1.5" vector-effect="non-scaling-stroke"/></svg>`;
  }

  function kriCard(k) {
    const st = kriStatus(k), last = k.v[k.v.length - 1], prev = k.v[k.v.length - 2];
    const thr = k.inv
      ? [`≥ ${kriVal(k, k.g)}`, `${kriVal(k, k.r)}–${kriVal(k, k.g)}`, `< ${kriVal(k, k.r)}`]
      : [`≤ ${kriVal(k, k.g)}`, `${kriVal(k, k.g)}–${kriVal(k, k.r)}`, `> ${kriVal(k, k.r)}`];
    const r = riskById(k.risk);
    return `<div class="kri"><div class="kh"><div><div class="mono muted" style="font-size:11px">${k.id} · <a href="#risk-${k.risk}">${k.risk}</a></div><div class="kn">${esc(k.n)}</div></div><span class="pill ${st.c}">${st.n === 'Kritis' ? '● ' : ''}${st.n}</span></div>
      <div class="row between" style="align-items:flex-end"><div class="kval">${kriVal(k, last)}<small>${esc(k.u)}</small></div><span class="mono muted" style="font-size:11.5px">bln lalu ${kriVal(k, prev)}</span></div>
      ${spark(k)}
      <div class="th"><span style="--c:var(--lv-l)">Normal ${thr[0]}</span><span style="--c:var(--lv-m)">Waspada ${thr[1]}</span><span style="--c:var(--lv-vh)">Kritis ${thr[2]}</span></div>
      ${r ? `<div class="muted" style="font-size:11.5px">Risiko: ${esc(r.name)}</div>` : ''}</div>`;
  }

  function journey(stages) {
    return `<div class="journey" style="--n:${stages.length}">${stages.map((s) => { const v = sc(s.a), l = level(v); return `<div class="jstep" style="--c:var(--lv-${l.k})"><div class="js-l">${s.l}</div><div class="js-v"><b>${v}</b>${lvChip(v)}</div><div class="js-f">K${s.a[0]} × D${s.a[1]}</div><div class="js-bar"><i style="width:${(v / 25) * 100}%"></i></div></div>`; }).join('')}</div>`;
  }

  const WARNINGS = D.WARNINGS;
  const feed = (items) => `<div class="feed">${items.map((w) => `<a class="fitem" href="#${w.go}" style="--c:${w.c};text-decoration:none;color:inherit"><span class="fi">${ic(w.i)}</span><div><div class="ft">${w.t}</div><div class="fm mono">${w.m}</div></div></a>`).join('')}</div>`;

  function riskRow(r, i) {
    const s = sc(r.res);
    return `<a class="ritem" href="#risk-${r.id}"><span class="rk">${i + 1}</span><div style="min-width:0"><div class="rn">${esc(r.name)}</div><div class="rs"><span class="mono">${r.id}</span> · ${esc(unitName(r.unit))}</div></div><div class="rr">${trendB(r.trend)}${scoreB(s)}</div></a>`;
  }

  /* ======================= Layar ======================= */
  const V = {};

  V.exec = function () {
    const P = D.PROFILE, inh = S.heat === 'inh';
    const lvTile = (k, n, v, prev) => `<div style="--c:var(--lv-${k})"><div class="k-l"><span class="lv lv-${k}" style="padding:0;background:none"><i></i></span>${n}</div><div class="k-v">${v}</div><div class="k-s">${Math.round((v / P.total) * 100)}% · inheren ${prev}</div><div class="meter"><i style="width:${(v / P.total) * 100}%"></i></div></div>`;
    const top = S.risks.filter((r) => r.status !== 'Ditutup').slice().sort((a, b) => sc(b.res) - sc(a.res) || sc(b.inh) - sc(a.inh)).slice(0, 10);
    const r2 = riskById('R-002'), st = stats(), maxObj = Math.max(...D.OBJECTIVES.map((o) => o.cnt));
    return `
    ${ph(`ISO 31000 · 6.7 Pencatatan & Pelaporan · ${D.ORG.period}`, 'Executive Risk Dashboard', `Profil risiko ${esc(D.ORG.name)} pada level residual, yaitu setelah memperhitungkan kontrol yang sudah berjalan.`, `<a class="btn" href="#risk-dash">${ic('pulse')}Detail operasional</a><a class="btn pri" href="#reports" data-act="gen-report">${ic('spark')}Laporan eksekutif AI</a>`)}
    <div class="callout"><span class="ai-ic">${ic('spark')}</span><div style="flex:1;min-width:0"><p>Terdapat <b>${st.vh} risiko Sangat Tinggi</b> yang membutuhkan perhatian manajemen; ${st.topUnitN} di antaranya berada di ${esc(st.topUnit)}. Risiko ransomware naik dari Tinggi ke Sangat Tinggi bulan ini dan backlog permohonan perizinan menembus 5.200 berkas. Realisasi mitigasi mencapai <b>${st.mitig}%</b>, namun ${st.late.length} action plan melewati tenggat.</p><small>Ringkasan AI dari risk register, KRI, dan action plan · diperbarui 5 Okt 2026 14:40</small></div><a class="btn sm" href="#ai">Tanya AI</a></div>
    <div class="profile">
      <div class="total"><div class="k-l">Total risiko organisasi</div><div class="k-v">${P.total}</div><div class="k-s">${P.newQ} risiko baru triwulan ini · ${P.closed} ditutup</div></div>
      ${lvTile('vh', 'Sangat Tinggi', P.vh, P.inh.vh)}${lvTile('h', 'Tinggi', P.h, P.inh.h)}${lvTile('m', 'Sedang', P.m, P.inh.m)}${lvTile('l', 'Rendah', P.l, P.inh.l)}
    </div>
    <div class="grid g-12">
      <div class="s-5">${card('Risk heatmap', `${heatmap(S.heat)}<div style="margin-top:12px">${legendLv()}</div>`, { sub: inh ? 'inheren · sebelum kontrol' : 'residual · setelah kontrol', extra: seg('heat', [['inh', 'Inheren'], ['res', 'Residual']], S.heat) })}</div>
      <div class="s-7">${card('Top 10 risiko organisasi', `<div class="rlist">${top.map(riskRow).join('')}</div>`, { flush: true, sub: 'urut skor residual', extra: '<a class="btn sm ghost" href="#register">Risk register →</a>' })}</div>
      <div class="s-7">${card('Tren skor risiko rata-rata', lineChart(D.AVG_TREND.values, D.AVG_TREND.labels, { min: 8, max: 12, step: 1, dg: 1, label: 'Skor residual rata-rata', aria: 'Skor residual rata-rata turun dari 10,8 ke 9,1 dalam 12 bulan', year: (i) => (i < 2 ? '2025' : '2026') }), { sub: 'residual, Nov 2025 – Okt 2026 · makin rendah makin baik' })}</div>
      <div class="s-5">${card('Mitigasi & arah risiko', `
        <div class="row between" style="align-items:flex-end"><div><div class="lbl">Realisasi mitigasi</div><div style="font-family:var(--f-display);font-size:34px;font-weight:600;line-height:1.1">${P.mitig}%</div><div class="hint">rata-rata progres ${D.MITIG.total} action plan</div></div><div style="text-align:right"><div class="lbl">Efektivitas mitigasi</div><div style="font-family:var(--f-display);font-size:24px;font-weight:600">${P.effect}%</div><div class="hint">risiko turun ≥ 1 level</div></div></div>
        <div style="margin:12px 0 16px">${prog(P.mitig)}</div>
        <div class="lbl" style="margin-bottom:8px">Arah risiko dibanding triwulan lalu</div>
        <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
          <div class="kpi" style="padding:10px 12px"><div class="k-l"><span class="trend up">▲</span>Meningkat</div><div class="k-v" style="font-size:24px">${P.up}</div></div>
          <div class="kpi" style="padding:10px 12px"><div class="k-l"><span class="trend flat">▶</span>Stabil</div><div class="k-v" style="font-size:24px">${P.flat}</div></div>
          <div class="kpi" style="padding:10px 12px"><div class="k-l"><span class="trend down">▼</span>Menurun</div><div class="k-v" style="font-size:24px">${P.down}</div></div>
        </div>`)}</div>
      <div class="s-12">${card(`Perjalanan risiko · <a href="#risk-R-002">${r2.id} ${esc(r2.name)}</a>`, journey([{ l: 'Inheren', a: r2.inh }, { l: 'Setelah kontrol eksisting', a: r2.res }, { l: 'Proyeksi setelah mitigasi', a: r2.proj }, { l: 'Target (risk appetite)', a: r2.tgt }]), { sub: 'Inherent → Control → Treatment → Target' })}</div>
      <div class="s-6">${card('Risiko per kategori', barList(D.TAXONOMY.map((t) => [t.k, t.cnt]).sort((a, b) => b[1] - a[1])), { sub: `${P.total} risiko` })}</div>
      <div class="s-6">${card('Risiko per sasaran strategis', `<div class="stack">${D.OBJECTIVES.map((o) => `<a href="#objective" data-act="obj" data-v="${o.id}" style="text-decoration:none;color:inherit;display:grid;grid-template-columns:44px minmax(0,1fr) 40px;gap:10px;align-items:center" data-tip="${esc(o.n)}<br>IKU: ${esc(o.ik)}"><span class="mono muted">${o.id}</span><span style="min-width:0"><span style="display:block;font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(o.n)}</span><span class="prog" style="min-width:0"><span class="bar"><i style="width:${(o.cnt / maxObj) * 100}%"></i></span></span></span><span class="mono" style="text-align:right">${o.cnt}</span></a>`).join('')}</div>`, { sub: 'klik untuk melihat pemetaan' })}</div>
    </div>`;
  };

  V['risk-dash'] = function () {
    const P = D.PROFILE, M = D.MITIG;
    const segs = [['done', 'Selesai', M.done, 'var(--lv-l)'], ['run', 'Berjalan', M.run, 'var(--accent)'], ['todo', 'Belum mulai', M.todo, 'var(--muted)'], ['late', 'Terlambat', M.late, 'var(--lv-vh)'], ['cancel', 'Dibatalkan', M.cancel, 'var(--line)']];
    const upAll = S.risks.filter((r) => r.trend === 'up'), emerging = upAll.slice().sort((a, b) => sc(b.res) - sc(a.res)).slice(0, 8);
    return `
    ${ph('ISO 31000 · 6.6 Pemantauan & Reviu', 'Risk Dashboard', 'Pemantauan operasional untuk Risk Manager dan Risk Officer: pergerakan risiko, status mitigasi, dan distribusi per unit kerja.', `<select class="sel" aria-label="Unit kerja"><option>Semua unit kerja</option>${D.UNITS.map((u) => `<option>${u}</option>`).join('')}</select>`)}
    <div class="kpis">
      ${kpi('Risiko baru', P.newQ, 'triwulan III 2026')}
      ${kpi('Sedang ditangani', P.treating, 'memiliki action plan aktif')}
      ${kpi(`<span style="color:var(--bad-ink)">${ic('clock')}</span>Melewati target`, P.overdueRisk, 'tanggal target terlampaui', '', 'border-top:3px solid var(--lv-vh)')}
      ${kpi('Ditutup', P.closed, 'tahun berjalan')}
      ${kpi('<span class="trend up">▲</span>Meningkat', P.up, 'vs triwulan lalu')}
      ${kpi('<span class="trend down">▼</span>Menurun', P.down, 'vs triwulan lalu')}
    </div>
    <div class="grid g-12">
      <div class="s-7">${card('Distribusi level risiko per triwulan', `<div style="margin-bottom:8px">${legendLv()}</div>${stackCols(D.QUARTERS)}`, { sub: 'residual' })}</div>
      <div class="s-5">${card('Status mitigasi', `
        <div class="sbar" role="img" aria-label="Status ${M.total} action plan">${segs.map((s) => `<i style="--c:${s[3]};width:${(s[2] / M.total) * 100}%" data-tip="${s[1]}: <b>${s[2]}</b> (${Math.round((s[2] / M.total) * 100)}%)"></i>`).join('')}</div>
        <table class="tbl" style="margin-top:12px"><tbody>${segs.map((s) => `<tr><td style="padding-left:0"><span class="legend"><span style="--c:${s[3]}"><i></i>${s[1]}</span></span></td><td class="num">${s[2]}</td><td class="num muted" style="padding-right:0">${Math.round((s[2] / M.total) * 100)}%</td></tr>`).join('')}</tbody></table>
        <a class="btn sm" href="#treatment" style="margin-top:10px">Kelola action plan →</a>`, { sub: `${M.total} action plan` })}</div>
      <div class="s-6">${card('Risiko per unit kerja', barList(D.BY_UNIT), { sub: 'total risiko · hover untuk level' })}</div>
      <div class="s-6">${card('Risiko per proses bisnis', barList(D.BY_PROCESS), { sub: `${D.BY_PROCESS.length} proses teratas` })}</div>
      <div class="s-6">${card('Top emerging risks', `<div class="rlist">${emerging.map(riskRow).join('')}</div>`, { flush: true, sub: `${emerging.length} dari ${upAll.length} risiko yang meningkat`, extra: '<a class="btn sm ghost" href="#review">Risk review →</a>' })}</div>
      <div class="s-6">${card('Peringatan terbaru', feed(WARNINGS.slice(0, 5)), { flush: true, extra: '<a class="btn sm ghost" href="#kri">Semua →</a>' })}</div>
      <div class="s-12">${card('Aktivitas terbaru', auditTable(S.audit.slice(0, 8)), { flush: true, extra: allowed('audit') ? '<a class="btn sm ghost" href="#audit">Audit trail →</a>' : '' })}</div>
    </div>`;
  };

  V.kri = function () {
    const sts = D.KRIS.map(kriStatus);
    const cnt = (n) => sts.filter((s) => s.n === n).length;
    return `
    ${ph('ISO 31000 · 6.6 Pemantauan & Reviu', 'KRI & Early Warning', 'Key Risk Indicator memberi sinyal dini saat risiko mulai meningkat. Ambang batas Normal, Waspada, dan Kritis ditetapkan per indikator.', `<button class="btn" ${W()} data-act="toast" data-v="Formulir KRI baru dibuka (simulasi)">${ic('plus')}Tambah KRI</button>`)}
    <div class="kpis">
      ${kpi('<span class="lv lv-vh" style="padding:0;background:none"><i></i></span>Kritis', cnt('Kritis'), 'melewati batas toleransi', 'lvl', '--c:var(--lv-vh)')}
      ${kpi('<span class="lv lv-m" style="padding:0;background:none"><i></i></span>Waspada', cnt('Waspada'), 'di zona peringatan', 'lvl', '--c:var(--lv-m)')}
      ${kpi('<span class="lv lv-l" style="padding:0;background:none"><i></i></span>Normal', cnt('Normal'), 'dalam batas', 'lvl', '--c:var(--lv-l)')}
      ${kpi('Data otomatis', `${D.KRIS.length - 7} / ${D.KRIS.length}`, 'via API (ITSM, SIEM, SIMPEG, SAKTI)')}
    </div>
    <div class="kri-grid">${D.KRIS.slice().sort((a, b) => ['Kritis', 'Waspada', 'Normal'].indexOf(kriStatus(a).n) - ['Kritis', 'Waspada', 'Normal'].indexOf(kriStatus(b).n)).map(kriCard).join('')}</div>
    <div class="grid g-12">
      <div class="s-8">${card('Early warning', feed(WARNINGS), { flush: true, sub: 'dipicu KRI & perubahan skor' })}</div>
      <div class="s-4">${card('Kanal notifikasi', `<div class="stack">${[['Dashboard', true, 'Selalu aktif'], ['Email', true, 'Risk Owner & Risk Manager'], ['WhatsApp', true, 'Hanya status Kritis · via integrasi'], ['Push notification', false, 'Aplikasi seluler']].map(([n, on, d], i) => `<label class="row between" style="gap:12px"><span><b style="font-size:13px">${n}</b><br><span class="hint">${d}</span></span><input type="checkbox" id="ch-${i}" ${on ? 'checked' : ''} ${i === 0 ? 'disabled' : ''} data-act="toast" data-v="Pengaturan notifikasi ${n} disimpan" style="width:18px;height:18px;accent-color:var(--accent)"></label>`).join('')}</div>`)}</div>
    </div>`;
  };

  V.context = function () {
    const t = S.ctxTab;
    let body = '';
    if (t === 'scope') {
      body = `<div class="grid g-12"><div class="s-8">${card('Penetapan ruang lingkup', `<form class="form-grid" data-form="scope">
        <div class="field" style="grid-column:1/-1"><label for="sc-obj">Objek / proses yang dianalisis</label><input class="inp" id="sc-obj" value="Penyelenggaraan layanan perizinan daring"></div>
        <div class="field" style="grid-column:1/-1"><label for="sc-goal">Tujuan</label><textarea id="sc-goal">Memastikan layanan perizinan daring tersedia ≥ 99,5%, aman, dan memenuhi standar pelayanan 3 hari kerja.</textarea></div>
        <div class="field"><label for="sc-per">Periode</label><select id="sc-per">${opt('2026', 'Tahun Anggaran 2026', '2026')}${opt('2027', 'Tahun Anggaran 2027', '2026')}</select></div>
        <div class="field"><label for="sc-unit">Unit pemilik</label><select id="sc-unit">${D.UNITS.map((u, i) => opt(i, u, 2)).join('')}</select></div>
        <div class="field" style="grid-column:1/-1"><label for="sc-lim">Batasan</label><textarea id="sc-lim">Tidak mencakup layanan tatap muka di kantor wilayah dan sistem milik kementerian lain yang terintegrasi.</textarea></div>
        <div class="field" style="grid-column:1/-1"><label for="sc-area">Unit terkait & area cakupan</label><input class="inp" id="sc-area" value="Direktorat TI, Biro Umum & Pengadaan, Biro Hukum · aplikasi, infrastruktur, data, vendor"></div>
        <div class="row" style="grid-column:1/-1"><button type="button" class="btn pri" ${W()} data-act="toast" data-v="Ruang lingkup disimpan">Simpan ruang lingkup</button><span class="hint">Terakhir diubah Osmond · 12 Feb 2026</span></div></form>`)}</div>
        <div class="s-4">${card('Komunikasi & konsultasi', `<p class="fg2" style="font-size:13px">ISO 31000 6.2 meminta pemangku kepentingan dilibatkan sejak penetapan konteks.</p><div class="stack" style="margin-top:12px">${[['FGD konteks layanan perizinan', '14 Jan 2026', 'Selesai'], ['Konsultasi kriteria dampak dengan Biro Keuangan', '28 Jan 2026', 'Selesai'], ['Sosialisasi risk appetite ke Eselon II', '15 Okt 2026', 'Terjadwal']].map(([a, b, c]) => `<div class="row between"><div><b style="font-size:13px">${a}</b><div class="hint">${b}</div></div><span class="pill ${c === 'Selesai' ? 'ok' : 'run'}">${c}</span></div>`).join('')}</div>`)}</div></div>`;
    } else if (t === 'context') {
      const IN = [['Struktur organisasi', 'Fungsi keamanan informasi masih setingkat subdirektorat', 'Kelemahan'], ['SDM', '6 posisi kunci tanpa pengganti; kompetensi siber terbatas', 'Kelemahan'], ['Teknologi', 'Arsitektur layanan sudah berbasis microservice', 'Kekuatan'], ['Keuangan', 'Pagu TI naik 18% di TA 2026', 'Kekuatan'], ['Infrastruktur', 'Pusat data tunggal; DRC belum diuji penuh', 'Kelemahan'], ['Kebijakan', 'Kebijakan MR ditetapkan Feb 2026', 'Kekuatan'], ['Proses bisnis', 'Verifikasi tagihan dan pengadaan masih manual', 'Kelemahan'], ['Budaya organisasi', 'Pelaporan insiden mulai meningkat', 'Kekuatan']];
      const EX = [['Regulasi', 'UU Pelindungan Data Pribadi berlaku penuh', 'Ancaman'], ['Politik', 'Prioritas nasional transformasi digital pemerintah', 'Peluang'], ['Ekonomi', 'Kenaikan kurs berdampak pada lisensi perangkat lunak', 'Ancaman'], ['Sosial', 'Ekspektasi publik pada layanan 24/7', 'Ancaman'], ['Teknologi', 'Ketersediaan layanan cloud pemerintah (PDN)', 'Peluang'], ['Lingkungan', 'Banjir musiman di sekitar gedung', 'Ancaman'], ['Stakeholder', 'DPR & Ombudsman memantau kualitas layanan', 'Ancaman'], ['Perubahan industri', 'Serangan ransomware ke sektor publik meningkat', 'Ancaman']];
      const tb = (rows, title, sub) => card(title, `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Faktor</th><th>Kondisi</th><th>Sifat</th></tr></thead><tbody>${rows.map(([a, b, c]) => `<tr><td class="t-main" style="white-space:nowrap">${a}</td><td class="fg2">${b}</td><td><span class="pill ${['Kekuatan', 'Peluang'].includes(c) ? 'ok' : 'bad'}">${c}</span></td></tr>`).join('')}</tbody></table></div>`, { flush: true, sub });
      body = `<div class="grid g-12"><div class="s-6">${tb(IN, 'Konteks internal', 'ISO 31000 6.3.3')}</div><div class="s-6">${tb(EX, 'Konteks eksternal', 'PESTLE + pemangku kepentingan')}</div></div>`;
    } else {
      body = `<div class="grid g-12">
      <div class="s-6">${card('Skala kemungkinan', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Nilai</th><th>Level</th><th>Kriteria</th></tr></thead><tbody>${D.LIKELIHOOD.map((l) => `<tr><td class="mono">${l.v}</td><td><b>${l.n}</b><div class="t-sub">${l.id}</div></td><td class="fg2">${l.d}</td></tr>`).join('')}</tbody></table></div>`, { flush: true, extra: `<button class="btn sm" ${W()} data-act="toast" data-v="Skala kemungkinan dapat diubah oleh Risk Administrator">Ubah</button>` })}</div>
      <div class="s-6">${card('Level & ambang risiko', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Level</th><th>Rentang skor</th><th>Kriteria penerimaan</th></tr></thead><tbody>${D.LEVELS.slice().reverse().map((l) => `<tr><td>${lvChip(l.min)}</td><td class="mono">${l.min}–${l.max}</td><td class="fg2">${{ vh: 'Tidak dapat diterima; eskalasi ke pimpinan ≤ 2×24 jam', h: 'Wajib rencana mitigasi; dipantau bulanan', m: 'Dapat diterima dengan pemantauan triwulanan', l: 'Dapat diterima; dipantau tahunan' }[l.k]}</td></tr>`).join('')}</tbody></table></div>`, { flush: true })}</div>
      <div class="s-12">${card('Skala dampak multidimensi', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Nilai</th><th>Level</th><th>Keuangan</th><th>Operasional</th><th>Reputasi</th><th>Hukum / kepatuhan</th></tr></thead><tbody>${D.IMPACT.map((l) => `<tr><td class="mono">${l.v}</td><td><b>${l.n}</b><div class="t-sub">${l.id}</div></td><td>${l.fin}</td><td>${l.ops}</td><td>${l.rep}</td><td>${l.law}</td></tr>`).join('')}</tbody></table></div>`, { flush: true, sub: 'nilai dampak = dimensi tertinggi' })}</div>
      <div class="s-12">${card('Risk appetite, tolerance & capacity', `<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">${[['Risk appetite', 'Skor residual ≤ 6 untuk sebagian besar kategori; ≤ 4 untuk Kepatuhan, Hukum, dan Keamanan Siber.', 'Batas risiko yang bersedia diambil untuk mencapai sasaran.'], ['Risk tolerance', 'Penyimpangan hingga skor 9 (12 untuk Strategis & SDM) masih dapat ditoleransi dengan pemantauan.', 'Variasi yang dapat diterima di sekitar appetite.'], ['Risk capacity', 'Kerugian maksimum Rp5 M per tahun atau gangguan layanan ≤ 3 hari.', 'Batas maksimum yang dapat ditanggung organisasi.']].map(([a, b, c]) => `<div style="border:1px solid var(--line);border-radius:8px;padding:14px"><div class="lbl">${a}</div><p style="margin-top:6px;font-weight:600">${b}</p><p class="hint" style="margin-top:6px">${c}</p></div>`).join('')}</div><a class="btn sm" href="#governance" style="margin-top:12px">Appetite per kategori →</a>`)}</div>
      </div>`;
    }
    return `${ph('ISO 31000 · 6.3 Ruang Lingkup, Konteks & Kriteria', 'Konteks & Kriteria Risiko', 'Tetapkan objek analisis, pahami konteks internal dan eksternal, dan atur kriteria yang dipakai seluruh unit saat menilai risiko.')}
      ${tabs('ctx', [['scope', 'Ruang lingkup'], ['context', 'Konteks internal & eksternal'], ['criteria', 'Kriteria risiko']], t)}${body}`;
  };

  /* --- Identifikasi (wizard) --- */
  const AI_LIB = {
    pembayaran: [
      { name: 'Keterlambatan pembayaran kepada vendor', cause: 'dokumen tagihan vendor diverifikasi manual dan sering tidak lengkap', event: 'keterlambatan pembayaran melebihi 30 hari kalender', impact: 'denda keterlambatan dan terganggunya pasokan layanan', cat: 'Keuangan' },
      { name: 'Kesalahan data rekening vendor', cause: 'perubahan data rekening vendor tidak diverifikasi ulang', event: 'dana ditransfer ke rekening yang salah', impact: 'kerugian keuangan dan proses penagihan kembali', cat: 'Operasional' },
      { name: 'Fraud pembayaran fiktif', cause: 'pemisahan tugas antara pembuat dan penyetuju pembayaran lemah', event: 'pembayaran atas pekerjaan yang tidak dilaksanakan', impact: 'kerugian negara dan proses hukum', cat: 'Kepatuhan' },
      { name: 'Pembayaran ganda', cause: 'verifikasi tidak mencocokkan nilai kontrak kumulatif', event: 'pembayaran ganda atas tagihan yang sama', impact: 'kelebihan bayar dan temuan audit', cat: 'Keuangan' },
      { name: 'Gangguan sistem pembayaran', cause: 'aplikasi SPAN/SAKTI tidak dapat diakses', event: 'proses pembayaran tertunda', impact: 'keterlambatan pembayaran massal di akhir tahun', cat: 'Teknologi Informasi' },
      { name: 'Ketidakpatuhan perpajakan', cause: 'pemotongan pajak tidak sesuai ketentuan terbaru', event: 'kesalahan pemotongan PPh/PPN', impact: 'sanksi administrasi perpajakan', cat: 'Kepatuhan' }
    ],
    pengadaan: [
      { name: 'Persekongkolan tender', cause: 'spesifikasi teknis mengarah ke merek tertentu', event: 'persekongkolan antarpeserta tender', impact: 'harga tidak wajar dan pembatalan tender', cat: 'Kepatuhan' },
      { name: 'Keterlambatan proses pengadaan', cause: 'dokumen perencanaan pengadaan terlambat disusun', event: 'kontrak ditandatangani melewati jadwal', impact: 'kegiatan prioritas tertunda', cat: 'Operasional' },
      { name: 'Wanprestasi penyedia', cause: 'evaluasi kemampuan penyedia tidak memadai', event: 'penyedia gagal menyelesaikan pekerjaan', impact: 'pemutusan kontrak dan pengadaan ulang', cat: 'Pihak Ketiga' }
    ],
    default: [
      { name: 'Gangguan sistem pendukung proses', cause: 'sistem pendukung tidak memiliki cadangan', event: 'sistem tidak tersedia saat dibutuhkan', impact: 'proses terhenti dan layanan tertunda', cat: 'Teknologi Informasi' },
      { name: 'Kesalahan manusia dalam pelaksanaan proses', cause: 'SOP belum diperbarui dan pelatihan terbatas', event: 'kesalahan pelaksanaan proses', impact: 'hasil kerja tidak sesuai standar', cat: 'Operasional' },
      { name: 'Ketidakpatuhan regulasi', cause: 'perubahan regulasi tidak dipantau', event: 'proses tidak sesuai ketentuan terbaru', impact: 'temuan audit dan sanksi administratif', cat: 'Kepatuhan' }
    ]
  };
  const aiCands = (p) => { const s = (p || '').toLowerCase(); if (/bayar|vendor|tagih/.test(s)) return AI_LIB.pembayaran; if (/pengadaan|tender|lelang/.test(s)) return AI_LIB.pengadaan; return AI_LIB.default; };

  function stmtHTML(d) {
    const f = (v, ph, c) => (v ? `<em class="${c}">${esc(v)}</em>` : `<em class="ph-empty">${ph}</em>`);
    return `Karena ${f(d.cause, '[penyebab]', 'c1')}, dapat terjadi ${f(d.event, '[peristiwa risiko]', 'c2')}, sehingga menyebabkan ${f(d.impact, '[dampak]', 'c3')}.`;
  }
  function mpick(prefix, L, I) {
    let h = `<div class="mpick" role="grid">`;
    for (let l = 5; l >= 1; l--) { h += `<span class="ax">${l}</span>`; for (let i = 1; i <= 5; i++) { const s = l * i; h += `<button type="button" class="lv-${level(s).k} ${l === L && i === I ? 'on' : ''}" data-act="mpick" data-v="${prefix}:${l}:${i}" aria-label="Kemungkinan ${l} dampak ${i} skor ${s}">${s}</button>`; } }
    h += `<span></span>${[1, 2, 3, 4, 5].map((i) => `<span class="ax">${i}</span>`).join('')}</div><div class="row between hint" style="margin-top:4px"><span>↑ Kemungkinan</span><span>Dampak →</span></div>`;
    return h;
  }

  V.identify = function () {
    const z = S.wz, d = z.d, st = z.step;
    const steps = [['Konteks', 'unit, proses, sasaran'], ['Pernyataan risiko', 'penyebab → peristiwa → dampak'], ['Analisis', 'inheren & residual'], ['Perlakuan', 'opsi & action plan'], ['Tinjau & ajukan', 'kirim ke alur persetujuan']];
    const inS = d.iL * d.iI, reS = d.rL * d.rI;
    let body = '';
    if (st === 1) {
      body = `<div class="form-grid">
        <div class="field" style="grid-column:1/-1"><label for="wz-name">Nama risiko</label><input class="inp" id="wz-name" data-wz="name" value="${esc(d.name)}" placeholder="Contoh: Keterlambatan pembayaran kepada vendor"></div>
        <div class="field"><label for="wz-unit">Unit kerja</label><select id="wz-unit" data-wz="unit">${D.UNITS.map((u, i) => opt(i, u, d.unit)).join('')}</select></div>
        <div class="field"><label for="wz-owner">Risk owner</label><select id="wz-owner" data-wz="owner">${Object.keys(D.PEOPLE).map((k) => opt(k, `${D.PEOPLE[k].n} — ${D.PEOPLE[k].j}`, d.owner)).join('')}</select></div>
        <div class="field"><label for="wz-proc">Proses bisnis</label><input class="inp" id="wz-proc" data-wz="proc" value="${esc(d.proc)}" placeholder="Contoh: Pembayaran vendor"></div>
        <div class="field"><label for="wz-obj">Sasaran strategis</label><select id="wz-obj" data-wz="obj">${D.OBJECTIVES.map((o) => opt(o.id, `${o.id} · ${o.n}`, d.obj)).join('')}</select></div>
        <div class="field"><label for="wz-cat">Kategori risiko</label><select id="wz-cat" data-wz="cat">${D.TAXONOMY.map((t) => opt(t.k, t.k, d.cat)).join('')}</select></div>
        <div class="field"><label for="wz-src">Sumber penyebab</label><div class="row" style="flex-wrap:nowrap"><select id="wz-src" data-wz="src1">${['Internal', 'Eksternal'].map((x) => opt(x, x, d.src1)).join('')}</select><select id="wz-src2" aria-label="Jenis sumber" data-wz="src2">${['People', 'Process', 'Technology', 'Infrastructure', 'Regulation', 'Financial', 'Third Party'].map((x) => opt(x, x, d.src2)).join('')}</select></div></div>
      </div>`;
    } else if (st === 2) {
      const cands = aiCands(d.proc);
      body = `<div class="grid g-12"><div class="s-7 stack">
        <div class="field"><label for="wz-cause">Penyebab (cause)</label><textarea id="wz-cause" data-wz="cause" placeholder="Apa yang menjadi sumber risiko?">${esc(d.cause)}</textarea></div>
        <div class="field"><label for="wz-event">Peristiwa risiko (event)</label><textarea id="wz-event" data-wz="event" placeholder="Kejadian apa yang mungkin terjadi?">${esc(d.event)}</textarea></div>
        <div class="field"><label for="wz-impact">Dampak (impact)</label><textarea id="wz-impact" data-wz="impact" placeholder="Apa akibatnya terhadap sasaran?">${esc(d.impact)}</textarea></div>
        <div><div class="lbl" style="margin-bottom:6px">Pratinjau risk statement</div><div class="stmt" data-live="stmt">${stmtHTML(d)}</div><div class="hint" style="margin-top:6px">Format baku: Karena [CAUSE], dapat terjadi [EVENT], sehingga menyebabkan [IMPACT].</div></div>
      </div><div class="s-5">${card(`${ic('spark')} Saran AI`, `<p class="fg2" style="font-size:12.5px;margin-bottom:10px">Kandidat risiko untuk proses <b>${esc(d.proc || '—')}</b>. Pilih salah satu untuk mengisi formulir.</p><div class="stack" style="gap:8px">${cands.map((c, i) => `<button type="button" class="chip" style="border-radius:8px;padding:9px 11px" data-act="ai-cand" data-v="${i}"><b style="display:block;color:var(--fg);font-size:13px">${esc(c.name)}</b><span style="font-size:11.5px">${esc(c.cat)} · Karena ${esc(c.cause)}…</span></button>`).join('')}</div>`, { sub: 'simulasi' })}</div></div>`;
    } else if (st === 3) {
      const e = evalStatus(reS, d.cat), t = tax(d.cat);
      body = `<div class="grid g-12">
        <div class="s-4">${card('Risiko inheren', `${mpick('i', d.iL, d.iI)}<div class="hint" style="margin-top:8px">Sebelum memperhitungkan kontrol yang ada.</div>`, { sub: `K${d.iL} × D${d.iI}` })}</div>
        <div class="s-4">${card('Risiko residual', `${mpick('r', d.rL, d.rI)}<div class="hint" style="margin-top:8px">Setelah kontrol eksisting berjalan.</div>`, { sub: `K${d.rL} × D${d.rI}` })}</div>
        <div class="s-4">${card('Hasil analisis & evaluasi', `<div class="stack">
          <div class="row between"><span class="fg2">Inheren</span><span class="row" style="gap:6px">${scoreB(inS)}${lvChip(inS)}</span></div>
          <div class="row between"><span class="fg2">Residual</span><span class="row" style="gap:6px">${scoreB(reS)}${lvChip(reS)}</span></div>
          <div class="row between"><span class="fg2">Penurunan oleh kontrol</span><b class="mono">${inS} → ${reS}</b></div>
          <hr style="border:0;border-top:1px solid var(--line);margin:2px 0;width:100%">
          <div class="row between"><span class="fg2">Appetite ${esc(d.cat)}</span><b class="mono">≤ ${t.app}</b></div>
          <div class="row between"><span class="fg2">Tolerance</span><b class="mono">≤ ${t.tol}</b></div>
          <div class="row between"><span class="fg2">Status evaluasi</span><span class="pill ${e.c}">${e.n}</span></div>
          <p class="hint">${reS > t.app ? 'Residual melebihi risk appetite, sehingga rencana perlakuan wajib disusun.' : 'Residual dalam batas appetite. Perlakuan bersifat opsional.'}</p></div>`)}</div></div>`;
    } else if (st === 4) {
      const TR = [['Hindari', 'Avoid', 'Menghentikan aktivitas yang memunculkan risiko'], ['Kurangi', 'Reduce', 'Menurunkan kemungkinan dan/atau dampak'], ['Bagikan', 'Share', 'Mengalihkan sebagian risiko (asuransi, kontrak, outsourcing)'], ['Terima', 'Retain', 'Menerima risiko dengan keputusan yang terdokumentasi']];
      body = `<div class="stack"><div><div class="lbl" style="margin-bottom:8px">Opsi perlakuan (ISO 31000 6.5.2)</div><div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px">${TR.map(([a, b, c]) => `<button type="button" class="chip" data-act="wz-treat" data-v="${a}" style="border-radius:10px;padding:12px;${d.treat === a ? 'border-color:var(--accent);box-shadow:inset 0 0 0 1px var(--accent);background:var(--accent-soft)' : ''}" aria-pressed="${d.treat === a}"><b style="display:block;color:var(--fg);font-size:14px">${a} <span class="muted mono" style="font-size:11px;font-weight:400">${b}</span></b><span style="font-size:12px">${c}</span></button>`).join('')}</div></div>
        <div><div class="row between" style="margin-bottom:8px"><span class="lbl">Rencana aksi</span><button type="button" class="btn sm" data-act="wz-addact">${ic('plus')}Tambah aksi</button></div>
        <div class="stack" style="gap:8px">${d.actions.map((a, i) => `<div class="form-grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr) minmax(0,1fr) auto;align-items:end;gap:8px"><div class="field"><label for="wa-t${i}">Aksi ${i + 1}</label><input class="inp" id="wa-t${i}" data-wza="${i}:t" value="${esc(a.t)}" placeholder="Uraian tindakan mitigasi"></div><div class="field"><label for="wa-p${i}">PIC</label><input class="inp" id="wa-p${i}" data-wza="${i}:pic" value="${esc(a.pic)}"></div><div class="field"><label for="wa-d${i}">Target</label><input class="inp" type="date" id="wa-d${i}" data-wza="${i}:due" value="${esc(a.due)}"></div><button type="button" class="btn ghost sm" data-act="wz-delact" data-v="${i}" aria-label="Hapus aksi ${i + 1}">${ic('x')}</button></div>`).join('')}</div></div></div>`;
    } else {
      body = `<div class="grid g-12"><div class="s-7 stack">
        <div class="stmt">${stmtHTML(d)}</div>
        <dl class="kv"><dt>Nama risiko</dt><dd><b>${esc(d.name || '—')}</b></dd><dt>Unit kerja</dt><dd>${esc(unitName(+d.unit))}</dd><dt>Risk owner</dt><dd>${esc(person(d.owner).n)}</dd><dt>Proses bisnis</dt><dd>${esc(d.proc || '—')}</dd><dt>Sasaran</dt><dd>${esc(d.obj)}</dd><dt>Kategori</dt><dd>${esc(d.cat)}</dd><dt>Sumber</dt><dd>${esc(d.src1)} · ${esc(d.src2)}</dd><dt>Perlakuan</dt><dd>${esc(d.treat)} · ${d.actions.filter((a) => a.t).length} aksi</dd></dl>
      </div><div class="s-5 stack">${journey([{ l: 'Inheren', a: [d.iL, d.iI] }, { l: 'Residual', a: [d.rL, d.rI] }])}
        ${card('Alur persetujuan', `<div class="wf">${D.WF_STAGES.map((s, i) => `<div class="wf-s ${i === 0 ? 'cur' : ''}"><span class="wf-dot">${i + 1}</span><div><div class="wt">${s}</div><div class="wm">${i === 0 ? 'Anda mengajukan' : 'Menunggu'}</div></div></div>`).join('')}</div>`)}</div></div>`;
    }
    return `${ph('ISO 31000 · 6.4.2 Identifikasi Risiko', 'Identifikasi Risiko', 'Wizard terpandu agar setiap risiko ditulis dengan format yang seragam dan langsung dianalisis terhadap kriteria organisasi.', `<button class="btn" data-act="wz-reset">Kosongkan formulir</button>`)}
    ${z.example ? `<div class="ro-banner">${ic('info')}<span>Formulir berisi <b>contoh isian</b> agar alur dapat langsung dicoba. Pilih “Kosongkan formulir” untuk mulai dari awal.</span></div>` : ''}
    <section class="card"><div class="steps">${steps.map((s, i) => `<button type="button" class="${i + 1 === st ? 'on' : i + 1 < st ? 'done' : ''}" data-act="wz-step" data-v="${i + 1}"><b>${i + 1}. ${s[0]}</b>${s[1]}</button>`).join('')}</div>
    <div class="card-b" style="padding:18px">${body}</div>
    <div class="row between" style="padding:12px 16px;border-top:1px solid var(--line)"><button class="btn" data-act="wz-step" data-v="${st - 1}" ${st === 1 ? 'disabled' : ''}>← Sebelumnya</button><span class="hint">Langkah ${st} dari 5 · tersimpan otomatis sebagai draft</span>${st < 5 ? `<button class="btn pri" data-act="wz-step" data-v="${st + 1}">Lanjut →</button>` : `<button class="btn pri" ${W()} data-act="wz-submit">${ic('send')}Ajukan untuk persetujuan</button>`}</div></section>`;
  };

  /* --- Risk register --- */
  function filteredRisks() {
    const f = S.reg, q = f.q.toLowerCase();
    let rs = S.risks.filter((r) => {
      const s = sc(r.res);
      if (q && !(`${r.id} ${r.name} ${r.cause} ${r.event} ${r.impact} ${unitName(r.unit)} ${r.cat}`.toLowerCase().includes(q))) return false;
      if (f.lv && level(s).k !== f.lv) return false;
      if (f.unit !== '' && String(r.unit) !== String(f.unit)) return false;
      if (f.cat && r.cat !== f.cat) return false;
      if (f.st && r.status !== f.st) return false;
      if (f.cell) { const [L, I] = f.cell.split('-').map(Number); const a = S.heat === 'inh' ? r.inh : r.res; if (a[0] !== L || a[1] !== I) return false; }
      return true;
    });
    const k = f.sort;
    rs.sort((a, b) => (k === 'id' ? a.id.localeCompare(b.id) : k === 'inh' ? sc(b.inh) - sc(a.inh) : k === 'due' ? a.due.localeCompare(b.due) : sc(b.res) - sc(a.res)));
    return rs;
  }
  V.register = function () {
    const f = S.reg, rs = filteredRisks(), pg = paged('reg', rs);
    const chips = [];
    if (f.cell) chips.push(['cell', `Sel heatmap K${f.cell.split('-')[0]}×D${f.cell.split('-')[1]} (${S.heat === 'inh' ? 'inheren' : 'residual'})`]);
    if (f.q) chips.push(['q', `“${f.q}”`]);
    const sts = ['Draft', 'Menunggu Persetujuan', 'Dalam Penanganan', 'Dipantau', 'Ditutup'];
    return `${ph('ISO 31000 · 6.7 Pencatatan', 'Risk Register', 'Pusat penyimpanan seluruh risiko organisasi beserta analisis, kontrol, perlakuan, dan statusnya.', `<button class="btn" data-act="toast" data-v="Risk register diekspor ke Excel (simulasi)">${ic('down')}Excel</button><button class="btn" data-act="toast" data-v="Risk register diekspor ke PDF (simulasi)">${ic('down')}PDF</button><a class="btn pri" href="#identify" ${W()}>${ic('plus')}Risiko baru</a>`)}
    <section class="card"><div class="card-b stack">
      <div class="fbar">
        <input class="inp" id="reg-q" type="search" placeholder="Cari ID, nama, penyebab, unit…" value="${esc(f.q)}" data-reg="q" aria-label="Cari risiko">
        <select class="sel" id="reg-lv" data-reg="lv" aria-label="Level residual"><option value="">Semua level</option>${D.LEVELS.slice().reverse().map((l) => opt(l.k, l.n, f.lv)).join('')}</select>
        <select class="sel" id="reg-unit" data-reg="unit" aria-label="Unit kerja"><option value="">Semua unit</option>${D.UNITS.map((u, i) => opt(i, u, f.unit)).join('')}</select>
        <select class="sel" id="reg-cat" data-reg="cat" aria-label="Kategori"><option value="">Semua kategori</option>${D.TAXONOMY.map((t) => opt(t.k, t.k, f.cat)).join('')}</select>
        <select class="sel" id="reg-st" data-reg="st" aria-label="Status"><option value="">Semua status</option>${sts.map((s) => opt(s, s, f.st)).join('')}</select>
        <select class="sel" id="reg-sort" data-reg="sort" aria-label="Urutkan">${[['res', 'Urut: skor residual'], ['inh', 'Urut: skor inheren'], ['due', 'Urut: target terdekat'], ['id', 'Urut: ID']].map(([v, l]) => opt(v, l, f.sort)).join('')}</select>
      </div>
      <div class="row between"><div class="row" style="gap:6px">${chips.map(([k, l]) => `<span class="fchip">${esc(l)}<button data-act="reg-clear" data-v="${k}" aria-label="Hapus filter">×</button></span>`).join('')}<span class="hint">${rs.length} dari ${S.risks.length} risiko</span></div>${chips.length || f.lv || f.unit !== '' || f.cat || f.st ? '<button class="btn sm ghost" data-act="reg-clear" data-v="all">Reset filter</button>' : ''}</div>
    </div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Risiko</th><th>Kategori</th><th>Pemilik</th><th class="num">Inheren</th><th>Residual</th><th>Evaluasi</th><th>Perlakuan</th><th>Status</th><th>Tren</th><th>Target</th></tr></thead><tbody>
      ${rs.length ? pg.rows.map((r) => { const s = sc(r.res), late = r.due < D.TODAY && r.status !== 'Ditutup'; return `<tr class="click" data-go="risk-${r.id}" tabindex="0"><td class="mono">${r.id}</td><td class="wrap"><div class="t-main">${esc(r.name)}</div><div class="t-sub">${esc(unitName(r.unit))} · ${esc(r.proc)}</div></td><td>${esc(r.cat)}</td><td style="white-space:nowrap">${esc(person(r.owner).n)}</td><td class="num">${scoreB(sc(r.inh))}</td><td><span class="row" style="gap:6px;flex-wrap:nowrap">${scoreB(s)}${lvChip(s)}</span></td><td>${evalPill(s, r.cat)}</td><td>${esc(r.treat)}</td><td>${riskStatusPill(r.status)}</td><td>${trendB(r.trend)}</td><td style="white-space:nowrap" class="${late ? '' : 'fg2'}">${late ? `<span class="pill bad">${fmtDate(r.due)}</span>` : fmtDate(r.due)}</td></tr>`; }).join('') : `<tr><td colspan="11"><div class="empty">Tidak ada risiko yang cocok dengan filter. <button class="btn sm" data-act="reg-clear" data-v="all">Reset filter</button></div></td></tr>`}
    </tbody></table></div>${pager('reg', pg)}</section>`;
  };

  /* --- Detail risiko --- */
  V.risk = function (id) {
    const r = riskById(id);
    if (!r) return `<div class="empty">Risiko ${esc(id)} tidak ditemukan. <a href="#register">Kembali ke register</a></div>`;
    const s = sc(r.res), acts = S.actions.filter((a) => a.risk === r.id), ctrls = S.controls.filter((c) => r.ctrl.includes(c.id)), kris = D.KRIS.filter((k) => r.kri.includes(k.id)), incs = D.INCIDENTS.filter((i) => i.risk === r.id), docs = S.docs.filter((d) => d.ref === r.id || acts.some((a) => a.id === d.ref) || r.ctrl.includes(d.ref)), hist = S.audit.filter((a) => a.ref === r.id || acts.some((x) => x.id === a.ref));
    const obj = D.OBJECTIVES.find((o) => o.id === r.obj);
    const t = S.dtab, tx = tax(r.cat);
    let body = '';
    if (t === 'sum') {
      const st = [{ l: 'Inheren', a: r.inh }, { l: 'Residual saat ini', a: r.res }];
      if (r.proj) st.push({ l: 'Proyeksi setelah mitigasi', a: r.proj });
      st.push({ l: 'Target', a: r.tgt });
      body = `<div class="grid g-12"><div class="s-7 stack">${journey(st)}
        ${card('Informasi risiko', `<dl class="kv"><dt>Unit kerja</dt><dd>${esc(unitName(r.unit))}</dd><dt>Risk owner</dt><dd>${esc(person(r.owner).n)} <span class="muted">· ${esc(person(r.owner).j)}</span></dd><dt>Proses bisnis</dt><dd>${esc(r.proc)}</dd><dt>Sasaran strategis</dt><dd><a href="#objective" data-act="obj" data-v="${r.obj}">${r.obj}</a> · ${esc(obj ? obj.n : '')}</dd><dt>Kategori</dt><dd>${esc(r.cat)}</dd><dt>Sumber penyebab</dt><dd>${esc(r.src)}</dd><dt>Opsi perlakuan</dt><dd>${esc(r.treat)}</dd><dt>Target penyelesaian</dt><dd>${fmtDate(r.due)}${r.due < D.TODAY && r.status !== 'Ditutup' ? ' <span class="pill bad">Terlambat</span>' : ''}</dd></dl>`)}</div>
        <div class="s-5 stack">${card('Evaluasi terhadap kriteria', `<div class="stack"><div class="row between"><span class="fg2">Skor residual</span><span class="row" style="gap:6px">${scoreB(s)}${lvChip(s)}</span></div><div class="row between"><span class="fg2">Risk appetite ${esc(r.cat)}</span><b class="mono">≤ ${tx.app}</b></div><div class="row between"><span class="fg2">Risk tolerance</span><b class="mono">≤ ${tx.tol}</b></div><div class="row between"><span class="fg2">Status</span>${evalPill(s, r.cat)}</div></div>`)}
        ${card('Ringkasan keterkaitan', `<div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">${[['Kontrol', ctrls.length, 'ctl'], ['Action plan', acts.length, 'act'], ['KRI', kris.length, 'kri'], ['Insiden', incs.length, 'inc'], ['Dokumen', docs.length, 'doc'], ['Riwayat', hist.length, 'hist']].map(([a, b, c]) => `<button type="button" class="kpi" data-act="dtab" data-v="${c}" style="cursor:pointer;text-align:left;padding:10px"><span class="k-l">${a}</span><span class="k-v" style="font-size:22px">${b}</span></button>`).join('')}</div>`)}</div></div>`;
    } else if (t === 'ana') {
      const mk = {}; const add = (a, k, tt) => { const key = `${a[0]}-${a[1]}`; (mk[key] = mk[key] || []).push({ k, t: tt }); };
      add(r.inh, 'I', 'Inheren'); add(r.res, 'R', 'Residual'); if (r.proj) add(r.proj, 'P', 'Proyeksi'); add(r.tgt, 'T', 'Target');
      body = `<div class="grid g-12"><div class="s-6">${card('Posisi pada matriks risiko', `${heatmap('res', { markers: mk })}<div class="legend" style="margin-top:10px"><span><b class="mono">I</b> Inheren</span><span><b class="mono">R</b> Residual</span>${r.proj ? '<span><b class="mono">P</b> Proyeksi</span>' : ''}<span><b class="mono">T</b> Target</span></div>`)}</div>
      <div class="s-6">${card('Perhitungan skor', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Tahap</th><th>Kemungkinan</th><th>Dampak</th><th class="num">Skor</th><th>Level</th></tr></thead><tbody>${[['Inheren', r.inh], ['Residual', r.res], r.proj ? ['Proyeksi', r.proj] : null, ['Target', r.tgt]].filter(Boolean).map(([l, a]) => `<tr><td class="t-main">${l}</td><td>${a[0]} · ${D.LIKELIHOOD[a[0] - 1].n}</td><td>${a[1]} · ${D.IMPACT[a[1] - 1].n}</td><td class="num">${scoreB(sc(a))}</td><td>${lvChip(sc(a))}</td></tr>`).join('')}</tbody></table></div><p class="hint" style="padding:10px 16px 0">Skor = Kemungkinan × Dampak. Level mengikuti matriks yang dikonfigurasi di Kriteria Risiko.</p>`, { flush: true, extra: `<button class="btn sm" ${W()} data-act="toast" data-v="Perubahan skor akan masuk alur persetujuan & audit trail">Ubah skor</button>` })}</div></div>`;
    } else if (t === 'ctl') {
      body = ctrls.length ? `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Kontrol</th><th>Jenis</th><th>Frekuensi</th><th>Desain</th><th>Operasi</th><th>Keseluruhan</th></tr></thead><tbody>${ctrls.map((c) => `<tr><td class="mono">${c.id}</td><td><div class="t-main">${esc(c.n)}</div><div class="t-sub">${esc(c.owner)}</div></td><td>${c.type} · ${c.mode}</td><td>${c.freq}</td><td>${effPill(c.des)}</td><td>${effPill(c.ope)}</td><td>${effPill(Math.min(c.des, c.ope))}</td></tr>`).join('')}</tbody></table></div>` : `<div class="empty">Belum ada kontrol yang terhubung. Risiko ini tidak memiliki pengendalian eksisting.<br><a class="btn sm" href="#controls" style="margin-top:10px">Hubungkan kontrol</a></div>`;
      body = card('', body, { flush: true });
    } else if (t === 'act') {
      body = card('', actTable(acts) + `<div class="row" style="padding:12px 16px"><button class="btn sm" ${W()} data-act="toast" data-v="Formulir action plan baru (simulasi)">${ic('plus')}Tambah action plan</button></div>`, { flush: true });
    } else if (t === 'kri') {
      body = kris.length ? `<div class="kri-grid">${kris.map(kriCard).join('')}</div>` : '<div class="empty">Belum ada KRI untuk risiko ini.</div>';
    } else if (t === 'inc') {
      body = incs.length ? card('', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Kejadian</th><th>Tanggal</th><th class="num">Kerugian</th><th>Status</th></tr></thead><tbody>${incs.map((i) => `<tr class="click" data-act="inc-open" data-v="${i.id}"><td class="mono">${i.id}</td><td class="t-main">${esc(i.t)}</td><td>${fmtDate(i.date)}</td><td class="num">${rp(i.loss)}</td><td>${incPill(i.status)}</td></tr>`).join('')}</tbody></table></div>`, { flush: true }) : '<div class="empty">Belum ada insiden yang tercatat untuk risiko ini.</div>';
    } else if (t === 'doc') {
      body = card('', docTable(docs), { flush: true });
    } else {
      body = card('', auditTable(hist.length ? hist : []), { flush: true });
    }
    return `<div class="crumb"><a href="#register">Risk Register</a> <span>/</span> <span class="mono">${r.id}</span></div>
    ${ph(`${r.id} · ${esc(r.cat)} · ${esc(unitName(r.unit))}`, esc(r.name), '', `<span class="row" style="gap:6px">${riskStatusPill(r.status)}${trendB(r.trend)}</span><button class="btn" ${W()} data-act="toast" data-v="Mode ubah risiko (simulasi)">Ubah</button><a class="btn pri" href="#workflow">${ic('inbox')}Alur persetujuan</a>`)}
    <div class="stmt">${stmtHTML(r)}</div>
    ${tabs('dtab', [['sum', 'Ringkasan'], ['ana', 'Analisis'], ['ctl', 'Kontrol', ctrls.length], ['act', 'Mitigasi', acts.length], ['kri', 'KRI', kris.length], ['inc', 'Insiden', incs.length], ['doc', 'Dokumen', docs.length], ['hist', 'Riwayat', hist.length]], t)}
    ${body}`;
  };
  const effPill = (v) => `<span class="pill ${['', 'bad', 'warn', 'ok', 'ok'][v]}">${D.EFF[v]}</span>`;
  const incPill = (s) => `<span class="pill ${s === 'Ditutup' ? 'ok' : s === 'Investigasi' ? 'bad' : 'warn'}">${s}</span>`;
  function actTable(acts) {
    if (!acts.length) return '<div class="empty">Belum ada action plan.</div>';
    return `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Rencana aksi</th><th>Risiko</th><th>PIC</th><th class="num">Anggaran</th><th>Target</th><th>Progres</th><th>Status</th><th class="num">Bukti</th></tr></thead><tbody>${acts.map((a) => { const st = actStatus(a); return `<tr><td class="mono">${a.id}</td><td class="wrap"><div class="t-main">${esc(a.t)}</div><div class="t-sub">Prioritas ${a.prio}</div></td><td><a class="mono" href="#risk-${a.risk}">${a.risk}</a></td><td style="white-space:nowrap">${esc(a.pic)}</td><td class="num">${a.budget ? rp(a.budget) : '—'}</td><td style="white-space:nowrap">${fmtDate(a.due)}</td><td>${prog(a.prog, st.k)}</td><td><span class="pill ${st.c}">${st.n}</span></td><td class="num">${a.ev ? `<span class="pill">${a.ev} file</span>` : '<span class="muted">—</span>'}</td></tr>`; }).join('')}</tbody></table></div>`;
  }
  function docTable(docs) {
    if (!docs.length) return '<div class="empty">Belum ada dokumen.</div>';
    return `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Dokumen</th><th>Jenis</th><th>Terkait</th><th>Versi</th><th>Diunggah</th><th>Kedaluwarsa</th><th>Status</th><th></th></tr></thead><tbody>${docs.map((d) => { const soon = d.exp !== '—' && days(d.exp, D.TODAY) <= 60; return `<tr><td class="t-main" style="min-width:220px">${ic('file')} ${esc(d.n)}</td><td>${esc(d.type)}</td><td class="mono">${esc(d.ref)}</td><td class="mono">${esc(d.v)}</td><td style="white-space:nowrap">${esc(d.by)}<div class="t-sub">${fmtDate(d.d)}</div></td><td style="white-space:nowrap">${soon ? `<span class="pill warn">${fmtDate(d.exp)} · ${days(d.exp, D.TODAY)} hari</span>` : fmtDate(d.exp)}</td><td><span class="pill ${d.st === 'Disetujui' ? 'ok' : d.st === 'Review' ? 'warn' : ''}">${esc(d.st)}</span></td><td><button class="btn sm ghost" data-act="toast" data-v="Mengunduh ${esc(d.n)} (simulasi)" aria-label="Unduh">${ic('down')}</button></td></tr>`; }).join('')}</tbody></table></div>`;
  }
  function auditTable(rows) {
    if (!rows.length) return '<div class="empty">Belum ada aktivitas tercatat.</div>';
    return `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Objek</th><th>Field</th><th>Sebelum → Sesudah</th><th>IP</th></tr></thead><tbody>${rows.map((a) => `<tr><td class="mono" style="white-space:nowrap">${fmtDate(a.t.slice(0, 10))}, ${a.t.slice(11)}</td><td style="white-space:nowrap"><b>${esc(a.u)}</b></td><td>${esc(a.a)}</td><td class="mono">${esc(a.ref)}</td><td class="fg2">${esc(a.f)}</td><td style="min-width:200px"><span class="mono muted">${esc(a.p)}</span> → <b class="mono">${esc(a.n)}</b></td><td class="mono muted">${esc(a.ip)}</td></tr>`).join('')}</tbody></table></div>`;
  }

  V.analysis = function () {
    const L = S.anaL, I = S.anaI, s = L * I, cat = S.anaCat, e = evalStatus(s, cat), t = tax(cat);
    const rows = S.risks.filter((r) => r.status !== 'Ditutup').map((r) => ({ r, s: sc(r.res), e: evalStatus(sc(r.res), r.cat) }));
    const order = ['Kritis', 'Perlu Eskalasi', 'Perlu Penanganan', 'Dipantau', 'Dapat Diterima'];
    rows.sort((a, b) => order.indexOf(a.e.n) - order.indexOf(b.e.n) || b.s - a.s);
    return `${ph('ISO 31000 · 6.4.3 Analisis & 6.4.4 Evaluasi', 'Analisis & Evaluasi Risiko', 'Skor dihitung otomatis dari Kemungkinan × Dampak, lalu dibandingkan dengan risk appetite dan tolerance setiap kategori.')}
    <div class="grid g-12">
      <div class="s-5">${card('Kalkulator risiko', `${mpick('a', L, I)}<div class="field" style="margin-top:14px"><label for="ana-cat">Kategori (menentukan appetite)</label><select id="ana-cat" data-anacat>${D.TAXONOMY.map((x) => opt(x.k, x.k, cat)).join('')}</select></div>`, { sub: 'klik sel matriks' })}</div>
      <div class="s-7">${card('Hasil', `<div class="row" style="gap:18px;align-items:center;flex-wrap:wrap"><div><div class="lbl">Skor</div><div style="font-family:var(--f-display);font-size:56px;font-weight:600;line-height:1" class="tnum">${s}</div><div class="mono fg2" style="margin-top:4px">${L} × ${I}</div></div><div class="stack" style="gap:8px;flex:1;min-width:220px"><div class="row between"><span class="fg2">Level</span>${lvChip(s)}</div><div class="row between"><span class="fg2">Appetite / tolerance</span><b class="mono">≤ ${t.app} / ≤ ${t.tol}</b></div><div class="row between"><span class="fg2">Status evaluasi</span><span class="pill ${e.c}">${e.n}</span></div></div></div>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:16px"><div style="border:1px solid var(--line);border-radius:8px;padding:12px"><div class="lbl">Kemungkinan ${L} · ${D.LIKELIHOOD[L - 1].n}</div><p style="margin-top:4px;font-size:13px">${D.LIKELIHOOD[L - 1].id}: ${D.LIKELIHOOD[L - 1].d}</p></div><div style="border:1px solid var(--line);border-radius:8px;padding:12px"><div class="lbl">Dampak ${I} · ${D.IMPACT[I - 1].n}</div><p style="margin-top:4px;font-size:13px">Keuangan ${D.IMPACT[I - 1].fin}; operasional ${D.IMPACT[I - 1].ops.toLowerCase()}; ${D.IMPACT[I - 1].rep.toLowerCase()}.</p></div></div>
        <div class="tbl-wrap" style="margin-top:14px"><table class="tbl"><thead><tr><th>Status evaluasi</th><th>Aturan</th><th>Tindakan</th></tr></thead><tbody>${[['ok', 'Dapat Diterima', 'skor ≤ appetite', 'Dipantau rutin'], ['run', 'Dipantau', 'appetite < skor ≤ tolerance', 'Pemantauan KRI'], ['warn', 'Perlu Penanganan', 'skor > tolerance', 'Rencana mitigasi wajib'], ['bad', 'Perlu Eskalasi', 'skor 16–19', 'Lapor ke Risk Manager & Direktur'], ['bad', 'Kritis', 'skor ≥ 20', 'Perhatian segera pimpinan']].map(([c, a, b, d]) => `<tr style="${a === e.n ? 'background:var(--accent-soft)' : ''}"><td><span class="pill ${c}">${a}</span></td><td class="mono" style="font-size:12px">${b}</td><td class="fg2">${d}</td></tr>`).join('')}</tbody></table></div>`)}</div>
      <div class="s-12">${card('Evaluasi seluruh risiko aktif', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Risiko</th><th>Kategori</th><th>Residual</th><th class="num">Appetite</th><th class="num">Tolerance</th><th class="num">Selisih</th><th>Status evaluasi</th></tr></thead><tbody>${rows.map(({ r, s: v, e: ev }) => { const tt = tax(r.cat), gap = v - tt.app; return `<tr class="click" data-go="risk-${r.id}"><td class="mono">${r.id}</td><td class="t-main">${esc(r.name)}</td><td>${esc(r.cat)}</td><td><span class="row" style="gap:6px;flex-wrap:nowrap">${scoreB(v)}${lvChip(v)}</span></td><td class="num">${tt.app}</td><td class="num">${tt.tol}</td><td class="num mono" style="color:${gap > 0 ? 'var(--bad-ink)' : 'var(--ok-ink)'}">${gap > 0 ? '+' : ''}${gap}</td><td><span class="pill ${ev.c}">${ev.n}</span></td></tr>`; }).join('')}</tbody></table></div>`, { flush: true, sub: 'residual vs appetite per kategori' })}</div>
    </div>`;
  };

  V.treatment = function () {
    const all = S.actions, st = (a) => actStatus(a).k;
    const c = (k) => all.filter((a) => st(a) === k).length;
    const f = S.treatFilter, acts = f ? all.filter((a) => st(a) === f) : all;
    const cols = [['todo', 'Belum Mulai'], ['run', 'Berjalan'], ['late', 'Terlambat'], ['done', 'Selesai'], ['cancel', 'Dibatalkan']];
    const pg = paged('act', acts.slice().sort((a, b) => ({ late: 0, run: 1, todo: 2, done: 3, cancel: 4 })[st(a)] - ({ late: 0, run: 1, todo: 2, done: 3, cancel: 4 })[st(b)] || a.due.localeCompare(b.due)));
    const view = S.treatView === 'kanban'
      ? `<div class="kanban">${cols.map(([k, n]) => `<div class="kcol"><h4>${n}<span class="mono muted">${c(k)}</span></h4>${all.filter((a) => st(a) === k).sort((a, b) => a.due.localeCompare(b.due)).slice(0, 10).map((a) => `<div class="kcard"><div class="row between"><span class="mono muted" style="font-size:11px">${a.id} · <a href="#risk-${a.risk}">${a.risk}</a></span><span class="pill ${a.prio === 'Kritis' ? 'bad' : a.prio === 'Tinggi' ? 'warn' : ''}">${a.prio}</span></div><div class="kt">${esc(a.t)}</div>${prog(a.prog, k)}<div class="km"><span>${esc(a.pic)}</span><span>${fmtDate(a.due)}</span></div></div>`).join('') || '<div class="hint" style="padding:6px">Kosong</div>'}${c(k) > 10 ? `<button type="button" class="btn sm ghost" data-act="tfilter" data-v="${k}">+${c(k) - 10} lainnya →</button>` : ''}</div>`).join('')}</div>`
      : card('', actTable(pg.rows) + pager('act', pg), { flush: true });
    return `${ph('ISO 31000 · 6.5 Perlakuan Risiko', 'Mitigasi & Action Plan', 'Setiap risiko dapat memiliki beberapa rencana aksi dengan PIC, tenggat, progres, dan bukti pelaksanaan.', `${seg('tview', [['list', 'Tabel'], ['kanban', 'Kanban']], S.treatView)}<button class="btn pri" ${W()} data-act="toast" data-v="Formulir action plan baru (simulasi)">${ic('plus')}Action plan</button>`)}
    <div class="kpis">
      ${[['', 'Semua', all.length, ''], ['run', 'Berjalan', c('run'), 'var(--accent)'], ['late', 'Terlambat', c('late'), 'var(--lv-vh)'], ['todo', 'Belum mulai', c('todo'), 'var(--muted)'], ['done', 'Selesai', c('done'), 'var(--lv-l)']].map(([k, n, v, col]) => `<button type="button" class="kpi" data-act="tfilter" data-v="${k}" style="cursor:pointer;text-align:left;${col ? `border-top:3px solid ${col};` : ''}${f === k ? 'outline:2px solid var(--accent);outline-offset:-1px' : ''}" aria-pressed="${f === k}"><span class="k-l">${n}</span><span class="k-v">${v}</span><span class="k-s">${k === 'late' ? 'notifikasi otomatis ke PIC' : 'klik untuk menyaring'}</span></button>`).join('')}
    </div>
    ${c('late') ? `<div class="ro-banner" style="background:color-mix(in oklab,var(--lv-vh) 12%,var(--surface))"><span style="color:var(--bad-ink)">${ic('alert')}</span><span><b>${c('late')} action plan melewati tenggat.</b> Notifikasi telah dikirim ke PIC dan Risk Owner terkait.</span></div>` : ''}
    ${view}`;
  };

  V.controls = function () {
    const cs = S.controls, eff = cs.filter((c) => Math.min(c.des, c.ope) >= 3).length;
    const sel = S.ctrlSel && cs.find((c) => c.id === S.ctrlSel);
    return `${ph('ISO 31000 · 6.4.3 & 6.6', 'Kontrol & Efektivitas', 'Daftar pengendalian yang sudah berjalan beserta penilaian efektivitas desain dan operasinya. Kontrol menentukan selisih antara risiko inheren dan residual.', `<button class="btn pri" ${W()} data-act="toast" data-v="Formulir kontrol baru (simulasi)">${ic('plus')}Kontrol</button>`)}
    <div class="kpis">${kpi('Kontrol terdaftar', cs.length, `${S.risks.filter((r) => !r.ctrl.length && r.status !== 'Ditutup').length} risiko belum punya kontrol`)}${kpi('Efektif', `${eff}`, `${Math.round((eff / cs.length) * 100)}% dari kontrol`, 'lvl', '--c:var(--lv-l)')}${kpi('Sebagian / tidak efektif', cs.length - eff, 'perlu perbaikan', 'lvl', '--c:var(--lv-h)')}${kpi('Otomatis', cs.filter((c) => c.mode === 'Otomatis').length, `${cs.filter((c) => c.mode === 'Manual').length} manual`)}</div>
    <div class="grid g-12"><div class="${sel ? 's-8' : 's-12'}">${card('Control register', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Kontrol</th><th>Sifat</th><th>Frekuensi</th><th>Desain</th><th>Operasi</th><th>Keseluruhan</th><th>Risiko</th></tr></thead><tbody>${cs.map((c) => `<tr class="click" data-act="ctrl-open" data-v="${c.id}" style="${sel && sel.id === c.id ? 'background:var(--accent-soft)' : ''}"><td class="mono">${c.id}</td><td class="wrap"><div class="t-main">${esc(c.n)}</div><div class="t-sub">${esc(c.owner)}</div></td><td style="white-space:nowrap">${c.type}<div class="t-sub">${c.mode}</div></td><td>${c.freq}</td><td>${effPill(c.des)}</td><td>${effPill(c.ope)}</td><td>${effPill(Math.min(c.des, c.ope))}</td><td class="mono" style="font-size:12px">${S.risks.filter((r) => r.ctrl.includes(c.id)).map((r) => `<a href="#risk-${r.id}">${r.id}</a>`).join(' ')}</td></tr>`).join('')}</tbody></table></div>`, { flush: true, sub: 'klik baris untuk menilai' })}</div>
    ${sel ? `<div class="s-4">${card(`${sel.id} · Penilaian`, `<div class="stack"><div><b>${esc(sel.n)}</b><p class="hint" style="margin-top:4px">${esc(sel.obj)}</p></div><dl class="kv" style="grid-template-columns:110px minmax(0,1fr)"><dt>Pemilik</dt><dd>${esc(sel.owner)}</dd><dt>Sifat</dt><dd>${sel.type} · ${sel.mode}</dd><dt>Uji terakhir</dt><dd>${fmtDate(sel.last)}</dd></dl>
      <div class="field"><label for="c-des">Efektivitas desain</label><select id="c-des" data-ctrl="des" ${canWrite() ? '' : 'disabled'}>${[1, 2, 3, 4].map((v) => opt(v, D.EFF[v], sel.des)).join('')}</select><span class="hint">Apakah kontrol dirancang tepat untuk menurunkan risiko?</span></div>
      <div class="field"><label for="c-ope">Efektivitas operasi</label><select id="c-ope" data-ctrl="ope" ${canWrite() ? '' : 'disabled'}>${[1, 2, 3, 4].map((v) => opt(v, D.EFF[v], sel.ope)).join('')}</select><span class="hint">Apakah kontrol benar-benar berjalan sesuai desain?</span></div>
      <div class="row between"><span class="fg2">Hasil keseluruhan</span>${effPill(Math.min(sel.des, sel.ope))}</div>
      <label class="drop" for="c-ev">${ic('upload')} Unggah bukti pengujian<input type="file" id="c-ev" class="sr" data-upload="${sel.id}"></label>
      <button class="btn" data-act="ctrl-close">Tutup</button></div>`)}</div>` : ''}</div>`;
  };

  V.incidents = function () {
    const inc = D.INCIDENTS.find((i) => i.id === S.incSel) || D.INCIDENTS[0];
    const r = riskById(inc.risk), ctrls = r ? S.controls.filter((c) => r.ctrl.includes(c.id)) : [];
    const tot26 = D.LOSS_HISTORY.filter((l) => l.y === 2026).reduce((s, l) => s + l.loss, 0);
    const byYear = [2024, 2025, 2026].map((y) => [String(y), D.LOSS_HISTORY.filter((l) => l.y === y).reduce((s, l) => s + l.loss, 0)]);
    return `${ph('ISO 31000 · 6.6 & 6.7', 'Insiden & Loss Event', 'Risiko yang benar-benar terjadi dicatat sebagai insiden dan dihubungkan kembali ke risiko, kontrol, dan tindakan korektif. Riwayat kerugian menjadi dasar analisis berikutnya.', `<button class="btn pri" ${W()} data-act="toast" data-v="Formulir laporan insiden (simulasi)">${ic('plus')}Laporkan insiden</button>`)}
    <div class="kpis">${kpi('Insiden 2026', D.INCIDENTS.filter((i) => i.date.startsWith('2026')).length, 'tercatat')}${kpi('Masih terbuka', D.INCIDENTS.filter((i) => i.status !== 'Ditutup').length, 'investigasi / tindakan korektif', 'lvl', '--c:var(--lv-h)')}${kpi('Kerugian 2026', rp(tot26), 'loss event database')}${kpi('Rata-rata pemulihan', '9,5 jam', 'insiden layanan')}</div>
    <div class="grid g-12">
      <div class="s-5">${card('Daftar insiden', `<div class="rlist">${D.INCIDENTS.map((i) => `<button type="button" class="ritem" data-act="inc-open" data-v="${i.id}" style="border-left:0;border-right:0;border-bottom:0;background:${i.id === inc.id ? 'var(--accent-soft)' : 'transparent'};text-align:left;font:inherit;width:100%;grid-template-columns:minmax(0,1fr) auto"><div style="min-width:0"><div class="rn">${esc(i.t)}</div><div class="rs"><span class="mono">${i.id}</span> · ${fmtDate(i.date)} · ${rp(i.loss)}</div></div>${incPill(i.status)}</button>`).join('')}</div>`, { flush: true })}</div>
      <div class="s-7">${card(`<span class="mono">${inc.id}</span>`, `<div class="stack"><h3 style="font-size:18px">${esc(inc.t)}</h3>
        <div class="chain" style="grid-template-columns:repeat(4,minmax(0,1fr))">
          <div class="cnode"><div class="cl">Risiko</div><div class="cv"><a href="#risk-${inc.risk}">${inc.risk}</a> ${r ? esc(r.name) : ''}</div></div>
          <div class="cnode"><div class="cl">Kontrol</div><div class="cv">${ctrls.length ? ctrls.map((c) => esc(c.n)).join(', ') : '<span class="muted">Belum ada</span>'}</div></div>
          <div class="cnode"><div class="cl">Insiden</div><div class="cv">${fmtDate(inc.date)}</div><div class="cs">${esc(inc.loc)}</div></div>
          <div class="cnode"><div class="cl">Tindakan korektif</div><div class="cv">${esc(inc.corrective)}</div></div>
        </div>
        <dl class="kv"><dt>Kronologi</dt><dd>${esc(inc.chrono)}</dd><dt>Penyebab</dt><dd>${esc(inc.cause)}</dd><dt>Dampak</dt><dd>${esc(inc.impact)}</dd><dt>Kerugian</dt><dd><b>${rp(inc.loss)}</b></dd><dt>Respons</dt><dd>${esc(inc.response)}</dd><dt>Status</dt><dd>${incPill(inc.status)}</dd></dl></div>`)}</div>
      <div class="s-7">${card('Loss event database', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Tahun</th><th>Risiko</th><th>Kejadian</th><th class="num">Kerugian</th></tr></thead><tbody>${D.LOSS_HISTORY.map((l) => `<tr><td class="mono">${l.y}</td><td>${esc(l.risk)}</td><td class="fg2">${esc(l.ev)}</td><td class="num">${rp(l.loss)}</td></tr>`).join('')}</tbody></table></div>`, { flush: true })}</div>
      <div class="s-5">${card('Kerugian per tahun', barList(byYear, { unit: 'juta rupiah' }) + '<p class="hint" style="margin-top:10px">Dalam juta rupiah. Data historis menjadi masukan penilaian kemungkinan & dampak.</p>')}</div>
    </div>`;
  };

  V.review = function () {
    const rows = D.REVIEWS.map((v) => ({ v, r: riskById(v.r) })).filter((x) => x.r);
    const tr = (a, b) => (b < a ? ['down', 'Membaik'] : b > a ? ['up', 'Memburuk'] : ['flat', 'Stabil']);
    const cnt = (k) => rows.filter((x) => tr(x.v.prev, x.v.cur)[0] === k).length;
    return `${ph('ISO 31000 · 6.6 Pemantauan & Reviu', 'Risk Review', 'Reviu berkala membandingkan posisi risiko periode sebelumnya dengan kondisi terkini.', `<select class="sel" id="rev-per" aria-label="Periode reviu">${['TW III 2026', 'TW II 2026', 'Semester I 2026', 'Tahunan 2025'].map((p) => opt(p, `Reviu ${p}`, S.revPeriod)).join('')}</select><button class="btn pri" ${W()} data-act="toast" data-v="Berita acara reviu disusun (simulasi)">Susun berita acara</button>`)}
    <div class="kpis">${kpi('Risiko direviu', rows.length, `${S.risks.filter((r) => r.status !== 'Ditutup').length} risiko aktif`)}${kpi('<span class="trend down">▼</span>Membaik', cnt('down'), '')}${kpi('<span class="trend flat">▶</span>Stabil', cnt('flat'), '')}${kpi('<span class="trend up">▲</span>Memburuk', cnt('up'), 'perlu keputusan manajemen')}</div>
    <div class="grid g-12"><div class="s-8">${card('Hasil reviu', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Risiko</th><th>Sebelumnya (TW II)</th><th>Saat ini (TW III)</th><th>Tren</th><th>Catatan reviu</th></tr></thead><tbody>${rows.map(({ v, r }) => { const t = tr(v.prev, v.cur); return `<tr class="click" data-go="risk-${r.id}"><td><div class="t-main">${esc(r.name)}</div><div class="t-sub mono">${r.id}</div></td><td>${lvChip(v.prev)}</td><td>${lvChip(v.cur)}</td><td><span class="trend ${t[0]}">${{ up: '▲', down: '▼', flat: '▶' }[t[0]]} ${t[1]}</span></td><td class="fg2" style="min-width:240px">${esc(v.note)}</td></tr>`; }).join('')}</tbody></table></div>`, { flush: true })}</div>
    <div class="s-4">${card('Jadwal reviu', `<div class="wf">${[['Monthly Risk Review', 'Setiap Senin pertama · Risk Officer', 'done', '6 Okt 2026'], ['Quarterly Risk Review', 'Komite Manajemen Risiko', 'cur', '15 Okt 2026'], ['Semester Review', 'Pimpinan & Eselon I', '', 'Jan 2027'], ['Annual Risk Assessment', 'Seluruh unit kerja', '', 'Des 2026']].map(([a, b, c, d], i) => `<div class="wf-s ${c}"><span class="wf-dot">${c === 'done' ? '✓' : i + 1}</span><div><div class="wt">${a}</div><div class="wm">${b} · ${d}</div></div></div>`).join('')}</div>`)}</div></div>`;
  };

  V.improve = function () {
    const src = [['Insiden', 5, 'alert'], ['Temuan audit', 6, 'search'], ['Kegagalan kontrol', 3, 'shield'], ['Pelanggaran KRI', 4, 'gauge'], ['Lessons learned', 7, 'book']];
    return `${ph('ISO 31000 · 5.7 Perbaikan', 'Perbaikan Berkelanjutan', 'Insiden, temuan audit, kegagalan kontrol, dan pelanggaran KRI dianalisis untuk menghasilkan rencana perbaikan kerangka dan proses manajemen risiko.', `<button class="btn pri" ${W()} data-act="toast" data-v="Rencana perbaikan baru (simulasi)">${ic('plus')}Rencana perbaikan</button>`)}
    <div class="kpis">${src.map(([a, b, i]) => kpi(`${ic(i)}${a}`, b, 'sumber masukan 2026')).join('')}</div>
    <div class="grid g-12"><div class="s-8">${card('Improvement plan', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Rencana perbaikan</th><th>Sumber</th><th>PIC</th><th>Target</th><th>Status</th></tr></thead><tbody>${D.IMPROVE.map((p) => `<tr><td class="mono">${p.id}</td><td class="t-main wrap">${esc(p.t)}</td><td style="white-space:nowrap">${p.src}<div class="t-sub mono">${esc(p.ref)}</div></td><td>${esc(p.pic)}</td><td style="white-space:nowrap">${fmtDate(p.due)}</td><td><span class="pill ${p.st === 'Selesai' ? 'ok' : p.st === 'Berjalan' ? 'run' : ''}">${p.st}</span></td></tr>`).join('')}</tbody></table></div>`, { flush: true })}</div>
    <div class="s-4">${card('Lessons learned terbaru', `<div class="stack">${[['INC-2026-014', 'Unit pendingin cadangan harus diuji bulanan, bukan hanya saat perbaikan.'], ['INC-2026-013', 'Data mutasi pegawai perlu memicu penonaktifan akun secara otomatis.'], ['INC-2026-006', 'EDR efektif; titik lemah ada pada kesadaran pegawai terhadap phishing.']].map(([a, b]) => `<div><div class="mono muted" style="font-size:11px">${a}</div><p style="font-size:13px;margin-top:2px">${b}</p></div>`).join('')}</div>`)}</div></div>`;
  };

  V.objective = function () {
    const CH = {
      'SS-1': [['Program', 'Digitalisasi pelayanan', 'Pagu Rp 42,6 M'], ['Proses', 'Pengelolaan aplikasi pelayanan', 'Direktorat TI'], ['Risiko', 'R-001 Aplikasi tidak tersedia', 'Residual 12 · Tinggi', 'R-001'], ['Kontrol', 'High availability + backup harian', 'C-01, C-02'], ['KRI', 'Downtime sistem layanan', '3,4 jam · Kritis']],
      'SS-2': [['Program', 'Pengelolaan keuangan negara', 'Pagu Rp 8,1 M'], ['Proses', 'Pembayaran vendor', 'Biro Keuangan'], ['Risiko', 'R-005 Pembayaran ganda', 'Residual 6 · Sedang', 'R-005'], ['Kontrol', 'Verifikasi tagihan dua tingkat', 'C-08'], ['KRI', 'Temuan audit belum ditindaklanjuti', '5 temuan · Normal']],
      'SS-3': [['Program', 'Keamanan & ketahanan siber', 'Pagu Rp 15,3 M'], ['Proses', 'Keamanan informasi', 'Subdit Keamanan Informasi'], ['Risiko', 'R-002 Serangan ransomware', 'Residual 16 · Sangat Tinggi', 'R-002'], ['Kontrol', 'EDR + patch management', 'C-05, C-04'], ['KRI', 'Insiden keamanan terkonfirmasi', '4 / bulan · Waspada']],
      'SS-4': [['Program', 'Pengembangan talenta digital', 'Pagu Rp 3,2 M'], ['Proses', 'Perencanaan kebutuhan SDM', 'Biro SDM'], ['Risiko', 'R-009 Kekurangan SDM siber', 'Residual 16 · Sangat Tinggi', 'R-009'], ['Kontrol', 'Belum ada kontrol', 'Kesenjangan pengendalian', null, true], ['KRI', 'Posisi kunci tanpa pengganti', '6 posisi · Kritis']]
    };
    const o = D.OBJECTIVES.find((x) => x.id === S.objSel), ch = CH[o.id];
    return `${ph('ISO 31000 · 5.3 Integrasi', 'Pemetaan Sasaran Strategis', 'Setiap risiko ditelusuri sampai sasaran organisasi, sehingga manajemen risiko terhubung langsung dengan kinerja.', '')}
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px">${D.OBJECTIVES.map((x) => `<button type="button" class="kpi" data-act="obj" data-v="${x.id}" style="cursor:pointer;text-align:left;${x.id === o.id ? 'outline:2px solid var(--accent);outline-offset:-1px;background:var(--accent-soft)' : ''}" aria-pressed="${x.id === o.id}"><span class="k-l mono">${x.id}</span><span style="font-weight:600;font-size:14px;line-height:1.35">${esc(x.n)}</span><span class="k-s">IKU: ${esc(x.ik)} · ${x.cnt} risiko</span></button>`).join('')}</div>
    ${card('Rantai keterkaitan', `<div class="chain"><div class="cnode"><div class="cl">Sasaran</div><div class="cv">${esc(o.n)}</div><div class="cs">${esc(o.ik)}</div></div>${ch.map(([l, v, s2, rid, gap]) => `<div class="cnode" style="${gap ? 'background:color-mix(in oklab,var(--lv-vh) 10%,var(--surface))' : ''}"><div class="cl" style="${gap ? 'color:var(--bad-ink)' : ''}">${l}</div><div class="cv">${rid ? `<a href="#risk-${rid}">${esc(v)}</a>` : esc(v)}</div><div class="cs">${esc(s2)}</div></div>`).join('')}</div>`, { sub: 'Sasaran → Program → Proses → Risiko → Kontrol → KRI' })}
    ${card('Cakupan per sasaran', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Sasaran</th><th class="num">Risiko</th><th class="num">Sangat Tinggi</th><th class="num">Tinggi</th><th>Cakupan kontrol</th><th class="num">KRI</th></tr></thead><tbody>${D.OBJECTIVES.map((x) => { const cov = x.cov; return `<tr class="click" data-act="obj" data-v="${x.id}"><td><span class="mono muted">${x.id}</span> ${esc(x.n)}</td><td class="num">${x.cnt}</td><td class="num">${x.vh}</td><td class="num">${x.h}</td><td>${prog(cov, cov < 75 ? 'late' : '')}</td><td class="num">${x.kri}</td></tr>`; }).join('')}</tbody></table></div>`, { flush: true })}`;
  };

  V.governance = function () {
    return `${ph('ISO 31000 · 5.4 Desain Kerangka', 'Taksonomi & Risk Appetite', 'Klasifikasi risiko organisasi dan batas selera risiko per kategori. Nilai ini dipakai otomatis saat evaluasi risiko.', `<button class="btn pri" ${W()} data-act="toast" data-v="Kategori baru (simulasi)">${ic('plus')}Kategori</button>`)}
    ${card('Taksonomi risiko', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Kategori</th><th>Deskripsi</th><th class="num">Risiko</th><th class="num">Appetite</th><th class="num">Tolerance</th><th>Sikap</th><th></th></tr></thead><tbody>${D.TAXONOMY.map((t) => `<tr><td><div class="t-main">${t.k}</div><div class="t-sub">${t.en}</div></td><td class="fg2" style="min-width:260px">${t.d}</td><td class="num">${t.cnt}</td><td class="num">${scoreB(t.app)}</td><td class="num">${scoreB(t.tol)}</td><td><span class="pill ${t.app <= 4 ? 'bad' : t.app >= 8 ? 'ok' : 'run'}">${t.app <= 4 ? 'Averse' : t.app >= 8 ? 'Moderat' : 'Hati-hati'}</span></td><td><button class="btn sm ghost" ${W()} data-act="toast" data-v="Ubah appetite ${t.k} (simulasi, tercatat di audit trail)">Ubah</button></td></tr>`).join('')}</tbody></table></div>`, { flush: true, sub: 'skor residual maksimum' })}
    <div class="grid g-12"><div class="s-6">${card('Pernyataan risk appetite', `<p style="font-size:13.5px;line-height:1.65">BLDN <b>tidak menoleransi</b> risiko yang mengancam keamanan data pribadi, kepatuhan hukum, dan integritas pengadaan. BLDN <b>bersedia mengambil risiko moderat</b> dalam inovasi layanan digital dan pengembangan SDM sepanjang dampak keuangan di bawah Rp500 jt per kejadian.</p><p class="hint" style="margin-top:8px">Ditetapkan Keputusan Kepala BLDN No. 12/2026 · 12 Feb 2026</p>`)}</div>
    <div class="s-6">${card('Kebijakan & kerangka', docTable(S.docs.filter((d) => d.ref === 'Kerangka')), { flush: true })}</div></div>`;
  };

  V.iso = function () {
    const PR = [['Terintegrasi', 'ok'], ['Terstruktur & komprehensif', 'ok'], ['Disesuaikan', 'ok'], ['Inklusif', 'warn'], ['Dinamis', 'warn'], ['Informasi terbaik yang tersedia', 'ok'], ['Faktor manusia & budaya', 'warn'], ['Perbaikan berkelanjutan', 'bad']];
    const FW = [['5.2', 'Kepemimpinan & komitmen', 90], ['5.3', 'Integrasi', 72], ['5.4', 'Desain', 85], ['5.5', 'Implementasi', 68], ['5.6', 'Evaluasi', 55], ['5.7', 'Perbaikan', 40]];
    const PC = [['6.2', 'Komunikasi & konsultasi', 'context'], ['6.3', 'Ruang lingkup, konteks & kriteria', 'context'], ['6.4', 'Penilaian risiko', 'identify'], ['6.5', 'Perlakuan risiko', 'treatment'], ['6.6', 'Pemantauan & reviu', 'kri'], ['6.7', 'Pencatatan & pelaporan', 'reports']];
    const lab = { ok: 'Terpenuhi', warn: 'Sebagian', bad: 'Belum' };
    return `${ph('ISO 31000:2018 · Klausul 4, 5, 6', 'Kerangka ISO 31000', 'Peta penerapan prinsip, kerangka kerja, dan proses ISO 31000 di organisasi, lengkap dengan tautan ke modul aplikasi.', '')}
    <div class="kpis">${kpi('Indeks maturitas', '3,4 <span style="font-size:15px" class="muted">/ 5</span>', 'Level 3 · Terdefinisi')}${kpi('Prinsip terpenuhi', '4 / 8', '3 sebagian')}${kpi('Kerangka', '68%', 'rata-rata implementasi')}${kpi('Asesmen berikutnya', 'Des 2026', 'self-assessment tahunan')}</div>
    <div class="iso-grid">
      ${card('Prinsip', PR.map(([n, c], i) => `<div class="irow"><span><span class="ic-n">4.${String.fromCharCode(97 + i)}</span>${n}</span><span class="pill ${c}" style="justify-self:end">${lab[c]}</span></div>`).join(''), { flush: true, sub: 'klausul 4' })}
      ${card('Kerangka kerja', FW.map(([n, a, p]) => `<div class="irow"><span><span class="ic-n">${n}</span>${a}</span>${prog(p, p < 50 ? 'late' : '')}</div>`).join(''), { flush: true, sub: 'klausul 5' })}
      ${card('Proses', PC.map(([n, a, g]) => `<div class="irow"><span><span class="ic-n">${n}</span>${a}</span><a class="btn sm" href="#${g}" style="justify-self:end">Buka →</a></div>`).join(''), { flush: true, sub: 'klausul 6' })}
    </div>`;
  };

  V.workflow = function () {
    const ap = S.approvals, sel = ap.find((a) => a.id === S.wfSel) || ap[0];
    const r = riskById(sel.ref);
    const done = sel.st !== 'Proses';
    return `${ph('ISO 31000 · 6.2 Komunikasi & Konsultasi', 'Persetujuan', 'Alur berjenjang Risk Officer → Risk Owner → Risk Manager → Direktur. Setiap keputusan tercatat di audit trail.', '')}
    <div class="grid g-12"><div class="s-7">${card('Kotak masuk persetujuan', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Jenis</th><th>Objek</th><th>Diajukan</th><th>Tahap</th><th>Status</th></tr></thead><tbody>${ap.map((a) => `<tr class="click" data-act="wf-sel" data-v="${a.id}" style="${a.id === sel.id ? 'background:var(--accent-soft)' : ''}"><td class="mono">${a.id}</td><td>${a.type}</td><td><span class="mono">${a.ref}</span><div class="t-sub">${esc((riskById(a.ref) || {}).name || '')}</div></td><td style="white-space:nowrap">${esc(a.by)}<div class="t-sub">${fmtDate(a.date)}</div></td><td style="white-space:nowrap">${a.st === 'Proses' ? D.WF_STAGES[a.stage] : '—'}</td><td><span class="pill ${a.st === 'Disetujui' ? 'ok' : a.st === 'Ditolak' ? 'bad' : a.st === 'Revisi' ? 'warn' : 'run'}">${a.st === 'Proses' ? 'Menunggu' : a.st}</span></td></tr>`).join('')}</tbody></table></div>`, { flush: true })}</div>
    <div class="s-5">${card(`${sel.id} · ${sel.type}`, `<div class="stack">${r ? `<div><a href="#risk-${r.id}" class="mono">${r.id}</a> <b>${esc(r.name)}</b><div class="row" style="gap:6px;margin-top:6px">${scoreB(sc(r.res))}${lvChip(sc(r.res))}</div></div>` : ''}<p class="fg2" style="font-size:13px">${esc(sel.note)}</p>
      <div class="wf">${D.WF_STAGES.map((s, i) => { const cls = sel.st === 'Disetujui' || i < sel.stage ? 'done' : i === sel.stage && sel.st === 'Proses' ? 'cur' : ''; return `<div class="wf-s ${cls}"><span class="wf-dot">${cls === 'done' ? '✓' : i + 1}</span><div><div class="wt">${s}</div><div class="wm">${cls === 'done' ? (i === 0 ? `Submit · ${esc(sel.by)}` : 'Disetujui') : cls === 'cur' ? 'Menunggu keputusan' : sel.st === 'Ditolak' && i === sel.stage ? 'Ditolak' : '—'}</div></div></div>`; }).join('')}</div>
      ${done ? `<div class="ro-banner">${ic('info')}Keputusan: <b>${sel.st}</b></div>` : `<div class="field"><label for="wf-note">Catatan reviu</label><textarea id="wf-note" placeholder="Tambahkan catatan untuk pengaju…"></textarea></div>
      <div class="row"><button class="btn pri" data-act="wf-do" data-v="approve" ${canApprove() ? '' : 'disabled'}>Setujui</button><button class="btn" data-act="wf-do" data-v="revise" ${canApprove() ? '' : 'disabled'}>Minta revisi</button><button class="btn danger" data-act="wf-do" data-v="reject" ${canApprove() ? '' : 'disabled'}>Tolak</button></div>${canApprove() ? '' : `<p class="hint">Peran ${S.role} tidak memiliki hak persetujuan.</p>`}`}</div>`)}</div></div>`;
  };

  V.reports = function () {
    const CAT = [['Risk Register', 'Daftar lengkap risiko beserta analisis dan perlakuan'], ['Risk Profile', 'Profil risiko organisasi per level dan kategori'], ['Risk Heatmap', 'Matriks inheren dan residual'], ['Top Risks', 'Risiko prioritas untuk pimpinan'], ['Risk Treatment', 'Rencana dan realisasi perlakuan'], ['Residual Risk', 'Perbandingan inheren → residual → target'], ['KRI Report', 'Status indikator dan pelanggaran ambang'], ['Risk Incident Report', 'Insiden, kerugian, dan tindakan korektif'], ['Risk Trend', 'Pergerakan risiko antarperiode'], ['Control Effectiveness', 'Hasil penilaian desain dan operasi kontrol'], ['Mitigation Progress', 'Progres action plan per unit'], ['Overdue Action', 'Action plan yang melewati tenggat'], ['Risk Review Report', 'Berita acara reviu berkala']];
    const fmts = ['PDF', 'Excel', 'Word'];
    const rep = S.report;
    let gen = '';
    const sx = stats(), P = D.PROFILE, prevAvg = D.AVG_TREND.values[D.AVG_TREND.values.length - 4];
    if (S.reportBusy) gen = `<div class="doc"><div class="typing" aria-label="Menyusun laporan"><i></i><i></i><i></i></div><p class="hint" style="margin-top:8px">AI menyusun laporan dari risk register, KRI, insiden, dan action plan…</p></div>`;
    else if (rep) gen = `<article class="doc"><div class="eyebrow">Laporan Eksekutif Manajemen Risiko</div><h2 style="margin-top:6px">Executive Risk Report ${esc(rep)}</h2><div class="meta">${esc(D.ORG.name)} · disusun otomatis 5 Okt 2026 · draf untuk ditinjau Risk Manager</div>
      <h4>1. Ringkasan eksekutif</h4><p>Organisasi mengelola ${sx.total} risiko aktif dan historis. Profil risiko membaik dibanding triwulan sebelumnya: jumlah risiko Sangat Tinggi turun dari ${D.QUARTERS[2].vh} menjadi ${sx.vh} dan skor residual rata-rata turun dari ${fmtNum(prevAvg, 1)} menjadi ${fmtNum(sx.avg, 1)}. Perbaikan terbesar terjadi pada risiko keuangan dan kepatuhan. Sebaliknya, eksposur keamanan siber, SDM, dan kapasitas layanan perizinan meningkat.</p>
      <h4>2. Top risiko</h4><ul>${S.risks.slice().sort((a, b) => sc(b.res) - sc(a.res)).slice(0, 5).map((r) => `<li><b>${r.id} ${esc(r.name)}</b> · residual ${sc(r.res)} (${level(sc(r.res)).n}), ${esc(unitName(r.unit))}</li>`).join('')}</ul>
      <h4>3. Tren risiko</h4><p>${P.up} risiko meningkat, ${P.flat} stabil, ${P.down} menurun. Risiko yang naik ke level Sangat Tinggi: ${sx.up.filter((r) => sc(r.res) >= 16).map((r) => `${r.id} ${esc(r.name)}`).join('; ')}.</p>
      <h4>4. Progres mitigasi</h4><p>Realisasi mitigasi ${sx.mitig}% dari ${sx.acts} action plan; ${sx.done} selesai dan ${sx.late.length} terlambat. Keterlambatan antara lain pada ${sx.late.slice(0, 4).map((a) => `${esc(a.t.toLowerCase())} (${a.id})`).join(', ')}.</p>
      <h4>5. KRI & insiden</h4><p>${sx.krit.length} KRI berstatus Kritis: ${sx.krit.map((k) => `${esc(k.n.toLowerCase())} ${kriVal(k, k.v[k.v.length - 1])} ${esc(k.u)}`).join('; ')}. ${sx.inc.length} insiden tercatat pada 2026 dengan total kerugian ${rp(sx.loss)}. Kerugian terbesar berasal dari “${esc(sx.big.t)}” senilai ${rp(sx.big.loss)}.</p>
      <h4>6. Rekomendasi</h4><ul><li>Percepat pengadaan redundansi pusat data (A-002) dan jadwalkan DR Test sebelum akhir November.</li><li>Tetapkan formasi khusus jabatan fungsional keamanan siber melalui koordinasi dengan KemenPAN-RB.</li><li>Tambah kapasitas verifikator dan terapkan pra-verifikasi otomatis untuk menurunkan backlog permohonan di bawah 3.000 berkas.</li><li>Setujui exit plan penyedia cloud sebagai prasyarat perpanjangan kontrak 2027.</li><li>Tetapkan Pejabat Pelindungan Data Pribadi sebelum 15 Oktober 2026.</li></ul>
      <div class="row" style="margin-top:20px">${fmts.map((f) => `<button class="btn sm" data-act="toast" data-v="Laporan eksekutif diekspor ke ${f} (simulasi)">${ic('down')}${f}</button>`).join('')}</div></article>`;
    return `${ph('ISO 31000 · 6.7 Pencatatan & Pelaporan', 'Laporan', 'Laporan baku dapat diekspor ke PDF, Excel, atau Word. Laporan eksekutif dapat disusun AI hanya dengan memilih periode.', '')}
    <section class="callout" style="flex-wrap:wrap"><span class="ai-ic">${ic('spark')}</span><div style="flex:1;min-width:220px"><p><b>AI Generate Risk Report</b></p><small>Pilih periode, lalu AI menyusun ringkasan eksekutif, top risk, tren, progres mitigasi, KRI, insiden, aksi terlambat, dan rekomendasi.</small></div>
      <div class="row"><select class="sel" id="rep-per" aria-label="Periode laporan">${['TW III 2026', 'TW II 2026', 'Semester I 2026', 'Tahun 2025'].map((p) => opt(p, p, S.reportPeriod)).join('')}</select><button class="btn c-pink" data-act="gen-report">${ic('spark')}Buat laporan</button></div></section>
    ${gen}
    <div class="rep-grid">${CAT.map(([a, b]) => `<div class="rep"><h4>${a}</h4><p>${b}</p><div class="fmt">${fmts.map((f) => `<button class="btn sm" data-act="toast" data-v="${a} diekspor ke ${f} (simulasi)">${f}</button>`).join('')}</div></div>`).join('')}</div>
    ${card('Riwayat laporan', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Laporan</th><th>Format</th><th>Dibuat oleh</th><th>Waktu</th><th>Keterangan</th><th></th></tr></thead><tbody>${D.REPORT_LOG.map((l) => `<tr><td class="t-main">${ic('file')} ${esc(l.n)}</td><td><span class="pill">${l.f}</span></td><td>${esc(l.by)}</td><td class="mono" style="white-space:nowrap">${fmtDate(l.t.slice(0, 10))}, ${l.t.slice(11)}</td><td class="fg2">${esc(l.note)}</td><td><button class="btn sm ghost" data-act="toast" data-v="Mengunduh ${esc(l.n)} (simulasi)" aria-label="Unduh">${ic('down')}</button></td></tr>`).join('')}</tbody></table></div>`, { flush: true, sub: `${D.REPORT_LOG.length} laporan terakhir` })}`;
  };

  V.documents = function () {
    return `${ph('ISO 31000 · 6.7 Pencatatan', 'Dokumen & Bukti', 'SOP, kebijakan, berita acara, hasil audit, dan bukti pelaksanaan dengan versi, metadata, persetujuan, dan tanggal kedaluwarsa.', '')}
    <label class="drop" for="doc-up">${ic('upload')} <b>Tarik berkas ke sini</b> atau klik untuk memilih · PDF, DOCX, XLSX, JPG hingga 25 MB<input type="file" id="doc-up" class="sr" multiple data-upload="doc"></label>
    ${card('Repositori dokumen', docTable(S.docs), { flush: true, sub: `${S.docs.length} dokumen` })}`;
  };

  V.org = function () {
    const t = S.orgTab;
    let body = '';
    if (t === 'tree') {
      const node = (n) => `<li><span class="tn"><span>${esc(n.n)}</span><span class="tt">${esc(n.t)}</span>${n.c ? `<span class="tc">${n.c} risiko</span>` : ''}</span>${n.k ? `<ul>${n.k.map(node).join('')}</ul>` : ''}</li>`;
      body = `<div class="grid g-12"><div class="s-8">${card('Struktur organisasi & risiko', `<div class="tree"><ul>${node(D.ORG_TREE)}</ul></div>`, { sub: 'Organisasi → Unit → Program → Kegiatan → Proses bisnis' })}</div><div class="s-4">${card('Multi-organisasi', `<p class="fg2" style="font-size:13px">Satu instalasi dapat melayani holding, kementerian, pemerintah daerah, rumah sakit, atau perguruan tinggi dengan data yang terpisah.</p><div class="stack" style="margin-top:12px">${[['Badan Layanan Digital Nusantara', 'Aktif · 127 risiko'], ['RSUD Kota Contoh', 'Tenant demo · 54 risiko'], ['Universitas Contoh', 'Tenant demo · 88 risiko']].map(([a, b], i) => `<div class="row between"><span><b style="font-size:13px">${a}</b><br><span class="hint">${b}</span></span>${i === 0 ? '<span class="pill ok">Aktif</span>' : ''}</div>`).join('')}</div>`)}</div></div>`;
    } else if (t === 'users') {
      body = card('Pengguna', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Nama</th><th>Unit</th><th>Peran</th><th>Login terakhir</th><th></th></tr></thead><tbody>${D.USERS.map((u) => `<tr><td><div class="row" style="gap:10px;flex-wrap:nowrap"><span class="avatar">${initials(u.n) || u.n[0]}</span><div><div class="t-main">${esc(u.n)}</div><div class="t-sub">${esc(u.e)}</div></div></div></td><td>${esc(u.unit)}</td><td><span class="pill run">${u.role}</span></td><td class="fg2">${u.last}</td><td><button class="btn sm ghost" ${W()} data-act="toast" data-v="Ubah pengguna ${esc(u.n)} (simulasi)">Ubah</button></td></tr>`).join('')}</tbody></table></div>`, { flush: true, sub: 'data organisasi (contoh)', extra: allowed('users') ? `<a class="btn sm c-teal" href="#users">${ic('key')}Kelola akun login →</a>` : `<button class="btn sm pri" ${W()} data-act="toast" data-v="Pengelolaan akun hanya untuk Super Admin">${ic('plus')}Pengguna</button>` });
    } else if (t === 'roles') {
      const R = Object.keys(D.ROLES);
      body = card('Role based access control', `<div class="tbl-wrap"><table class="tbl matrix-tbl"><thead><tr><th>Hak akses</th>${R.map((r) => `<th style="white-space:normal;min-width:84px">${r}</th>`).join('')}</tr></thead><tbody>${D.PRIVS.map((p, i) => `<tr><td class="t-main">${p}</td>${R.map((r) => (D.ROLES[r].p[i] ? '<td class="yes" aria-label="ya">✓</td>' : '<td class="no" aria-label="tidak">–</td>')).join('')}</tr>`).join('')}<tr><td class="t-sub">Deskripsi</td>${R.map((r) => `<td class="t-sub" style="white-space:normal">${D.ROLES[r].d}</td>`).join('')}</tr></tbody></table></div><p class="hint" style="padding:10px 16px 0">Coba ganti peran di bilah atas untuk melihat menu dan tombol menyesuaikan hak akses.</p>`, { flush: true });
    } else {
      body = card('Konfigurasi alur persetujuan', `<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">${D.WF_STAGES.map((s, i) => `<div style="border:1px solid var(--line);border-radius:8px;padding:12px"><div class="mono muted" style="font-size:11px">Tahap ${i + 1}</div><b>${s}</b><div class="hint" style="margin-top:4px">${['Submit', 'Review · Revisi', 'Review · Approve · Reject', 'Approve · Reject'][i]}</div><div class="hint">SLA ${['—', '3 hari kerja', '3 hari kerja', '5 hari kerja'][i]}</div></div>`).join('')}</div><p class="hint" style="margin-top:12px">Risiko dengan skor residual ≥ 16 otomatis membutuhkan persetujuan tahap 4.</p>`);
    }
    return `${ph('ISO 31000 · 5.4.3 Peran & Akuntabilitas', 'Organisasi & Pengguna', 'Struktur organisasi, risk ownership, pengguna, peran, dan alur persetujuan.', '')}${tabs('orgtab', [['tree', 'Struktur'], ['users', 'Pengguna', D.USERS.length], ['roles', 'Peran & hak akses'], ['wf', 'Alur persetujuan']], t)}${body}`;
  };

  V.audit = function () {
    const q = S.auditQ.toLowerCase();
    const rows = S.audit.filter((a) => !q || `${a.u} ${a.a} ${a.ref} ${a.f} ${a.p} ${a.n}`.toLowerCase().includes(q)), pg = paged('audit', rows);
    return `${ph('Governance · Akuntabilitas', 'Audit Trail', 'Seluruh aktivitas pengguna tercatat beserta nilai sebelum dan sesudah perubahan, untuk kebutuhan audit dan tata kelola.', `<button class="btn" data-act="toast" data-v="Audit trail diekspor ke Excel (simulasi)">${ic('down')}Ekspor</button>`)}
    <section class="card"><div class="card-b"><div class="fbar"><input class="inp" type="search" id="audit-q" data-auditq placeholder="Cari pengguna, aktivitas, objek…" value="${esc(S.auditQ)}" aria-label="Cari audit trail"><span class="hint">${rows.length} entri</span></div></div>${auditTable(pg.rows)}${pager('audit', pg)}</section>`;
  };

  /* --- Pengguna & Akun (Super Admin) --- */
  const DEMO_USERS = () => D.USERS.map((u, i) => ({ id: 'demo' + i, email: u.e, name: u.n, role: u.role, unit: u.unit, active: i !== 7, lastLogin: null, lastText: u.last, mustChange: i === 8, created: '2026-01-15T00:00:00+07:00' }));
  async function loadUsers(force) {
    if (!AUTH) { S.um.list = S.um.list || DEMO_USERS(); S.um.roles = Object.keys(D.ROLES); S.um.me = 'demo1'; return; }
    if (S.um.list && !force) return;
    const r = await fetch('api/users.php', { credentials: 'same-origin', cache: 'no-store' });
    const j = await r.json().catch(() => ({}));
    if (r.status === 401) { location.replace('./'); return; }
    if (!r.ok) throw new Error(j.error || 'Gagal memuat akun');
    Object.assign(S.um, { list: j.users, roles: j.roles, me: j.me });
  }
  async function loadLog() {
    if (!AUTH) { S.um.log = S.audit.slice(0, 40).map((a) => ({ t: a.t.replace(' ', 'T') + ':00', ip: a.ip, event: 'audit', email: a.u, detail: `${a.a} ${a.ref}` })); return; }
    const r = await fetch('api/users.php?log=1', { credentials: 'same-origin', cache: 'no-store' });
    const j = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(j.error || 'Gagal memuat log');
    S.um.log = j.log;
  }
  const EVENT_LABEL = { login_ok: ['Masuk', 'ok'], login_fail: ['Masuk gagal', 'bad'], login_locked: ['Terkunci', 'bad'], logout: ['Keluar', ''], password_changed: ['Ganti sandi', 'run'], password_fail: ['Ganti sandi gagal', 'warn'], user_create: ['Akun dibuat', 'ok'], user_update: ['Akun diubah', 'run'], user_reset: ['Sandi direset', 'warn'], user_activate: ['Diaktifkan', 'ok'], user_deactivate: ['Dinonaktifkan', 'bad'], user_delete: ['Akun dihapus', 'bad'], users_forbidden: ['Akses ditolak', 'bad'], audit: ['Aktivitas', ''] };
  const fmtIso = (iso) => { if (!iso) return '—'; const d = new Date(iso); if (isNaN(d)) return esc(iso); return `${d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })} ${d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`; };
  const rolePill = (r) => `<span class="pill ${r === 'Super Admin' ? 'bad' : r === 'Auditor' || r === 'Management' ? '' : 'run'}">${esc(r)}</span>`;

  V.users = function () {
    const um = S.um;
    if (um.list === null && !um.busy) {
      um.busy = true; um.err = '';
      loadUsers().then(() => { um.busy = false; render(true); }).catch((e) => { um.busy = false; um.err = e.message; render(true); });
    }
    if (um.tab === 'log' && um.log === null && !um.busy) {
      um.busy = true; loadLog().then(() => { um.busy = false; render(true); }).catch((e) => { um.busy = false; um.err = e.message; render(true); });
    }
    const list = um.list || [], q = um.q.toLowerCase();
    const rows = list.filter((u) => !q || `${u.name} ${u.email} ${u.role} ${u.unit}`.toLowerCase().includes(q));
    const n = (f) => list.filter(f).length;
    let body = '';
    if (um.err) body = `<div class="ro-banner" style="background:color-mix(in oklab,var(--lv-vh) 12%,var(--surface));color:var(--bad-ink)">${ic('alert')}<span>${esc(um.err)}</span><button class="btn sm ghost c-indigo" data-act="um-reload" style="margin-left:auto">Coba lagi</button></div>`;
    else if (um.busy && (um.tab === 'list' ? !um.list : !um.log)) body = `<div class="empty"><span class="typing"><i></i><i></i><i></i></span><br>Memuat…</div>`;
    else if (um.tab === 'list') {
      body = card('', `<div class="card-b" style="padding-bottom:0"><div class="fbar"><input class="inp" id="um-q" type="search" placeholder="Cari nama, email, peran, unit…" value="${esc(um.q)}" data-umq aria-label="Cari akun"><span class="hint">${rows.length} dari ${list.length} akun</span></div></div>
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Pengguna</th><th>Peran</th><th>Unit</th><th>Terakhir masuk</th><th>Status</th><th></th></tr></thead><tbody>${rows.length ? rows.map((u) => { const me = u.id === um.me; return `<tr style="${u.active ? '' : 'opacity:.6'}"><td><div class="row" style="gap:10px;flex-wrap:nowrap"><span class="avatar">${initials(u.name) || u.name[0]}</span><div><div class="t-main">${esc(u.name)}${me ? ' <span class="pill run" style="font-size:10.5px">Anda</span>' : ''}</div><div class="t-sub mono">${esc(u.email)}</div></div></div></td><td>${rolePill(u.role)}</td><td class="fg2">${esc(u.unit || '—')}</td><td class="fg2" style="white-space:nowrap">${u.lastText ? esc(u.lastText) : u.lastLogin ? fmtIso(u.lastLogin) : '<span class="muted">Belum pernah</span>'}</td><td><span class="row" style="gap:4px">${u.active ? '<span class="pill ok">Aktif</span>' : '<span class="pill bad">Nonaktif</span>'}${u.mustChange ? '<span class="pill warn" title="Wajib ganti kata sandi saat masuk">Sandi sementara</span>' : ''}</span></td><td style="white-space:nowrap"><span class="row" style="gap:4px;flex-wrap:nowrap;justify-content:flex-end"><button class="btn sm ghost c-amber" data-act="um-edit" data-v="${u.id}">Ubah</button><button class="btn sm ghost c-cyan" data-act="um-reset" data-v="${u.id}">Reset sandi</button>${me ? '' : `<button class="btn sm ghost ${u.active ? 'c-red' : 'c-green'}" data-act="um-toggle" data-v="${u.id}">${u.active ? 'Nonaktifkan' : 'Aktifkan'}</button>`}${!me && !u.lastLogin && !u.lastText ? `<button class="btn sm ghost c-red" data-act="um-delete" data-v="${u.id}" aria-label="Hapus ${esc(u.name)}">${ic('x')}</button>` : ''}</span></td></tr>`; }).join('') : '<tr><td colspan="6"><div class="empty">Tidak ada akun yang cocok.</div></td></tr>'}</tbody></table></div>`, { flush: true });
    } else {
      const log = um.log || [];
      body = card('Log keamanan', `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Peristiwa</th><th>Akun</th><th>Keterangan</th><th>IP</th></tr></thead><tbody>${log.length ? log.map((l) => { const lab = EVENT_LABEL[l.event] || [l.event, '']; return `<tr><td class="mono" style="white-space:nowrap">${fmtIso(l.t)}</td><td><span class="pill ${lab[1]}">${esc(lab[0])}</span></td><td class="mono">${esc(l.email)}</td><td class="fg2" style="min-width:220px">${esc(l.detail || '—')}</td><td class="mono muted">${esc(l.ip)}</td></tr>`; }).join('') : '<tr><td colspan="5"><div class="empty">Belum ada entri.</div></td></tr>'}</tbody></table></div>`, { flush: true, sub: `${log.length} entri terakhir`, extra: `<button class="btn sm ghost c-indigo" data-act="um-reload">${ic('refresh')}Muat ulang</button>` });
    }
    return `${ph('Administrasi · Super Admin', 'Pengguna & Akun', 'Kelola akun yang dapat masuk ke ManRisk. Peran menentukan menu dan hak akses; kata sandi sementara wajib diganti saat pertama masuk.', `<button class="btn pri c-teal" data-act="um-new">${ic('plus')}Tambah pengguna</button>`)}
    ${AUTH ? '' : `<div class="ro-banner">${ic('info')}<span><b>Mode demo:</b> perubahan hanya tersimpan di browser ini dan tidak dikirim ke server.</span></div>`}
    <div class="kpis">${kpi('Total akun', list.length, '')}${kpi('Aktif', n((u) => u.active), 'dapat masuk', 'lvl', '--c:var(--lv-l)')}${kpi('Nonaktif', n((u) => !u.active), 'ditolak saat masuk', 'lvl', '--c:var(--lv-vh)')}${kpi('Belum pernah masuk', n((u) => !u.lastLogin && !u.lastText), 'akun baru / sandi sementara')}${kpi('Super Admin aktif', n((u) => u.active && u.role === 'Super Admin'), 'minimal 1')}</div>
    ${tabs('umtab', [['list', 'Akun', list.length], ['log', 'Log keamanan']], um.tab)}
    ${body}`;
  };

  /* --- AI Assistant --- */
  const AI_PROMPTS = ['Identifikasi risiko proses pembayaran vendor', 'Buat risk statement untuk gangguan jaringan kantor wilayah', 'Rekomendasi mitigasi untuk risiko ransomware', 'Analisis perubahan risiko triwulan ini', 'Ringkas profil risiko untuk pimpinan'];
  function aiReply(q) {
    const s = q.toLowerCase();
    if (/identifik|kandidat|proses/.test(s)) {
      const c = aiCands(s);
      return `<span class="ai-tag">Risk identification</span>Kandidat risiko untuk proses tersebut:<ol>${c.map((x) => `<li><b>${esc(x.name)}</b> <span class="muted">(${x.cat})</span></li>`).join('')}</ol><p style="margin-top:8px">Pilih satu kandidat di <a href="#identify">wizard identifikasi</a> untuk melengkapi analisisnya.</p>`;
    }
    if (/statement|pernyataan/.test(s)) return `<span class="ai-tag">Risk statement generator</span><p>Karena <b>jalur jaringan kantor wilayah hanya mengandalkan satu penyedia tanpa jalur cadangan</b>, dapat terjadi <b>gangguan koneksi lebih dari 4 jam</b>, sehingga menyebabkan <b>pelayanan tatap muka beralih ke proses manual dan antrean meningkat</b>.</p><p style="margin-top:8px" class="muted">Kategori disarankan: Pihak Ketiga · Kemungkinan 3 · Dampak 3 · Skor 9 (Sedang).</p>`;
    if (/mitigasi|rekomendasi|ransomware/.test(s)) return `<span class="ai-tag">Treatment recommendation · R-002</span>Opsi perlakuan: <b>Kurangi</b>. Rekomendasi berdasarkan kontrol yang ada:<ol><li>Backup immutable/offline untuk basis data inti (mengurangi dampak 4 → 3).</li><li>Patch management terpusat dengan SLA patch kritikal 7 hari (mengurangi kemungkinan).</li><li>Segmentasi jaringan antara server aplikasi dan basis data.</li><li>MFA untuk seluruh akses administratif.</li><li>Simulasi phishing triwulanan.</li></ol><p style="margin-top:8px">Proyeksi residual: <b>16 → 9</b> (Sedang).</p>`;
    if (/analisis|perubahan|tren/.test(s)) return `<span class="ai-tag">Risk analysis</span><p>Dibanding TW II, 12 risiko meningkat. Kenaikan terbesar:</p><ul><li><b>R-002 Ransomware</b> 12 → 16. Pemicu: insiden keamanan naik 2 bulan berturut-turut (K-02) dan 12% server dengan patch tertunda.</li><li><b>R-009 Kekurangan SDM siber</b> 12 → 16. Pemicu: 2 personel mengundurkan diri dan rekrutmen A-014 terlambat.</li></ul><p style="margin-top:8px">Kedua risiko saling terkait: kekurangan personel memperlambat patching.</p>`;
    if (/ringkas|pimpinan|summary|profil/.test(s)) { const st = stats(); return `<span class="ai-tag">Risk summary</span><p>Terdapat <b>${st.vh} risiko Sangat Tinggi</b> yang membutuhkan perhatian manajemen dari total ${st.total} risiko; ${st.topUnitN} di antaranya di ${esc(st.topUnit)}. Profil membaik: skor residual rata-rata turun ke ${fmtNum(st.avg, 1)}. Realisasi mitigasi ${st.mitig}%, namun ${st.late.length} action plan terlambat. Tiga keputusan diperlukan bulan ini: redundansi pusat data, penambahan verifikator layanan perizinan, dan formasi SDM siber.</p>`; }
    return `<span class="ai-tag">AI Risk Assistant</span><p>Saya dapat membantu identifikasi risiko, menyusun risk statement, merekomendasikan mitigasi, menganalisis perubahan risiko, dan meringkas profil risiko. Coba salah satu contoh pertanyaan di samping.</p>`;
  }
  V.ai = function () {
    if (!S.ai) S.ai = [{ me: false, h: aiReply('') }];
    return `${ph('Fitur pembeda · Simulasi', 'AI Risk Assistant', 'Asisten yang membaca risk register, KRI, insiden, dan action plan untuk membantu Risk Officer dan pimpinan. Jawaban pada purwarupa ini adalah simulasi.', '')}
    <div class="ai-wrap"><section class="card chat"><div class="msgs" id="msgs">${S.ai.map((m) => `<div class="msg ${m.me ? 'me' : 'ai'}">${m.h}</div>`).join('')}${S.aiBusy ? '<div class="msg ai typing" aria-label="Mengetik"><i></i><i></i><i></i></div>' : ''}</div>
      <form class="chat-in" data-form="ai"><input class="inp" id="ai-in" placeholder="Tanyakan sesuatu tentang risiko organisasi…" autocomplete="off" aria-label="Pertanyaan untuk AI"><button class="btn pri" type="submit">${ic('send')}<span class="sr">Kirim</span></button></form></section>
    <div class="stack">${card('Contoh pertanyaan', `<div class="chips" style="flex-direction:column;align-items:stretch">${AI_PROMPTS.map((p) => `<button type="button" class="chip" data-act="ai-ask" data-v="${esc(p)}">${esc(p)}</button>`).join('')}</div>`)}
    ${card('Kemampuan', `<ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.8" class="fg2"><li>Identify risk</li><li>Risk statement generator</li><li>Recommend treatment</li><li>Analyze risk change</li><li>Risk summary</li><li><a href="#reports">Generate report</a></li></ul>`)}</div></div>`;
  };

  /* Tombol warna-warni: warna mengikuti makna tombol, sisanya bergiliran agar tiap kelompok tampil berwarna */
  const PALETTE = ['c-blue', 'c-violet', 'c-teal', 'c-orange', 'c-pink', 'c-green', 'c-indigo', 'c-cyan', 'c-amber'];
  const COLOR_RULES = [
    [/excel|setujui|simpan|lanjut|selesai/i, 'c-green'],
    [/pdf|tolak|hapus/i, 'c-red'],
    [/\bword\b/i, 'c-indigo'],
    [/\bai\b|laporan eksekutif|buat laporan|tanya/i, 'c-violet'],
    [/tambah|baru|laporkan|rencana perbaikan|undang|^\s*\+?\s*(kri|kontrol|kategori|pengguna|action plan)\s*$/i, 'c-teal'],
    [/ubah|revisi|susun/i, 'c-amber'],
    [/ekspor|unduh|alur persetujuan/i, 'c-cyan'],
    [/kosongkan|reset|tutup|sebelumnya/i, 'c-indigo']
  ];
  function colorize(root) {
    $$('.btn, .icon-btn, .tabs button, .seg button, .chip', root).forEach((el, i) => {
      if (/\bc-[a-z]+\b/.test(el.className)) return;
      const t = (el.textContent || el.getAttribute('aria-label') || '').trim();
      let c = null;
      if (el.classList.contains('danger')) c = 'c-red';
      if (!c) for (const [re, k] of COLOR_RULES) if (re.test(t) || re.test(el.getAttribute('aria-label') || '')) { c = k; break; }
      if (!c && el.classList.contains('pri')) c = 'c-blue';
      if (!c) c = PALETTE[i % PALETTE.length];
      el.classList.add(c);
    });
  }

  /* ======================= Autentikasi ======================= */
  async function api(path, body) {
    const r = await fetch(path, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': AUTH ? AUTH.csrf : '' }, body: JSON.stringify(body || {}) });
    const j = await r.json().catch(() => ({}));
    if (r.status === 401) { location.replace('./'); throw new Error(j.error || 'Sesi berakhir'); }
    if (j.csrf && AUTH) AUTH.csrf = j.csrf;
    if (!r.ok) { const e = new Error(j.error || 'Permintaan gagal'); e.field = j.field; throw e; }
    return j;
  }
  async function logout() {
    try { await api('api/logout.php'); } catch (e) { /* tetap keluar */ }
    location.replace('./');
  }
  function startIdleWatch() {
    const limit = (AUTH.idle || 1800) * 1000;
    ['click', 'keydown', 'mousemove', 'touchstart', 'scroll'].forEach((ev) => document.addEventListener(ev, () => { lastActivity = Date.now(); }, { passive: true }));
    setInterval(async () => {
      if (Date.now() - lastActivity > limit) { location.replace('./'); return; }
      try { const r = await fetch('api/session.php', { credentials: 'same-origin', cache: 'no-store' }); const j = await r.json(); if (!j.authenticated) location.replace('./'); } catch (e) { /* abaikan gangguan jaringan sesaat */ }
    }, 60000);
  }
  function fmtLogin(iso) { if (!iso) return 'Masuk pertama kali'; const d = new Date(iso); return `Terakhir masuk ${d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })} ${d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`; }

  /* ======================= Shell ======================= */
  const TITLES = {};
  NAV.forEach((g) => g.items.forEach(([id, t]) => (TITLES[id] = t)));

  function renderSide(cur) {
    const pend = S.approvals.filter((a) => a.st === 'Proses').length, kritN = D.KRIS.filter((k) => kriStatus(k).n === 'Kritis').length;
    $('#side').innerHTML = `<div class="brand"><span class="brand-mark" aria-hidden="true">${['l', 'm', 'h', 'm', 'h', 'vh', 'h', 'vh', 'vh'].map((k) => `<i style="background:var(--lv-${k})"></i>`).join('')}</span><span><b>ManRisk</b><small>ERM · ISO 31000:2018</small></span></div>
    <nav class="nav">${NAV.map((g) => { const its = g.items.filter((i) => allowed(i[0])); if (!its.length) return ''; return `<div class="nav-g"><span>${g.g}</span>${its.map(([id, t, i]) => `<a href="#${id}" class="${cur === id || (cur === 'risk' && id === 'register') ? 'on' : ''}" ${cur === id ? 'aria-current="page"' : ''}>${ic(i)}${t}${id === 'workflow' && pend ? `<span class="cnt">${pend}</span>` : ''}${id === 'kri' && kritN ? `<span class="cnt">${kritN}</span>` : ''}</a>`).join('')}</div>`; }).join('')}</nav>
    <div class="side-foot">Purwarupa UI/UX · data contoh fiktif<br>${esc(D.ORG.name)}</div>`;
  }
  function renderTop() {
    $('#top').innerHTML = `<button class="icon-btn menu-btn c-indigo" data-act="nav" aria-label="Buka menu">${ic('menu')}</button>
    <form class="search" data-form="search" role="search"><span class="muted">${ic('search')}</span><input id="gq" type="search" placeholder="Cari risiko, kontrol, KRI…" aria-label="Pencarian global"></form>
    <div class="top-ctx">
      <select class="sel" id="org-sel" aria-label="Organisasi" data-orgsel>${opt('bldn', 'BLDN', 'bldn')}${opt('rs', 'RSUD Kota Contoh', 'bldn')}${opt('uv', 'Universitas Contoh', 'bldn')}</select>
      <select class="sel" id="per-sel" aria-label="Periode" data-act-change="period">${['TW III 2026', 'TW II 2026', 'TW I 2026'].map((p) => opt(p, p, 'TW III 2026')).join('')}</select>
      ${AUTH ? '' : `<select class="sel" id="role-sel" aria-label="Masuk sebagai peran" data-rolesel title="Simulasi peran (RBAC)">${Object.keys(D.ROLES).map((r) => opt(r, `Peran: ${r}`, S.role)).join('')}</select>`}
      <button class="icon-btn c-indigo" data-act="theme" aria-label="Ganti tema terang/gelap">${ic('moon')}</button>
      <button class="icon-btn c-orange" data-act="notif" aria-label="Notifikasi early warning">${ic('bell')}<span class="dot">${WARNINGS.length}</span></button>
      <a class="icon-btn ai-btn c-violet" href="#ai" aria-label="AI Risk Assistant">${ic('spark')}</a>
      <button type="button" class="user ${AUTH ? 'u-btn' : ''}" data-act="umenu" aria-haspopup="menu" aria-expanded="${S.overlay === 'umenu'}" title="${AUTH ? 'Akun' : 'Mode demo'}"><span class="avatar">${initials(ME()) || ME()[0]}</span><span class="u-txt"><b>${esc(ME())}</b><br><span class="muted">${esc(S.role)}</span></span></button>
    </div>`;
  }
  function renderOverlay() {
    const o = $('#overlay');
    if (S.overlay === 'umenu') {
      const u = AUTH ? AUTH.user : { name: ME(), role: S.role, email: 'demo@manrisk.id', unit: 'Mode demo' };
      o.innerHTML = `<div class="scrim clear" data-act="close-ov"></div><div class="umenu" role="menu"><div class="um-h"><span class="avatar">${initials(u.name) || u.name[0]}</span><div><b>${esc(u.name)}</b><div class="muted" style="font-size:12px">${esc(u.role)} · ${esc(u.unit || '')}</div><div class="mono muted" style="font-size:11.5px">${esc(u.email)}</div></div></div>
        ${AUTH ? `<div class="hint" style="padding:0 14px 8px">${fmtLogin(AUTH.user.lastLogin)}</div><button type="button" class="um-i" role="menuitem" data-act="pwd-open">${ic('lock')}Ganti kata sandi</button><button type="button" class="um-i" role="menuitem" data-act="theme">${ic('moon')}Ganti tema</button><button type="button" class="um-i danger" role="menuitem" data-act="logout">${ic('x')}Keluar</button>` : `<div class="hint" style="padding:0 14px 12px">Pratinjau tanpa server: peran dapat diganti lewat pilihan di bilah atas. Di server, akun login menentukan peran.</div>`}</div>`;
    } else if (S.overlay === 'uform') {
      const e = S.um.edit || {}, isNew = !e.id, units = Array.from(new Set(D.UNITS.concat(['Unit Manajemen Risiko', 'Pimpinan', 'Sekretariat Utama']).concat(e.unit ? [e.unit] : [])));
      o.innerHTML = `<div class="scrim" data-act="close-ov"></div><form class="modal" data-form="uform" role="dialog" aria-labelledby="uf-t"><div class="drawer-h"><h3 id="uf-t">${isNew ? 'Tambah pengguna' : 'Ubah akun'}</h3><button type="button" class="icon-btn c-indigo" data-act="close-ov" aria-label="Tutup">${ic('x')}</button></div>
        <div class="stack" style="padding:16px"><div class="form-grid" style="grid-template-columns:1fr 1fr"><div class="field" style="grid-column:1/-1"><label for="uf-name">Nama lengkap</label><input class="inp" id="uf-name" value="${esc(e.name || '')}" required maxlength="80" autocomplete="off"></div>
        <div class="field" style="grid-column:1/-1"><label for="uf-email">Email (dipakai untuk masuk)</label><input class="inp" id="uf-email" type="email" value="${esc(e.email || '')}" required maxlength="120" autocomplete="off"></div>
        <div class="field"><label for="uf-role">Peran</label><select id="uf-role">${S.um.roles.map((r) => opt(r, r, e.role || 'Risk Officer')).join('')}</select></div>
        <div class="field"><label for="uf-unit">Unit kerja</label><input class="inp" id="uf-unit" list="uf-units" value="${esc(e.unit || '')}" maxlength="80"><datalist id="uf-units">${units.map((u) => `<option value="${esc(u)}">`).join('')}</datalist></div>
        ${isNew ? `<div class="field" style="grid-column:1/-1"><label for="uf-pass">Kata sandi awal</label><input class="inp" id="uf-pass" type="text" autocomplete="off" placeholder="Kosongkan agar dibuat otomatis"><span class="hint">Minimal 10 karakter dengan huruf dan angka. Jika dikosongkan, sistem membuat sandi sementara yang hanya ditampilkan sekali.</span></div>
        <label class="row" style="grid-column:1/-1;gap:8px"><input type="checkbox" id="uf-must" checked style="width:18px;height:18px;accent-color:var(--accent)"><span>Wajib ganti kata sandi saat pertama masuk</span></label>` : `<label class="row" style="grid-column:1/-1;gap:8px"><input type="checkbox" id="uf-active" ${e.active ? 'checked' : ''} ${e.id === S.um.me ? 'disabled' : ''} style="width:18px;height:18px;accent-color:var(--accent)"><span>Akun aktif${e.id === S.um.me ? ' <span class="hint">(akun sendiri tidak dapat dinonaktifkan)</span>' : ''}</span></label>`}</div>
        <div class="ro-banner" id="uf-err" hidden style="background:color-mix(in oklab,var(--lv-vh) 12%,var(--surface));color:var(--bad-ink)"></div></div>
        <div class="row" style="justify-content:flex-end;padding:0 16px 16px"><button type="button" class="btn ghost c-indigo" data-act="close-ov">Batal</button><button type="submit" class="btn c-green" id="uf-go">${isNew ? 'Buat akun' : 'Simpan perubahan'}</button></div></form>`;
      const first = $('#uf-name'); if (first) first.focus();
    } else if (S.overlay === 'ureset') {
      const e = S.um.edit || {};
      o.innerHTML = `<div class="scrim" data-act="close-ov"></div><div class="modal" role="dialog" aria-labelledby="ur-t"><div class="drawer-h"><h3 id="ur-t">Reset kata sandi</h3><button type="button" class="icon-btn c-indigo" data-act="close-ov" aria-label="Tutup">${ic('x')}</button></div>
        <div class="stack" style="padding:16px"><p>Kata sandi <b>${esc(e.name)}</b> (${esc(e.email)}) akan diganti dengan sandi sementara yang hanya ditampilkan sekali. Pengguna wajib membuat sandi baru saat masuk berikutnya.${e.id === S.um.me ? '<br><br><b>Ini akun Anda sendiri.</b> Setelah reset, Anda harus mengganti sandi saat masuk berikutnya.' : ''}</p></div>
        <div class="row" style="justify-content:flex-end;padding:0 16px 16px"><button type="button" class="btn ghost c-indigo" data-act="close-ov">Batal</button><button type="button" class="btn c-orange" data-act="um-reset-go" data-v="${e.id}">Reset sekarang</button></div></div>`;
    } else if (S.overlay === 'uresult') {
      const r = S.um.result || {};
      o.innerHTML = `<div class="scrim"></div><div class="modal" role="dialog" aria-labelledby="ux-t"><div class="drawer-h"><h3 id="ux-t">${esc(r.title)}</h3></div>
        <div class="stack" style="padding:16px"><p>${r.body}</p><div class="pw-show"><code id="pw-val">${esc(r.password)}</code><button type="button" class="btn sm c-blue" data-act="um-copy">${ic('copy')}Salin</button></div><p class="hint">Sandi ini <b>tidak disimpan</b> dan tidak dapat ditampilkan lagi. Sampaikan kepada pengguna lewat saluran yang aman.</p></div>
        <div class="row" style="justify-content:flex-end;padding:0 16px 16px"><button type="button" class="btn c-green" data-act="close-ov">Sudah saya catat</button></div></div>`;
    } else if (S.overlay === 'pwd') {
      const forced = S.forcePwd;
      o.innerHTML = `<div class="scrim" ${forced ? '' : 'data-act="close-ov"'}></div><form class="modal" data-form="pwd" role="dialog" aria-labelledby="pwd-t"><div class="drawer-h"><h3 id="pwd-t">${forced ? 'Buat kata sandi baru' : 'Ganti kata sandi'}</h3>${forced ? '' : `<button type="button" class="icon-btn c-indigo" data-act="close-ov" aria-label="Tutup">${ic('x')}</button>`}</div>${forced ? `<div class="ro-banner" style="margin:12px 16px 0">${ic('lock')}<span>Anda masuk dengan kata sandi sementara. Buat kata sandi baru untuk melanjutkan.</span></div>` : ''}
        <div class="stack" style="padding:16px"><div class="field"><label for="pw-cur">Kata sandi saat ini</label><input class="inp" id="pw-cur" type="password" autocomplete="current-password" required></div>
        <div class="field"><label for="pw-new">Kata sandi baru</label><input class="inp" id="pw-new" type="password" autocomplete="new-password" minlength="10" required><span class="hint">Minimal 10 karakter, memuat huruf dan angka.</span></div>
        <div class="field"><label for="pw-rep">Ulangi kata sandi baru</label><input class="inp" id="pw-rep" type="password" autocomplete="new-password" required></div>
        <div class="ro-banner" id="pw-err" hidden style="background:color-mix(in oklab,var(--lv-vh) 12%,var(--surface));color:var(--bad-ink)"></div></div>
        <div class="row" style="justify-content:flex-end;padding:0 16px 16px">${forced ? '' : '<button type="button" class="btn ghost c-indigo" data-act="close-ov">Batal</button>'}<button type="submit" class="btn c-green" id="pw-go">Simpan kata sandi</button></div></form>`;
    } else if (S.overlay === 'notif') {
      o.innerHTML = `<div class="scrim" data-act="close-ov"></div><aside class="drawer" role="dialog" aria-label="Early warning"><div class="drawer-h"><h3>Early warning</h3><button class="icon-btn" data-act="close-ov" aria-label="Tutup">${ic('x')}</button></div><div class="drawer-b">${feed(WARNINGS)}</div></aside>`;
    } else if (S.nav) {
      o.innerHTML = '<div class="scrim" data-act="nav"></div>';
    } else o.innerHTML = '';
    $('#side').classList.toggle('open', S.nav);
    colorize($('#overlay'));
  }

  function render(keepScroll) {
    let { id, arg } = route();
    if (!V[id]) id = 'exec';
    if (!allowed(id === 'risk' ? 'register' : id)) { location.replace('#' + firstAllowed()); return; }
    const changed = S.lastRoute !== id + (arg || '');
    if (changed && id === 'risk') S.dtab = 'sum';
    renderSide(id);
    renderTop();
    const ro = canWrite() ? '' : `<div class="ro-banner">${ic('lock')}<span>Anda masuk sebagai <b>${esc(S.role)}</b>, mode baca saja. Tombol perubahan dinonaktifkan.</span></div>`;
    $('#view').innerHTML = ro + V[id](arg);
    if (!canWrite()) $$('#view [data-w]').forEach((b) => { b.setAttribute('disabled', ''); if (b.tagName === 'A') { b.removeAttribute('href'); b.setAttribute('aria-disabled', 'true'); b.style.opacity = '.45'; b.style.pointerEvents = 'none'; } });
    document.title = `${id === 'risk' ? arg : TITLES[id] || 'ManRisk'} · ManRisk ERM`;
    if (changed && !keepScroll) { window.scrollTo(0, 0); S.nav = false; }
    S.lastRoute = id + (arg || '');
    renderOverlay();
    colorize(document);
    const m = $('#msgs'); if (m) m.scrollTop = m.scrollHeight;
  }

  /* ======================= Interaksi ======================= */
  function toast(t) {
    const el = document.createElement('div'); el.className = 'toast'; el.textContent = t;
    $('#toasts').appendChild(el); setTimeout(() => el.remove(), 3200);
  }

  const ACT = {
    toast: (el) => toast(el.dataset.v),
    page: (el) => { const [k, p] = el.dataset.v.split(':'); S.pg[k] = +p; render(true); const v = $('#view'); if (v) v.querySelector('.tbl-wrap') && v.querySelector('.tbl-wrap').scrollIntoView({ block: 'nearest' }); },
    nav: () => { S.nav = !S.nav; renderOverlay(); },
    notif: () => { S.overlay = 'notif'; renderOverlay(); },
    'close-ov': () => { S.overlay = null; renderOverlay(); },
    umenu: () => { S.overlay = S.overlay === 'umenu' ? null : 'umenu'; renderOverlay(); },
    umtab: (el) => { S.um.tab = el.dataset.v; S.um.err = ''; render(true); },
    'um-reload': () => { S.um.err = ''; if (S.um.tab === 'log') S.um.log = null; else S.um.list = AUTH ? null : S.um.list; render(true); },
    'um-new': () => { S.um.edit = null; S.overlay = 'uform'; renderOverlay(); },
    'um-edit': (el) => { S.um.edit = S.um.list.find((u) => u.id === el.dataset.v); S.overlay = 'uform'; renderOverlay(); },
    'um-reset': (el) => { S.um.edit = S.um.list.find((u) => u.id === el.dataset.v); S.overlay = 'ureset'; renderOverlay(); },
    'um-reset-go': async (el) => {
      const u = S.um.list.find((x) => x.id === el.dataset.v);
      try {
        let temp;
        if (AUTH) { const j = await api('api/users.php', { action: 'reset', id: u.id }); temp = j.tempPassword; Object.assign(S.um, { list: j.users, log: null }); }
        else { temp = 'Demo' + Math.random().toString(36).slice(2, 8) + '9x'; u.mustChange = true; }
        S.um.result = { title: 'Kata sandi sementara', body: `Sandi sementara untuk <b>${esc(u.name)}</b> (${esc(u.email)}):`, password: temp };
        S.overlay = 'uresult'; render(true);
      } catch (e) { toast(e.message); }
    },
    'um-toggle': async (el) => {
      const u = S.um.list.find((x) => x.id === el.dataset.v);
      try {
        if (AUTH) { const j = await api('api/users.php', { action: 'toggle', id: u.id }); Object.assign(S.um, { list: j.users, log: null }); }
        else u.active = !u.active;
        toast(`${u.name} ${u.active ? 'diaktifkan' : 'dinonaktifkan'}`); render(true);
      } catch (e) { toast(e.message); }
    },
    'um-delete': async (el) => {
      const u = S.um.list.find((x) => x.id === el.dataset.v);
      if (!el.dataset.sure) { el.dataset.sure = '1'; el.textContent = 'Hapus?'; el.classList.remove('ghost'); setTimeout(() => { if (el.isConnected) { delete el.dataset.sure; el.innerHTML = ic('x'); el.classList.add('ghost'); } }, 3000); return; }
      try {
        if (AUTH) { const j = await api('api/users.php', { action: 'delete', id: u.id }); Object.assign(S.um, { list: j.users, log: null }); }
        else S.um.list = S.um.list.filter((x) => x.id !== u.id);
        toast(`Akun ${u.email} dihapus`); render(true);
      } catch (e) { toast(e.message); render(true); }
    },
    'um-copy': (el) => {
      const v = $('#pw-val').textContent;
      const sel = () => { const r = document.createRange(); r.selectNodeContents($('#pw-val')); const s2 = getSelection(); s2.removeAllRanges(); s2.addRange(r); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(v).then(() => toast('Kata sandi disalin')).catch(() => { sel(); toast('Pilih dan salin secara manual'); });
      else { sel(); toast('Pilih dan salin secara manual'); }
    },
    'pwd-open': () => { S.overlay = 'pwd'; renderOverlay(); const i = $('#pw-cur'); if (i) i.focus(); },
    logout: () => { if (AUTH) logout(); else toast('Mode demo: tidak ada sesi untuk diakhiri'); },
    theme: () => {
      if (S.overlay === 'umenu') { S.overlay = null; renderOverlay(); }
      const r = document.documentElement, cur = r.getAttribute('data-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      r.setAttribute('data-theme', cur === 'dark' ? 'light' : 'dark');
      try { localStorage.setItem('manrisk-theme', r.getAttribute('data-theme')); } catch (e) { /* abaikan */ }
    },
    heat: (el) => { S.heat = el.dataset.v; S.reg.cell = ''; render(true); },
    hmcell: (el) => { S.reg = Object.assign({}, S.reg, { cell: el.dataset.v, q: '', lv: '', unit: '', cat: '', st: '' }); S.pg.reg = 1; location.hash = 'register'; },
    'reg-clear': (el) => { S.pg.reg = 1; const k = el.dataset.v; if (k === 'all') S.reg = { q: '', lv: '', unit: '', cat: '', st: '', cell: '', sort: S.reg.sort }; else S.reg[k] = ''; render(true); },
    dtab: (el) => { S.dtab = el.dataset.v; render(true); },
    ctx: (el) => { S.ctxTab = el.dataset.v; render(true); },
    orgtab: (el) => { S.orgTab = el.dataset.v; render(true); },
    tview: (el) => { S.treatView = el.dataset.v; render(true); },
    tfilter: (el) => { S.treatFilter = el.dataset.v; S.pg.act = 1; if (S.treatView === 'kanban' && el.dataset.v) S.treatView = 'list'; render(true); },
    obj: (el) => { S.objSel = el.dataset.v; if (route().id === 'objective') render(true); },
    'inc-open': (el) => { S.incSel = el.dataset.v; if (route().id !== 'incidents') location.hash = 'incidents'; else render(true); },
    'ctrl-open': (el) => { S.ctrlSel = el.dataset.v; render(true); },
    'ctrl-close': () => { S.ctrlSel = null; render(true); },
    'wf-sel': (el) => { S.wfSel = el.dataset.v; render(true); },
    'wf-do': (el) => {
      const a = S.approvals.find((x) => x.id === S.wfSel), v = el.dataset.v, note = ($('#wf-note') || {}).value || '';
      if (v === 'approve') {
        a.stage++; if (a.stage >= D.WF_STAGES.length) { a.st = 'Disetujui'; const r = riskById(a.ref); if (r && a.type === 'Risiko Baru') r.status = 'Dalam Penanganan'; if (r && a.type === 'Penutupan Risiko') r.status = 'Ditutup'; }
        log('Menyetujui ' + a.type.toLowerCase(), a.ref, 'Tahap', D.WF_STAGES[a.stage - 1], a.st === 'Disetujui' ? 'Disetujui' : D.WF_STAGES[a.stage]);
        toast(a.st === 'Disetujui' ? `${a.id} disetujui penuh` : `${a.id} diteruskan ke ${D.WF_STAGES[a.stage]}`);
      } else if (v === 'revise') { a.st = 'Revisi'; log('Meminta revisi', a.ref, 'Status', 'Menunggu', 'Revisi'); toast(`${a.id} dikembalikan ke pengaju untuk revisi`); }
      else { a.st = 'Ditolak'; log('Menolak ' + a.type.toLowerCase(), a.ref, 'Status', 'Menunggu', 'Ditolak'); toast(`${a.id} ditolak`); }
      if (note) S.audit[0].n += ` · “${note}”`;
      render(true);
    },
    mpick: (el) => {
      const [p, l, i] = el.dataset.v.split(':'); const L = +l, I = +i;
      if (p === 'a') { S.anaL = L; S.anaI = I; } else if (p === 'i') { S.wz.d.iL = L; S.wz.d.iI = I; } else { S.wz.d.rL = L; S.wz.d.rI = I; }
      render(true);
    },
    'wz-step': (el) => { const v = +el.dataset.v; if (v >= 1 && v <= 5) { S.wz.step = v; render(true); $('#view').scrollIntoView({ block: 'start' }); } },
    'wz-reset': () => { S.wz = newWizard(false); render(true); },
    'wz-treat': (el) => { S.wz.d.treat = el.dataset.v; render(true); },
    'wz-addact': () => { S.wz.d.actions.push({ t: '', pic: '', due: '' }); render(true); },
    'wz-delact': (el) => { S.wz.d.actions.splice(+el.dataset.v, 1); if (!S.wz.d.actions.length) S.wz.d.actions.push({ t: '', pic: '', due: '' }); render(true); },
    'ai-cand': (el) => { const c = aiCands(S.wz.d.proc)[+el.dataset.v]; Object.assign(S.wz.d, { name: c.name, cause: c.cause, event: c.event, impact: c.impact, cat: c.cat }); toast('Kandidat AI diterapkan ke formulir'); render(true); },
    'wz-submit': () => {
      const d = S.wz.d;
      if (!d.name || !d.cause || !d.event || !d.impact) { toast('Lengkapi nama risiko, penyebab, peristiwa, dan dampak sebelum mengajukan.'); S.wz.step = !d.name ? 1 : 2; render(true); return; }
      const n = S.risks.length + 1, id = `R-${String(n).padStart(3, '0')}`;
      S.risks.push({ id, name: d.name, unit: +d.unit, cat: d.cat, obj: d.obj, proc: d.proc || '—', cause: d.cause, event: d.event, impact: d.impact, owner: d.owner, inh: [d.iL, d.iI], res: [d.rL, d.rI], tgt: [Math.max(1, d.rL - 1), d.rI], treat: d.treat, status: 'Menunggu Persetujuan', trend: 'flat', due: d.due, ctrl: [], kri: [], src: `${d.src1} · ${d.src2}` });
      d.actions.filter((a) => a.t).forEach((a, i) => S.actions.push({ id: `A-${String(S.actions.length + 1).padStart(3, '0')}`, risk: id, t: a.t, pic: a.pic || '—', budget: 0, due: a.due || d.due, prog: 0, prio: 'Sedang', ev: 0 }));
      const wf = `WF-${413 + n}`; S.approvals.unshift({ id: wf, type: 'Risiko Baru', ref: id, by: ME(), role: S.role, date: D.TODAY, stage: 1, st: 'Proses', note: 'Diajukan melalui wizard identifikasi risiko.' });
      log('Mengajukan risiko baru', id, 'Status', 'Draft', 'Menunggu Persetujuan');
      S.wz = newWizard(false); toast(`${id} tersimpan dan diajukan ke Risk Owner (${wf})`); location.hash = 'risk-' + id;
    },
    'ai-ask': (el) => askAI(el.dataset.v),
    'gen-report': (el, e) => {
      if (route().id !== 'reports') { e.preventDefault(); location.hash = 'reports'; }
      const sel = $('#rep-per'); if (sel) S.reportPeriod = sel.value;
      S.reportBusy = true; S.report = null; setTimeout(() => render(true), 0);
      setTimeout(() => { S.reportBusy = false; S.report = S.reportPeriod; if (route().id === 'reports') render(true); }, 1100);
    }
  };

  function askAI(q) {
    if (!q || S.aiBusy) return;
    if (!S.ai) S.ai = [{ me: false, h: aiReply('') }];
    S.ai.push({ me: true, h: esc(q) }); S.aiBusy = true;
    if (route().id !== 'ai') location.hash = 'ai'; else render(true);
    setTimeout(() => { S.ai.push({ me: false, h: aiReply(q) }); S.aiBusy = false; if (route().id === 'ai') render(true); }, 700);
  }

  document.addEventListener('click', (e) => {
    const a = e.target.closest('[data-act]');
    const link = e.target.closest('a[href]');
    const nestedLink = a && link && link !== a && a.contains(link);
    if (a && !nestedLink && !a.disabled && a.tagName !== 'INPUT' && a.tagName !== 'SELECT') {
      const fn = ACT[a.dataset.act];
      if (fn) { if (a.tagName !== 'A' || a.dataset.act === 'obj') { if (a.tagName === 'BUTTON' || a.tagName === 'TR') e.preventDefault(); } fn(a, e); return; }
    }
    const g = e.target.closest('[data-go]');
    if (g && !e.target.closest('a')) location.hash = g.dataset.go;
    if (e.target.closest('.nav a') && S.nav) { S.nav = false; renderOverlay(); }
    if (e.target.closest('.drawer a')) { S.overlay = null; renderOverlay(); }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.matches('tr[data-go]')) location.hash = e.target.dataset.go;
    if (e.key === 'Escape' && (S.overlay || S.nav) && !(S.overlay === 'pwd' && S.forcePwd) && S.overlay !== 'uresult') { S.overlay = null; S.nav = false; renderOverlay(); }
  });
  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t.matches('input[type=checkbox][data-act="toast"]')) toast(t.dataset.v);
    if (t.matches('[data-rolesel]')) { S.role = t.value; S.ctrlSel = null; toast(`Masuk sebagai ${S.role}${canWrite() ? '' : ' (baca saja)'}`); render(); }
    if (t.matches('[data-orgsel]') && t.value !== 'bldn') { toast('Purwarupa hanya memuat data BLDN. Tenant lain ditampilkan sebagai contoh multi-organisasi.'); t.value = 'bldn'; }
    if (t.matches('#per-sel') && t.value !== 'TW III 2026') { toast(`Periode ${t.value}: purwarupa menampilkan data TW III 2026`); t.value = 'TW III 2026'; }
    if (t.matches('select[data-reg]')) { S.reg[t.dataset.reg] = t.value; S.pg.reg = 1; render(true); }
    if (t.matches('[data-anacat]')) { S.anaCat = t.value; render(true); }
    if (t.matches('select[data-wz]')) { S.wz.d[t.dataset.wz] = t.dataset.wz === 'unit' ? +t.value : t.value; S.wz.example = false; }
    if (t.matches('#rep-per')) S.reportPeriod = t.value;
    if (t.matches('#rev-per')) { S.revPeriod = t.value; toast(`Menampilkan reviu ${t.value} (data contoh TW III)`); }
    if (t.matches('[data-ctrl]')) { const c = S.controls.find((x) => x.id === S.ctrlSel); const k = t.dataset.ctrl; const prev = D.EFF[c[k]]; c[k] = +t.value; log('Menilai efektivitas kontrol', c.id, k === 'des' ? 'Efektivitas desain' : 'Efektivitas operasi', prev, D.EFF[c[k]]); toast(`Penilaian ${c.id} disimpan`); render(true); }
    if (t.matches('input[type=file][data-upload]')) {
      const files = Array.from(t.files || []); const ref = t.dataset.upload === 'doc' ? 'Umum' : t.dataset.upload;
      files.forEach((f) => S.docs.unshift({ n: f.name, type: 'Bukti', ref, v: 'v1.0', by: ME(), d: D.TODAY, exp: '—', st: 'Draft' }));
      if (files.length) { log('Mengunggah bukti', ref, 'Dokumen', '—', files.map((f) => f.name).join(', ')); toast(`${files.length} berkas diunggah sebagai draft`); render(true); }
    }
  });
  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t.matches('[data-wz]') && t.tagName !== 'SELECT') {
      S.wz.d[t.dataset.wz] = t.value; S.wz.example = false;
      const live = $('[data-live="stmt"]'); if (live) live.innerHTML = stmtHTML(S.wz.d);
    }
    if (t.matches('[data-wza]')) { const [i, k] = t.dataset.wza.split(':'); S.wz.d.actions[+i][k] = t.value; }
    if (t.matches('#reg-q')) { S.reg.q = t.value; S.pg.reg = 1; clearTimeout(t._t); t._t = setTimeout(() => { render(true); const el = $('#reg-q'); if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); } }, 250); }
    if (t.matches('[data-umq]')) { S.um.q = t.value; clearTimeout(t._t); t._t = setTimeout(() => { render(true); const el = $('#um-q'); if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); } }, 250); }
    if (t.matches('[data-auditq]')) { S.auditQ = t.value; S.pg.audit = 1; clearTimeout(t._t); t._t = setTimeout(() => { render(true); const el = $('#audit-q'); if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); } }, 250); }
  });
  document.addEventListener('submit', (e) => {
    e.preventDefault();
    const f = e.target.dataset.form;
    if (f === 'search') { const q = $('#gq').value.trim(); S.reg = { q, lv: '', unit: '', cat: '', st: '', cell: '', sort: 'res' }; S.pg.reg = 1; if (allowed('register')) location.hash = 'register'; if (route().id === 'register') render(true); }
    if (f === 'ai') { const i = $('#ai-in'); const q = i.value.trim(); i.value = ''; askAI(q); }
    if (f === 'uform') {
      const e = S.um.edit, isNew = !e, err = $('#uf-err'), go = $('#uf-go');
      const fail = (m) => { err.textContent = m; err.hidden = false; go.disabled = false; go.textContent = isNew ? 'Buat akun' : 'Simpan perubahan'; };
      const body = { name: $('#uf-name').value.trim(), email: $('#uf-email').value.trim(), role: $('#uf-role').value, unit: $('#uf-unit').value.trim() };
      if (body.name.length < 2) { fail('Nama minimal 2 karakter.'); return; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(body.email)) { fail('Alamat email tidak valid.'); return; }
      go.disabled = true; go.textContent = 'Menyimpan…';
      (async () => {
        if (isNew) {
          const pw = $('#uf-pass').value, must = $('#uf-must').checked;
          if (AUTH) {
            const j = await api('api/users.php', Object.assign({ action: 'create', password: pw, mustChange: must }, body));
            Object.assign(S.um, { list: j.users, log: null });
            if (j.tempPassword) { S.um.result = { title: 'Akun dibuat', body: `Sandi sementara untuk <b>${esc(body.name)}</b> (${esc(body.email)}):`, password: j.tempPassword }; S.overlay = 'uresult'; }
            else { S.overlay = null; toast(`Akun ${body.email} dibuat`); }
          } else {
            if (S.um.list.some((u) => u.email.toLowerCase() === body.email.toLowerCase())) throw new Error('Email sudah dipakai akun lain.');
            S.um.list.push(Object.assign({ id: 'demo' + Date.now(), active: true, lastLogin: null, mustChange: must }, body));
            S.overlay = null; toast('Mode demo: akun ditambahkan di browser ini');
          }
        } else {
          const active = $('#uf-active').checked;
          if (AUTH) { const j = await api('api/users.php', Object.assign({ action: 'update', id: e.id, active }, body)); Object.assign(S.um, { list: j.users, log: null }); }
          else Object.assign(e, body, { active });
          S.overlay = null; toast('Perubahan akun disimpan');
        }
        render(true);
      })().catch((x) => fail(x.message));
    }
    if (f === 'pwd') {
      const cur = $('#pw-cur').value, nw = $('#pw-new').value, rep = $('#pw-rep').value, err = $('#pw-err'), go = $('#pw-go');
      const fail = (m) => { err.textContent = m; err.hidden = false; };
      if (nw !== rep) { fail('Ulangan kata sandi baru tidak sama.'); return; }
      if (!AUTH) { toast('Mode demo: kata sandi tidak disimpan'); S.overlay = null; renderOverlay(); return; }
      go.disabled = true; go.textContent = 'Menyimpan…';
      api('api/password.php', { current: cur, next: nw }).then(() => { S.overlay = null; if (S.forcePwd) { S.forcePwd = false; AUTH.user.mustChange = false; } renderOverlay(); toast('Kata sandi berhasil diganti'); })
        .catch((x) => { fail(x.message); go.disabled = false; go.textContent = 'Simpan kata sandi'; });
    }
  });

  /* Tooltip & crosshair */
  const tip = $('#tip');
  document.addEventListener('mousemove', (e) => {
    const el = e.target.closest && e.target.closest('[data-tip]');
    if (!el) { tip.hidden = true; return; }
    tip.innerHTML = el.dataset.tip; tip.hidden = false;
    const w = tip.offsetWidth, h = tip.offsetHeight;
    let x = e.clientX + 14, y = e.clientY + 14;
    if (x + w > innerWidth - 8) x = e.clientX - w - 14;
    if (y + h > innerHeight - 8) y = e.clientY - h - 14;
    tip.style.left = Math.max(8, x) + 'px'; tip.style.top = Math.max(8, y) + 'px';
    if (el.classList.contains('hit')) {
      const svg = el.ownerSVGElement, gl = svg.querySelector('.guide'), gd = svg.querySelector('.gdot');
      gl.setAttribute('x1', el.dataset.gx); gl.setAttribute('x2', el.dataset.gx); gl.setAttribute('visibility', 'visible');
      gd.setAttribute('cx', el.dataset.gx); gd.setAttribute('cy', el.dataset.gy); gd.setAttribute('visibility', 'visible');
    }
  });
  document.addEventListener('mouseout', (e) => {
    if (e.target.classList && e.target.classList.contains('hit') && !(e.relatedTarget && e.relatedTarget.classList && e.relatedTarget.classList.contains('hit'))) {
      const svg = e.target.ownerSVGElement; svg.querySelector('.guide').setAttribute('visibility', 'hidden'); svg.querySelector('.gdot').setAttribute('visibility', 'hidden');
    }
  });
  window.addEventListener('scroll', () => { tip.hidden = true; }, { passive: true });

  try { const th = localStorage.getItem('manrisk-theme'); if (th) document.documentElement.setAttribute('data-theme', th); } catch (e) { /* abaikan */ }
  window.addEventListener('hashchange', () => render());
  (async function boot() {
    try {
      const r = await fetch('api/session.php', { credentials: 'same-origin', cache: 'no-store' });
      const j = await r.json();
      if (j && j.authenticated) { AUTH = { user: j.user, csrf: j.csrf, idle: j.idleLimit }; S.role = j.user.role; startIdleWatch(); if (j.user.mustChange) { S.forcePwd = true; S.overlay = 'pwd'; } }
      else if (j && j.authenticated === false) { location.replace('./'); return; }
    } catch (e) { AUTH = null; /* tanpa server PHP (pratinjau / berkas lokal): mode demo */ }
    render();
  })();
})();
