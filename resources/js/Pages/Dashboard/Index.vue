<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Kpi from '../../Components/Kpi.vue';
import Heatmap from '../../Components/Heatmap.vue';
import Chart from '../../Components/Chart.vue';
import Pill from '../../Components/Pill.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ filters: Object, units: Array, kpi: Object, metrics: Object, by_level: Object, heat: Object, heat_inherent: Object, by_category: Array, by_unit: Array, by_objective: Array, by_process: Array, top_risks: Array, emerging: Array, trend: Array, alerts: Array, upcoming: Array, kris: Array, recent: Array, ai_summary: String });
const page = usePage();
const user = page.props.auth.user;
const heatMode = ref('residual');
const unitId = ref(props.filters.unit_id || '');
const setUnit = () => router.get('/dashboard', unitId.value ? { unit_id: unitId.value } : {}, { preserveScroll: true });
const q = (extra) => `/risks?${new URLSearchParams({ status: 'active', ...(props.filters.unit_id ? { unit_id: props.filters.unit_id } : {}), ...extra })}`;
const bar = (rows, color, horizontal = true) => horizontal
  ? { xAxis: { type: 'value' }, yAxis: { type: 'category', data: rows.map((c) => c.name).reverse(), axisLabel: { width: 130, overflow: 'truncate' } }, series: [{ type: 'bar', data: rows.map((c) => c.n).reverse(), itemStyle: { color, borderRadius: [0, 6, 6, 0] }, barMaxWidth: 16 }] }
  : { xAxis: { type: 'category', data: rows.map((u) => u.name), axisLabel: { rotate: 25, fontSize: 10, width: 90, overflow: 'truncate' } }, yAxis: { type: 'value' }, series: [{ type: 'bar', data: rows.map((u) => u.n), itemStyle: { color, borderRadius: [6, 6, 0, 0] }, barMaxWidth: 28 }] };
