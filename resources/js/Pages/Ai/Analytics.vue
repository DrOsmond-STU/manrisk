<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Chart from '../../Components/Chart.vue';
import { fmt, toast, confirmAction } from '../../lib/format';

const props = defineProps({ enabled: Boolean, remaining: Number, provider: String, examples: Array, dashboards: Array, open: Number, datasets: Array });
const prompt = ref('');
const busy = ref(false);
const left = ref(props.remaining);
const saved = ref([...props.dashboards]);
const current = ref(null); // {id?, title, prompt, shared, mine, widgets: [hasil]}
const summary = ref('');
const model = ref('');

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
const call = async (method, url, body) => {
  const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }, body: body ? JSON.stringify(body) : undefined });
  const data = await res.json().catch(() => ({}));
  if (res.status === 429) throw new Error('Terlalu banyak permintaan dalam satu menit. Tunggu sebentar lalu coba lagi.');
  if (!res.ok) throw new Error(data.message && !data.errors ? data.message : Object.values(data.errors || {}).flat()[0] || data.message || 'Permintaan gagal.');
  return data;
};

const generate = async (append = false) => {
  const p = prompt.value.trim();
  if (p.length < 3) { toast('Tulis permintaan dashboard terlebih dahulu.', 'warn'); return; }
  busy.value = true;
  try {
    const d = await call('POST', '/ai/analytics/plan', { prompt: p });
    left.value = d.remaining; model.value = d.model;
    if (!d.widgets.length) { toast('Permintaan belum dapat diterjemahkan menjadi grafik. Coba sebut data dan pengelompokannya, mis. "risiko per unit".', 'warn'); return; }
    if (append && current.value) {
      current.value.widgets = [...current.value.widgets, ...d.widgets].slice(0, 12);
      await refresh();
    } else {
      current.value = { id: null, title: d.title, prompt: p, shared: false, mine: true, widgets: d.widgets };
      summary.value = d.summary;
    }
  } catch (e) { toast(e.message, 'bad'); } finally { busy.value = false; }
};

const specs = () => current.value.widgets.map((w) => w.spec);
const refresh = async () => {
  if (!current.value?.widgets.length) return;
  busy.value = true;
  try { const d = await call('POST', '/ai/analytics/run', { widgets: specs() }); current.value.widgets = d.widgets; summary.value = d.summary; } catch (e) { toast(e.message, 'bad'); } finally { busy.value = false; }
};
const setType = async (w, type) => { w.spec = { ...w.spec, type, list: type === 'table' ? w.spec.list : false }; await refresh(); };
const remove = async (i) => { current.value.widgets.splice(i, 1); if (current.value.widgets.length) await refresh(); else current.value = null; };
const move = async (i, d) => { const ws = current.value.widgets; const j = i + d; if (j < 0 || j >= ws.length) return; [ws[i], ws[j]] = [ws[j], ws[i]]; };

const openSaved = async (d) => {
  current.value = { id: d.id, title: d.title, prompt: d.prompt, shared: d.shared, mine: d.mine, owner: d.owner, widgets: d.widgets.map((spec) => ({ spec, type: spec.type, title: spec.title, loading: true })) };
  prompt.value = d.prompt || '';
  await refresh();
  window.history.replaceState(null, '', `/ai/analytics?d=${d.id}`);
};
const save = async (asNew = false) => {
  const c = current.value;
  if (!c?.title?.trim()) { toast('Beri judul dashboard.', 'warn'); return; }
  const body = { title: c.title.trim(), prompt: c.prompt, shared: !!c.shared, widgets: specs() };
  try {
    const editing = c.id && c.mine && !asNew;
    const d = await call(editing ? 'PUT' : 'POST', editing ? `/ai/dashboards/${c.id}` : '/ai/dashboards', body);
    toast(d.message, 'ok');
    const row = { id: d.id, title: body.title, prompt: body.prompt, shared: body.shared, widgets: body.widgets, mine: true, updated_at: new Date().toISOString() };
    saved.value = [row, ...saved.value.filter((x) => x.id !== d.id)];
    c.id = d.id; c.mine = true;
    window.history.replaceState(null, '', `/ai/analytics?d=${d.id}`);
  } catch (e) { toast(e.message, 'bad'); }
};
const destroy = async (d) => {
  if (!confirmAction(`Hapus dashboard "${d.title}"?`)) return;
  try { await call('DELETE', `/ai/dashboards/${d.id}`); saved.value = saved.value.filter((x) => x.id !== d.id); if (current.value?.id === d.id) current.value = null; toast('Dashboard dihapus.', 'ok'); } catch (e) { toast(e.message, 'bad'); }
};
const useExample = (e) => { prompt.value = e; generate(false); };
const onKey = (e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) generate(false); };

