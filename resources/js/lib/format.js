const idr = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const num = new Intl.NumberFormat('id-ID');
export const fmt = {
    money: (v) => (v === null || v === undefined || v === '' ? '—' : idr.format(Number(v))),
    short: (v) => { v = Number(v || 0); if (v >= 1e9) return `Rp ${(v / 1e9).toFixed(1).replace('.', ',')} M`; if (v >= 1e6) return `Rp ${(v / 1e6).toFixed(v % 1e6 ? 1 : 0).replace('.', ',')} jt`; return idr.format(v); },
    num: (v, d = 0) => (v === null || v === undefined ? '—' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v))),
    date: (v) => { if (!v) return '—'; const d = new Date(v); return isNaN(d) ? v : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }); },
    datetime: (v) => { if (!v) return '—'; const d = new Date(v); return isNaN(d) ? v : d.toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); },
    ago: (v) => { if (!v) return '—'; const s = (Date.now() - new Date(v).getTime()) / 1000; if (s < 60) return 'baru saja'; if (s < 3600) return `${Math.floor(s / 60)} mnt lalu`; if (s < 86400) return `${Math.floor(s / 3600)} jam lalu`; if (s < 86400 * 30) return `${Math.floor(s / 86400)} hari lalu`; return fmt.date(v); },
    period: (p) => { if (!p) return '—'; const [y, m] = p.split('-'); return `${['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][Number(m) - 1]} ${y}`; },
    pct: (v) => `${Math.round(Number(v || 0))}%`,
    initials: (n) => (n || '').split(' ').filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join(''),
};
export const LV = { low: 'l', medium: 'm', high: 'h', very_high: 'vh' };
export const lvKey = (level) => LV[level] || 'm';
export const lvFromScore = (s) => (s >= 16 ? 'very_high' : s >= 10 ? 'high' : s >= 5 ? 'medium' : 'low');
export const EVAL_PILL = { acceptable: 'ok', monitor: 'run', treat: 'warn', escalate: 'bad', critical: 'bad' };
export const STATUS_PILL = { draft: '', pending: 'warn', treating: 'run', monitoring: 'ok', closed: 'off', reported: 'warn', investigating: 'run', corrective: 'run', open: 'warn', in_progress: 'run', done: 'ok', todo: '', verify: 'warn', running: 'run', overdue: 'bad', cancelled: 'off', normal: 'ok', warning: 'warn', critical: 'bad', approved: 'ok', rejected: 'bad', revision: 'warn', met: 'ok', partial: 'warn', unmet: 'bad', review: 'run', expired: 'bad', active: 'ok', inactive: 'off' };
export const PALETTE = ['c-blue', 'c-violet', 'c-teal', 'c-orange', 'c-pink', 'c-green', 'c-indigo', 'c-cyan', 'c-amber'];
export const toast = (msg, kind = 'info') => {
    let box = document.getElementById('toasts');
    if (!box) { box = document.createElement('div'); box.id = 'toasts'; box.className = 'toasts'; box.setAttribute('aria-live', 'polite'); document.body.appendChild(box); }
    const t = document.createElement('div'); t.className = `toast ${kind}`; t.textContent = msg; box.appendChild(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 300); }, 3600);
};
export const confirmAction = (msg) => window.confirm(msg);