const trendOpt = { xAxis: { type: 'category', data: props.trend.map((t) => fmt.period(t.period)) }, yAxis: [{ type: 'value', name: 'Rata-rata skor', min: 0, max: 25 }], series: [{ type: 'line', smooth: true, data: props.trend.map((t) => t.avg), areaStyle: { opacity: .12 }, lineStyle: { width: 3 } }] };
const j = props.metrics.journey;
const journeyOpt = { tooltip: { trigger: 'axis' }, xAxis: { type: 'category', data: ['Inheren', 'Setelah kontrol', 'Proyeksi mitigasi', 'Target'] }, yAxis: { type: 'value', min: 0, max: 25 }, series: [{ type: 'bar', data: [{ value: j.inherent, itemStyle: { color: '#d03b3b' } }, { value: j.residual, itemStyle: { color: '#ec835a' } }, { value: j.projected, itemStyle: { color: '#fab219' } }, { value: j.target, itemStyle: { color: '#0ca30c' } }], barMaxWidth: 42, label: { show: true, position: 'top' } }] };
// Drill-down grafik: batang horizontal dibalik urutannya, jadi indeks dipetakan ulang
const drill = (rows, key, horizontal = true) => (e) => { const r = rows[horizontal ? rows.length - 1 - e.index : e.index]; if (r?.id) router.visit(q({ [key]: r.id })); };
const unitQ = () => (props.filters.unit_id ? `&unit_id=${props.filters.unit_id}` : '');
const SUBJ_URL = { risk: (id) => `/risks/${id}`, action_plan: (id) => `/action-plans/${id}`, control: (id) => `/controls/${id}`, incident: (id) => `/incidents/${id}`, approval: (id) => `/approvals?id=${id}` };
const subjUrl = (a) => SUBJ_URL[a.subject_type]?.(a.subject_id);
const actLabel = { created: 'membuat', updated: 'mengubah', submitted: 'mengajukan', approval_approve: 'menyetujui', approval_reject: 'menolak', approval_revise: 'meminta revisi' };
const subjLabel = { risk: 'risiko', action_plan: 'action plan', control: 'kontrol', incident: 'insiden', kri: 'KRI', document: 'dokumen' };
</script>
<template>
  <Head title="Risk Dashboard" />
  <PageHead kicker="Dashboard" title="Risk Dashboard" :sub="`Ringkasan profil risiko ${user.unit_scoped ? 'unit kerja Anda' : 'organisasi'} per hari ini. Klik angka untuk melihat daftar sumbernya.`">
    <select v-model="unitId" class="sel sel-inline" aria-label="Filter unit" @change="setUnit"><option value="">Semua unit</option><option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option></select>
    <Link v-if="!user.read_only" :href="`/risks/create${filters.unit_id ? '?unit_id=' + filters.unit_id : ''}`" class="btn c-green"><Icon name="plus" />Risiko baru</Link>
    <Link :href="`/reports${filters.unit_id ? '?unit_id=' + filters.unit_id : ''}`" class="btn c-orange"><Icon name="file" />Laporan</Link>
  </PageHead>
  <div v-if="ai_summary" class="alert-box info"><Icon name="spark" /><div><b>Ringkasan</b> <span class="hint">(disusun otomatis, diperbarui tiap 15 menit)</span><div style="margin-top:2px">{{ ai_summary }}</div></div></div>
  <div class="kpis">
    <Link :href="q({})" class="plain"><Kpi label="Risiko aktif" :value="kpi.total" :sub="`${kpi.new_year} baru · ${kpi.closed_year} ditutup tahun ini`" /></Link>
    <Link :href="q({ level: 'high,very_high' })" class="plain"><Kpi label="Tinggi & sangat tinggi" :value="kpi.high" level="vh" sub="residual ≥ 10" /></Link>
    <Link :href="q({ evaluation: 'critical' })" class="plain"><Kpi label="Kritis (≥ 20)" :value="kpi.critical" level="vh" sub="perlu keputusan manajemen" /></Link>
    <Link :href="q({ status: 'treating' })" class="plain"><Kpi label="Dalam penanganan" :value="kpi.treating" :sub="`${kpi.above_target} di atas target`" level="h" /></Link>
    <Link :href="`/action-plans?status=overdue${unitQ()}`" class="plain"><Kpi label="Action plan terlambat" :value="kpi.plans_overdue" :sub="`dari ${kpi.plans_total} rencana aktif`" level="m" /></Link>
    <Link :href="`/action-plans${filters.unit_id ? '?unit_id=' + filters.unit_id : ''}`" class="plain"><Kpi label="Realisasi mitigasi" :value="`${metrics.realization}%`" sub="rata-rata progres action plan aktif" /></Link>
    <Link :href="q({ sort: 'inherent_score', dir: 'desc' })" class="plain"><Kpi label="Efektivitas mitigasi" :value="`${metrics.effectiveness}%`" sub="risiko turun ≥ 1 level inheren → residual" level="l" /></Link>
    <Link :href="q({ trend: 'up' })" class="plain"><Kpi label="Arah risiko" :value="`▲${kpi.up} ▼${kpi.down}`" :sub="`${kpi.flat} stabil dibanding periode lalu`" /></Link>
    <Link :href="`/kris?status=breach${unitQ()}`" class="plain"><Kpi label="KRI melewati ambang" :value="kpi.kri_breach" level="h" sub="waspada/kritis" /></Link>
    <Link :href="`/incidents?status=open${unitQ()}`" class="plain"><Kpi label="Insiden terbuka" :value="kpi.incidents_open" sub="belum ditutup" /></Link>
    <Link href="/controls?eff=weak" class="plain"><Kpi label="Kontrol lemah" :value="kpi.controls_weak" sub="efektivitas ≤ 2" /></Link>
    <Link :href="q({ no_controls: 1 })" class="plain"><Kpi label="Risiko tanpa kontrol" :value="kpi.no_controls" sub="kesenjangan pengendalian" /></Link>
    <Link href="/approvals" class="plain"><Kpi label="Menunggu persetujuan" :value="kpi.pending_approvals" sub="menunggu keputusan Anda" /></Link>
  </div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Peta risiko</h3><div class="seg"><button type="button" :class="{ on: heatMode === 'inherent' }" @click="heatMode = 'inherent'">Inheren</button><button type="button" :class="{ on: heatMode === 'residual' }" @click="heatMode = 'residual'">Residual</button></div></div><div class="card-b"><Heatmap :counts="heatMode === 'residual' ? heat : heat_inherent" compact @select="(k, l, i) => router.visit(q({ mode: heatMode, l, i }))" />
      <div class="row" style="margin-top:12px;gap:14px"><Link v-for="(n, k) in by_level" :key="k" :href="q({ level: k })" class="lv plain" :class="'lv-' + { low: 'l', medium: 'm', high: 'h', very_high: 'vh' }[k]"><i></i>{{ $page.props.labels.levels[k] }} <b>{{ n }}</b></Link></div><p class="hint">Klik sel untuk membuka register tersaring.</p></div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Perjalanan risiko</h3><span class="sub">rata-rata skor</span></div><div class="card-b"><Chart :option="journeyOpt" height="240px" /></div></div>
    <div class="card" style="grid-column:span 3"><div class="card-h"><h3>Tren residual</h3><Link href="/risks/residual" class="btn sm ghost c-indigo">Detail</Link></div><div class="card-b"><Chart :option="trendOpt" height="240px" /></div></div>
    <div class="card" style="grid-column:span 7"><div class="card-h"><h3>Risiko prioritas</h3><Link :href="q({ sort: 'residual_score', dir: 'desc' })" class="btn sm ghost c-indigo">Semua risiko</Link></div>
      <div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Risiko</th><th>Unit</th><th class="num">Inh</th><th class="num">Res</th><th>Level</th><th>Evaluasi</th><th>Tren</th></tr></thead><tbody>
        <tr v-for="r in top_risks" :key="r.id" class="click" @click="router.visit(`/risks/${r.id}`)"><td><span class="code-link">{{ r.code }}</span></td><td class="t-main wrap">{{ r.name }}</td><td class="t-sub"><Link :href="q({ unit_id: r.unit_id })" @click.stop>{{ r.unit }}</Link></td><td class="num mono">{{ r.inherent_score }}</td><td class="num mono"><b>{{ r.residual_score }}</b></td><td><Pill kind="level" :value="r.residual_level" /></td><td><Pill kind="evaluation" :value="r.evaluation" /></td><td><Pill :value="r.trend" :tone="r.trend === 'up' ? 'bad' : r.trend === 'down' ? 'ok' : ''" /></td></tr>
        <tr v-if="!top_risks.length"><td colspan="8"><div class="empty">Belum ada risiko aktif.</div></td></tr>
      </tbody></table></div></div>
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Emerging risks</h3><span class="sub">baru 30 hari / tren naik</span></div><div class="card-b stack">
      <div v-if="!emerging.length" class="empty">Tidak ada risiko baru atau yang meningkat.</div>
      <Link v-for="r in emerging" :key="r.id" :href="`/risks/${r.id}`" class="row between plain" style="font-size:13px"><span><span class="code-link">{{ r.code }}</span> {{ r.name }}</span><span class="row" style="gap:6px"><span v-if="r.new" class="pill run">baru</span><Pill v-if="r.trend === 'up'" value="up" tone="bad" /><Pill kind="level" :value="r.residual_level" /></span></Link>
    </div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Risiko per kategori</h3></div><div class="card-b"><Chart :option="bar(by_category, '#1268c4')" height="250px" clickable @select="drill(by_category, 'category_id')($event)" /></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Risiko per sasaran strategis</h3></div><div class="card-b"><Chart v-if="by_objective.length" :option="bar(by_objective, '#6a3fd4')" height="250px" clickable @select="drill(by_objective, 'objective_id')($event)" /><div v-else class="empty">Belum ada risiko yang dipetakan ke sasaran.</div></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Risiko per unit kerja</h3></div><div class="card-b"><Chart :option="bar(by_unit, '#34a8e0', false)" height="250px" clickable @select="drill(by_unit, 'unit_id', false)($event)" /></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Risiko per proses bisnis</h3></div><div class="card-b"><Chart v-if="by_process.length" :option="bar(by_process, '#0d8a84')" height="250px" clickable @select="drill(by_process, 'process_id')($event)" /><div v-else class="empty">Belum ada risiko yang dipetakan ke proses.</div></div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Peringatan dini</h3><Link href="/alerts" class="btn sm ghost c-orange">Semua</Link></div><div class="card-b stack">
      <div v-if="!alerts.length" class="empty">Tidak ada peringatan aktif.</div>
      <component :is="a.link ? Link : 'div'" v-for="a in alerts" :key="a.id" :href="a.link ? `/alerts/${a.id}/open` : undefined" class="alert-box plain" :class="{ bad: a.severity === 'critical', warn: a.severity === 'warning', info: a.severity === 'info' }"><Icon :name="a.severity === 'critical' ? 'alert' : 'bell'" /><div><div style="font-weight:600">{{ a.title }}</div><div class="hint">{{ fmt.ago(a.created_at) }}</div></div></component>
    </div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Status mitigasi</h3><Link :href="`/action-plans${filters.unit_id ? '?unit_id=' + filters.unit_id : ''}`" class="btn sm ghost c-teal">Semua</Link></div><div class="card-b stack">
      <div v-if="!upcoming.length" class="empty">Tidak ada action plan aktif.</div>
      <Link v-for="p in upcoming" :key="p.id" :href="`/action-plans/${p.id}`" class="row between plain" style="font-size:13px"><div style="min-width:0"><b class="clamp">{{ p.title }}</b><div class="hint">{{ p.code }} · tenggat {{ fmt.date(p.due_date) }}</div></div><div class="prog"><div class="bar"><i :class="{ late: p.status === 'overdue', done: p.progress >= 100 }" :style="`width:${p.progress}%`"></i></div><span>{{ p.progress }}%</span></div></Link>
    </div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Aktivitas terbaru</h3></div><div class="card-b"><div class="timeline">
      <div v-for="a in recent" :key="a.id" class="tl"><i :class="{ ok: a.action === 'approval_approve', bad: a.action === 'approval_reject' }"></i><div style="font-size:12.5px"><b>{{ a.user?.name || 'Sistem' }}</b> {{ actLabel[a.action] || a.action }} {{ subjLabel[a.subject_type] || a.subject_type }} <Link v-if="subjUrl(a)" :href="subjUrl(a)" class="mono">{{ a.subject_label }}</Link><span v-else class="mono">{{ a.subject_label }}</span><div class="hint">{{ fmt.ago(a.created_at) }}</div></div></div>
      <div v-if="!recent.length" class="empty">Belum ada aktivitas.</div></div></div></div>
    <div class="card" style="grid-column:span 12"><div class="card-h"><h3>Key Risk Indicator</h3><Link :href="`/kris${filters.unit_id ? '?unit_id=' + filters.unit_id : ''}`" class="btn sm ghost c-violet">Semua KRI</Link></div><div class="card-b kri-grid">
      <component :is="k.risk_id ? Link : 'div'" v-for="k in kris" :key="k.id" :href="k.risk_id ? `/kris?risk_id=${k.risk_id}` : undefined" class="kri plain"><div class="kh"><div><div class="mono muted" style="font-size:11px">{{ k.code }} · {{ k.risk?.code }}</div><div class="kn">{{ k.name }}</div></div><Pill :value="k.status" /></div><div class="kval">{{ fmt.num(k.last_value, k.decimals) }} <small>{{ k.unit }}</small></div><div class="th"><span>Waspada {{ fmt.num(k.threshold_warn, k.decimals) }}</span><span>Kritis {{ fmt.num(k.threshold_crit, k.decimals) }}</span></div></component>
      <div v-if="!kris.length" class="empty" style="grid-column:1/-1">Belum ada KRI.</div>
    </div></div>
  </div>
</template>