// ---- tampilan nilai & grafik ----
const val = (v, f) => (v === null || v === undefined || v === '' ? '—' : f === 'money' ? fmt.short(v) : f === 'percent' ? `${fmt.num(v, 1)}%` : typeof v === 'number' ? fmt.num(v, Number.isInteger(v) ? 0 : 1) : v);
const span = (w) => (w.type === 'kpi' ? 3 : w.type === 'table' ? (w.spec?.list ? 12 : 6) : 6);
// warna semantik untuk kelompok berjenjang (level risiko, status KRI/action plan, efektivitas); lainnya memakai palet bawaan
const SEM = { High: 'vh', 'Sangat Tinggi': 'vh', 'Moderate to High': 'h', Tinggi: 'h', Moderate: 'm', Sedang: 'm', 'Low to Moderate': 'lm', Low: 'l', Rendah: 'l',
  Kritis: 'vh', Waspada: 'm', Normal: 'l', Terlambat: 'vh', Selesai: 'l', 'Tidak Efektif': 'vh', 'Sebagian Efektif': 'm', Efektif: 'l', 'Sangat Efektif': 'l', 'Perlu Eskalasi': 'h', 'Perlu Penanganan': 'm', Dipantau: 'lm', 'Dapat Diterima': 'l' };
const lvColor = (k) => getComputedStyle(document.documentElement).getPropertyValue(`--lv-${k}`).trim();
const semantic = (names) => (names.length && names.every((n) => SEM[n]) ? names.map((n) => lvColor(SEM[n])) : null);
const option = (w) => {
  const multi = (w.series || []).length > 1;
  const fmtV = (v) => val(v, w.format);
  const series = (w.series || []).map((s) => ({ name: s.name, data: s.data, type: w.type === 'line' ? 'line' : 'bar', smooth: true, areaStyle: w.type === 'line' && !multi ? { opacity: 0.12 } : undefined, stack: multi && w.type !== 'line' ? 'total' : undefined, barMaxWidth: 34, emphasis: { focus: 'series' } }));
  const sc = multi ? semantic(series.map((x) => x.name)) : null;
  if (sc) series.forEach((x, i) => { x.itemStyle = { color: sc[i] }; x.lineStyle = { color: sc[i] }; });
  if (w.type === 'pie') {
    const pc = semantic(w.labels || []);
    return { color: pc || undefined, tooltip: { trigger: 'item', formatter: (p) => `${p.name}: ${fmtV(p.value)} (${p.percent}%)` }, legend: { bottom: 0, type: 'scroll' },
      series: [{ type: 'pie', radius: ['42%', '70%'], center: ['50%', '45%'], itemStyle: { borderColor: 'transparent', borderWidth: 2 }, label: { formatter: '{b}\n{d}%' }, data: (w.labels || []).map((l, i) => ({ name: l, value: w.series[0].data[i] })) }] };
  }
  const cat = { type: 'category', data: w.labels, axisLabel: { interval: 0, width: w.type === 'hbar' ? 130 : 90, overflow: 'truncate', rotate: w.type !== 'hbar' && (w.labels || []).length > 6 ? 30 : 0 } };
  const vax = { type: 'value', axisLabel: { formatter: fmtV }, splitLine: { lineStyle: { opacity: 0.4 } } };
  const bc = !multi && w.type !== 'line' ? semantic(w.labels || []) : null;
  if (bc) series[0].data = series[0].data.map((v, i) => ({ value: v, itemStyle: { color: bc[i] } }));
  return { tooltip: { trigger: 'axis', valueFormatter: fmtV }, legend: multi ? { top: 0, type: 'scroll' } : undefined, grid: { left: 8, right: 16, top: multi ? 34 : 16, bottom: 8, containLabel: true },
    xAxis: w.type === 'hbar' ? vax : cat, yAxis: w.type === 'hbar' ? { ...cat, inverse: true } : vax, series };
};
const height = (w) => (w.type === 'hbar' ? `${Math.max(220, Math.min(520, (w.labels || []).length * 34 + 60))}px` : '280px');
const typeOptions = (w) => (w.spec?.list ? ['table'] : (w.spec?.group || w.spec?.time) ? ['bar', 'hbar', 'line', 'pie', 'table'] : ['kpi']);
const TYPE_LABEL = { kpi: 'Angka', bar: 'Batang', hbar: 'Batang horizontal', line: 'Garis', pie: 'Donat', table: 'Tabel' };
const csv = (w) => {
  const rows = w.columns ? [w.columns, ...w.rows] : [[w.group_label || 'Kelompok', ...(w.series || []).map((s) => s.name)], ...(w.labels || []).map((l, i) => [l, ...w.series.map((s) => s.data[i])])];
  const text = rows.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(';')).join('\n');
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + text], { type: 'text/csv;charset=utf-8' })); a.download = `${(w.title || 'data').replace(/[^\w\- ]+/g, '').slice(0, 60)}.csv`; a.click(); URL.revokeObjectURL(a.href);
};
const isMoneyCol = (w, ci) => w.col_formats?.[ci] === 'money';
const cell = (w, v, ci) => (typeof v === 'number' ? val(v, w.col_formats?.[ci] || (ci > 0 ? w.format : 'number')) : v);
const mine = computed(() => saved.value.filter((d) => d.mine));
const shared = computed(() => saved.value.filter((d) => !d.mine));
onMounted(() => { const d = props.open && saved.value.find((x) => x.id === props.open); if (d) openSaved(d); });
</script>
<template>
  <Head title="AI Analitik" />
  <PageHead kicker="AI" title="AI Analitik" :sub="`Tulis kebutuhan Anda, AI menyusun dashboard dari data aplikasi sesuai hak akses Anda. Penyedia: ${provider}. Sisa kuota hari ini: ${left}.`" />
  <div v-if="!enabled" class="alert-box warn" style="margin-bottom:12px"><Icon name="alert" /><span>Fitur AI dinonaktifkan oleh administrator. Dashboard tersimpan tetap dapat dibuka.</span></div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 8"><div class="card-h"><h3>Permintaan dashboard</h3><span class="hint">Ctrl + Enter untuk membuat</span></div><div class="card-b stack">
      <textarea v-model="prompt" class="inp" rows="3" maxlength="1000" placeholder="Contoh: jumlah risiko per unit dan level, tren insiden per bulan 12 bulan terakhir, 10 action plan terlambat" aria-label="Permintaan dashboard" @keydown="onKey"></textarea>
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <button type="button" class="btn pri" :disabled="busy || !enabled" @click="generate(false)"><Icon name="spark" />{{ busy ? 'Memproses…' : 'Buat dashboard' }}</button>
        <button v-if="current" type="button" class="btn c-violet" :disabled="busy || !enabled" @click="generate(true)"><Icon name="plus" />Tambahkan ke dashboard ini</button>
      </div>
      <div><div class="hint" style="margin-bottom:6px">Contoh permintaan</div><div class="ai-chips"><button v-for="e in examples" :key="e" type="button" class="ai-chip sm" :disabled="busy || !enabled" @click="useExample(e)">{{ e }}</button></div></div>
      <p class="hint" style="margin:0">AI hanya menerima skema data (nama dataset dan kolom), bukan isi data. Perhitungan dilakukan aplikasi atas data yang boleh Anda lihat.</p>
    </div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Dashboard tersimpan</h3></div><div class="card-b stack" style="gap:6px">
      <div v-if="!saved.length" class="empty" style="padding:12px">Belum ada. Buat dashboard lalu simpan.</div>
      <div v-for="d in [...mine, ...shared]" :key="d.id" class="row between ai-saved" :class="{ on: current?.id === d.id }">
        <button type="button" class="btn ghost" style="flex:1;justify-content:flex-start;min-width:0" @click="openSaved(d)"><Icon name="chart" /><span style="overflow:hidden;text-overflow:ellipsis">{{ d.title }}</span></button>
        <span v-if="d.shared" class="pill" :title="d.mine ? 'Dibagikan ke organisasi' : `Dibagikan oleh ${d.owner}`"><Icon name="share" :size="12" />{{ d.mine ? 'dibagikan' : d.owner }}</span>
        <button v-if="d.mine" type="button" class="icon-btn c-pink" :aria-label="`Hapus ${d.title}`" @click="destroy(d)"><Icon name="trash" /></button>
      </div>
    </div></div>
  </div>

  <template v-if="current">
    <div class="card" style="margin-top:16px"><div class="card-b row between" style="gap:10px;flex-wrap:wrap">
      <input v-model="current.title" class="inp ai-title" maxlength="120" aria-label="Judul dashboard" :readonly="!current.mine">
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <label v-if="current.mine" class="row hint" style="gap:6px;cursor:pointer"><input v-model="current.shared" type="checkbox">Bagikan ke organisasi</label>
        <span v-else class="hint">Dibagikan oleh {{ current.owner }}</span>
        <button type="button" class="btn sm c-blue" :disabled="busy" @click="refresh"><Icon name="refresh" />Perbarui data</button>
        <button v-if="current.mine && current.id" type="button" class="btn sm c-green" @click="save(false)"><Icon name="save" />Simpan perubahan</button>
        <button type="button" class="btn sm" :class="current.id && current.mine ? '' : 'pri'" @click="save(true)"><Icon name="save" />{{ current.id ? 'Simpan sebagai baru' : 'Simpan dashboard' }}</button>
      </div>
    </div>
    <div v-if="summary" class="card-b ai-summary"><Icon name="spark" /><span><b>Ringkasan:</b> {{ summary }}</span></div></div>

    <div class="s-grid" style="margin-top:16px">
      <div v-for="(w, i) in current.widgets" :key="i" class="card ai-w" :style="`grid-column:span ${span(w)}`">
        <div class="card-h"><h3 :title="w.title">{{ w.title }}</h3>
          <span class="row ai-wtools">
            <select v-if="typeOptions(w).length > 1" class="sel" :value="w.spec.type" aria-label="Jenis grafik" @change="setType(w, $event.target.value)"><option v-for="t in typeOptions(w)" :key="t" :value="t">{{ TYPE_LABEL[t] }}</option></select>
            <button v-if="w.type !== 'kpi'" type="button" class="icon-btn" aria-label="Unduh CSV" title="Unduh CSV" @click="csv(w)"><Icon name="down" /></button>
            <button type="button" class="icon-btn" aria-label="Geser ke kiri" title="Geser" :disabled="i === 0" @click="move(i, -1)">‹</button>
            <button type="button" class="icon-btn" aria-label="Hapus widget" title="Hapus widget" @click="remove(i)"><Icon name="x" /></button>
          </span></div>
        <div class="card-b">
          <div v-if="w.loading" class="empty">Memuat…</div>
          <template v-else-if="w.type === 'kpi'"><div class="ai-kpi">{{ val(w.value, w.format) }}</div><div class="hint">{{ w.metric_label }} · {{ w.n }} data {{ (w.dataset_label || '').toLowerCase() }}</div></template>
          <div v-else-if="w.type === 'table'" class="tbl-wrap"><table class="tbl">
            <thead><tr><th v-for="(c, ci) in w.columns" :key="ci" :style="ci > 0 && typeof w.rows?.[0]?.[ci] === 'number' ? 'text-align:right' : ''">{{ c }}</th></tr></thead>
            <tbody><tr v-for="(r, ri) in w.rows" :key="ri"><td v-for="(v, ci) in r" :key="ci" :class="{ mono: ci === 0 && w.links }" :style="typeof v === 'number' ? 'text-align:right' : ''">
              <Link v-if="ci === 0 && w.links?.[ri]" :href="w.links[ri]">{{ v }}</Link><template v-else>{{ cell(w, v, ci) }}</template></td></tr>
              <tr v-if="!w.rows?.length"><td :colspan="w.columns?.length || 1" class="empty">Tidak ada data.</td></tr></tbody></table></div>
          <template v-else><Chart v-if="w.labels?.length" :option="option(w)" :height="height(w)" /><div v-else class="empty">Tidak ada data untuk kriteria ini.</div></template>
          <p v-if="w.insight && w.type !== 'kpi'" class="hint" style="margin:8px 0 0"><Icon name="spark" :size="12" /> {{ w.insight }}</p>
        </div>
      </div>
    </div>
  </template>
  <div v-else class="card" style="margin-top:16px"><div class="empty"><Icon name="chart" :size="28" /><p style="margin:8px 0 0">Tulis permintaan atau pilih contoh untuk membuat dashboard. Hasilnya dapat diubah jenis grafiknya, ditambah, disimpan, dan dibagikan.</p></div></div>
</template>
