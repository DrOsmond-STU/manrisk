<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Kpi from '../../Components/Kpi.vue';
import Heatmap from '../../Components/Heatmap.vue';
import Chart from '../../Components/Chart.vue';
import Pill from '../../Components/Pill.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ kpi: Object, by_level: Object, heat: Object, by_category: Array, by_unit: Array, top_risks: Array, trend: Array, alerts: Array, upcoming: Array, kris: Array });
const user = usePage().props.auth.user;
const catOpt = { xAxis: { type: 'value' }, yAxis: { type: 'category', data: props.by_category.map((c) => c.name).reverse(), axisLabel: { width: 120, overflow: 'truncate' } }, series: [{ type: 'bar', data: props.by_category.map((c) => c.n).reverse(), itemStyle: { color: '#1268c4', borderRadius: [0, 6, 6, 0] }, barMaxWidth: 18 }] };
const trendOpt = { xAxis: { type: 'category', data: props.trend.map((t) => fmt.period(t.period)) }, yAxis: [{ type: 'value', name: 'Rata-rata skor', min: 0, max: 25 }], series: [{ type: 'line', smooth: true, data: props.trend.map((t) => t.avg), areaStyle: { opacity: .12 }, lineStyle: { width: 3 } }] };
const unitOpt = { xAxis: { type: 'category', data: props.by_unit.map((u) => u.name), axisLabel: { rotate: 25, fontSize: 10, width: 90, overflow: 'truncate' } }, yAxis: { type: 'value' }, series: [{ type: 'bar', data: props.by_unit.map((u) => u.n), itemStyle: { color: '#34a8e0', borderRadius: [6, 6, 0, 0] }, barMaxWidth: 28 }] };
</script>
<template>
  <Head title="Risk Dashboard" />
  <PageHead kicker="Dashboard" title="Risk Dashboard" :sub="`Ringkasan profil risiko ${user.unit_scoped ? 'unit kerja Anda' : 'organisasi'} per hari ini.`">
    <Link href="/risks/create" class="btn c-green" v-if="!user.read_only"><Icon name="plus" />Risiko baru</Link>
    <Link href="/reports" class="btn c-orange"><Icon name="file" />Laporan</Link>
  </PageHead>
  <div class="kpis">
    <Kpi label="Risiko aktif" :value="kpi.total" sub="belum ditutup" />
    <Kpi label="Tinggi & sangat tinggi" :value="kpi.high" level="vh" sub="residual ≥ 10" />
    <Kpi label="Kritis (≥ 20)" :value="kpi.critical" level="vh" sub="perlu keputusan manajemen" />
    <Kpi label="Action plan terlambat" :value="kpi.plans_overdue" :sub="`dari ${kpi.plans_total} rencana`" level="m" />
    <Kpi label="KRI melewati ambang" :value="kpi.kri_breach" level="h" sub="waspada/kritis" />
    <Kpi label="Insiden terbuka" :value="kpi.incidents_open" sub="belum ditutup" />
    <Kpi label="Kontrol lemah" :value="kpi.controls_weak" sub="efektivitas ≤ 2" />
    <Kpi label="Menunggu persetujuan" :value="kpi.pending_approvals" sub="seluruh organisasi" />
  </div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Peta risiko residual</h3><Link href="/risks/matrix" class="btn sm ghost c-blue">Matriks lengkap</Link></div><div class="card-b"><Heatmap :counts="heat" compact />
      <div class="row" style="margin-top:12px;gap:14px"><span v-for="(n, k) in by_level" :key="k" class="lv" :class="'lv-' + { low: 'l', medium: 'm', high: 'h', very_high: 'vh' }[k]"><i></i>{{ $page.props.labels.levels[k] }} <b>{{ n }}</b></span></div></div></div>
    <div class="card" style="grid-column:span 7"><div class="card-h"><h3>Tren rata-rata skor residual</h3><span class="sub">6 bulan terakhir (snapshot)</span></div><div class="card-b"><Chart :option="trendOpt" height="240px" /></div></div>
    <div class="card" style="grid-column:span 7"><div class="card-h"><h3>Risiko prioritas</h3><Link href="/risks?sort=residual_score&dir=desc" class="btn sm ghost c-indigo">Semua risiko</Link></div>
      <div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Risiko</th><th>Unit</th><th class="num">Skor</th><th>Level</th><th>Evaluasi</th><th>Tren</th></tr></thead><tbody>
        <tr v-for="r in top_risks" :key="r.id"><td><Link :href="`/risks/${r.id}`" class="code-link">{{ r.code }}</Link></td><td class="t-main wrap">{{ r.name }}</td><td class="t-sub">{{ r.unit }}</td><td class="num mono">{{ r.residual_score }}</td><td><Pill kind="level" :value="r.residual_level" /></td><td><Pill kind="evaluation" :value="r.evaluation" /></td><td><Pill :value="r.trend" :tone="r.trend === 'up' ? 'bad' : r.trend === 'down' ? 'ok' : ''" /></td></tr>
      </tbody></table></div></div>
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Peringatan dini</h3><Link href="/alerts" class="btn sm ghost c-orange">Semua</Link></div><div class="card-b stack">
      <div v-if="!alerts.length" class="empty">Tidak ada peringatan aktif.</div>
      <div v-for="a in alerts" :key="a.id" class="alert-box" :class="{ bad: a.severity === 'critical', warn: a.severity === 'warning', info: a.severity === 'info' }"><Icon :name="a.severity === 'critical' ? 'alert' : 'bell'" /><div><div style="font-weight:600">{{ a.title }}</div><div class="hint">{{ fmt.ago(a.created_at) }}</div></div></div>
    </div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Risiko per kategori</h3></div><div class="card-b"><Chart :option="catOpt" height="260px" /></div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Risiko per unit</h3></div><div class="card-b"><Chart :option="unitOpt" height="260px" /></div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Action plan mendesak</h3><Link href="/action-plans" class="btn sm ghost c-teal">Semua</Link></div><div class="card-b stack">
      <div v-if="!upcoming.length" class="empty">Tidak ada action plan aktif.</div>
      <div v-for="p in upcoming" :key="p.id" class="row between" style="font-size:13px"><div style="min-width:0"><b class="clamp">{{ p.title }}</b><div class="hint">{{ p.code }} · tenggat {{ fmt.date(p.due_date) }}</div></div><div class="prog"><div class="bar"><i :class="{ late: p.status === 'overdue', done: p.progress >= 100 }" :style="`width:${p.progress}%`"></i></div><span>{{ p.progress }}%</span></div></div>
    </div></div>
    <div class="card" style="grid-column:span 12"><div class="card-h"><h3>Key Risk Indicator</h3><Link href="/kris" class="btn sm ghost c-violet">Semua KRI</Link></div><div class="card-b kri-grid">
      <div v-for="k in kris" :key="k.id" class="kri"><div class="kh"><div><div class="mono muted" style="font-size:11px">{{ k.code }} · {{ k.risk?.code }}</div><div class="kn">{{ k.name }}</div></div><Pill :value="k.status" /></div><div class="kval">{{ fmt.num(k.last_value, k.decimals) }} <small>{{ k.unit }}</small></div><div class="th"><span>Waspada {{ fmt.num(k.threshold_warn, k.decimals) }}</span><span>Kritis {{ fmt.num(k.threshold_crit, k.decimals) }}</span></div></div>
    </div></div>
  </div>
</template>
