// Markdown ringkas untuk keluaran AI: di-escape dulu, lalu heading/tebal/miring/daftar; kode objek ditautkan ke halamannya
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const escRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const safeHref = (h) => (typeof h === 'string' && h.startsWith('/') && !h.startsWith('//') ? h : null);
export const linkCodes = (html, refs = {}) => {
  const codes = Object.keys(refs).filter((c) => c && safeHref(refs[c])).sort((a, b) => b.length - a.length);
  if (!codes.length) return html;
  return html.replace(new RegExp(`(?<![\\w-])(${codes.map((c) => escRe(esc(c))).join('|')})(?![\\w-])`, 'g'), (c) => {
    const key = codes.find((k) => esc(k) === c);
    return key ? `<a href="${esc(refs[key])}">${c}</a>` : c;
  });
};
export const md = (s, refs = {}) => linkCodes(esc(s)
  .replace(/^### (.*)$/gm, '<h4>$1</h4>').replace(/^## (.*)$/gm, '<h3>$1</h3>').replace(/^# (.*)$/gm, '<h3>$1</h3>')
  .replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/(^|[\s(])_(.+?)_(?=[\s.,)]|$)/gm, '$1<i>$2</i>').replace(/\*(.+?)\*/g, '<i>$1</i>')
  .replace(/^\s*\d+\. (.*)$/gm, '<li class="ol">$1</li>').replace(/^\s*[-•] (.*)$/gm, '<li>$1</li>')
  .replace(/(<li>.*<\/li>\n?)+/g, (m) => `<ul>${m.replace(/\n/g, '')}</ul>\n`).replace(/(<li class="ol">.*<\/li>\n?)+/g, (m) => `<ol>${m.replace(/ class="ol"/g, '').replace(/\n/g, '')}</ol>\n`)
  .replace(/<\/(ul|ol|h3|h4)>\n/g, '</$1>').replace(/\n{2,}/g, '<br><br>').replace(/\n/g, '<br>'), refs);
// URL halaman → modul AI Assistant (null = panel tidak ditampilkan)
export const aiModule = (url) => {
  const p = (url || '').split('?')[0].replace(/\/+$/, '') || '/';
  const id = (re) => { const m = p.match(re); return m ? Number(m[1]) : null; };
  const table = [
    [/^\/dashboard\/executive$/, 'executive'], [/^\/dashboard$/, 'dashboard'], [/^\/risks\/matrix$/, 'matrix'], [/^\/risks\/evaluation$/, 'evaluation'], [/^\/risks\/residual$/, 'residual'],
    [/^\/risks\/create$/, 'risk_form'], [/^\/risks\/\d+\/edit$/, 'risk_form'], [/^\/risks\/(\d+)$/, 'risk', true], [/^\/risks$/, 'risks'], [/^\/reviews/, 'reviews'], [/^\/context/, 'context'], [/^\/criteria/, 'criteria'],
    [/^\/action-plans\/(\d+)$/, 'action_plan', true], [/^\/action-plans$/, 'action_plans'], [/^\/controls\/(\d+)$/, 'control', true], [/^\/controls$/, 'controls'], [/^\/improvements/, 'improvements'],
    [/^\/incidents\/losses$/, 'losses'], [/^\/incidents\/(\d+)$/, 'incident', true], [/^\/incidents$/, 'incidents'], [/^\/kris/, 'kris'], [/^\/alerts/, 'alerts'], [/^\/organization\/objectives/, 'objectives'],
    [/^\/organization\/units/, 'units'], [/^\/framework/, 'framework'], [/^\/rmi\/(\d+)$/, 'rmi_assessment', true], [/^\/rmi$/, 'rmi'], [/^\/approvals/, 'approvals'], [/^\/reports/, 'reports'], [/^\/documents/, 'documents'],
    [/^\/admin\/audit/, 'audit'], [/^\/admin\/users/, 'users'], [/^\/settings\/organization/, 'settings'], [/^\/ai(\/|$)/, null],
  ];
  for (const [re, mod, withId] of table) if (re.test(p)) return mod ? { module: mod, id: withId ? id(re) : null } : null;
  return { module: 'general', id: null };
};
