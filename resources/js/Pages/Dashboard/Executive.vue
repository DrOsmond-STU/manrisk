<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Kpi from '../../Components/Kpi.vue';
import Chart from '../../Components/Chart.vue';
import Pill from '../../Components/Pill.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ summary: Object, appetite: Array, trend: Array, top: Array, units: Array });
const trendOpt = { legend: { top: 0 }, xAxis: { type: 'category', data: props.trend.map((t) => fmt.period(t.period)) }, yAxis: [{ type: 'value', name: 'Jumlah' }, { type: 'value', name: 'Rata-rata', min: 0, max: 25 }], series: [{ name: 'Risiko tinggi+', type: 'bar', data: props.trend.map((t) => t.high), itemStyle: { color: '#ec835a', borderRadius: [4, 4, 0, 0] }, barMaxWidth: 22 }, { name: 'Rata-rata skor', type: 'line', yAxisIndex: 1, smooth: true, data: props.trend.map((t) => t.avg), lineStyle: { width: 3 } }] };
const pieOpt = { tooltip: { trigger: 'item' }, series: [{ type: 'pie', radius: ['45%', '72%'], data: [{ name: 'Sangat Tinggi', value: props.summary.very_high, itemStyle: { color: '#d03b3b' } }, { name: 'Tinggi', value: props.summary.high, itemStyle: { color: '#ec835a' } }, { name: 'Sedang', value: props.summary.medium, itemStyle: { color: '#fab219' } }, { name: 'Rendah', value: props.summary.low, itemStyle: { color: '#0ca30c' } }], label: { fontSize: 11 } }] };
</script>
<template>
  <Head title="Executive Dashboard" />
  <PageHead kicker="Dashboard" title="Executive Dashboard" sub="Pandangan pimpinan: profil risiko, risk appetite per kategori, tren, dan risiko utama organisasi.">
    <Link href="/reports" class="btn c-orange"><Icon name="file" />Laporan eksekutif</Link>
    <Link href="/ai" class="btn c-violet"><Icon name="spark" />Ringkasan AI</Link>
  </PageHead>
  <div class="kpis">
    <Kpi label="Total risiko aktif" :value="summary.total" :sub="`rata-rata residual ${summary.avg} · inheren ${summary.avg_inherent}`" />
    <Kpi label="Sangat tinggi" :value="summary.very_high" level="vh" /><Kpi label="Tinggi" :value="summary.high" level="h" /><Kpi label="Sedang" :value="summary.medium" level="m" /><Kpi label="Rendah" :value="summary.low" level="l" />
    <Kpi label="Perlu eskalasi" :value="summary.escalate" sub="≥ ambang eskalasi" level="vh" />
    <Kpi label="Action plan selesai" :value="`${summary.plan_done}/${summary.plan_total}`" :sub="fmt.pct(summary.plan_total ? summary.plan_done / summary.plan_total * 100 : 0) + ' penyelesaian'" />
    <Kpi label="Kerugian tahun berjalan" :value="fmt.short(summary.loss_ytd)" :sub="`${summary.incidents_ytd} insiden`" />
  </div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Profil risiko residual</h3></div><div class="card-b"><Chart :option="pieOpt" height="250px" /></div></div>
    <div class="card" style="grid-column:span 8"><div class="card-h"><h3>Tren 12 bulan</h3><span class="sub">risiko tinggi+ dan rata-rata skor</span></div><div class="card-b"><Chart :option="trendOpt" height="250px" /></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Risk appetite per kategori</h3><span class="sub">skor maksimum vs appetite / tolerance</span></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kategori</th><th class="num">Risiko</th><th class="num">Maks</th><th class="num">Appetite</th><th class="num">Tolerance</th><th>Status</th></tr></thead><tbody>
      <tr v-for="c in appetite" :key="c.name"><td class="t-main">{{ c.name }}</td><td class="num">{{ c.n }}</td><td class="num mono">{{ c.max }}</td><td class="num mono">{{ c.appetite }}</td><td class="num mono">{{ c.tolerance }}</td><td><span class="pill" :class="{ ok: c.status === 'ok', warn: c.status === 'watch', bad: c.status === 'breach' }">{{ { ok: 'Dalam appetite', watch: 'Melewati appetite', breach: 'Melewati tolerance' }[c.status] }}</span></td></tr>
    </tbody></table></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Profil per unit kerja</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Unit</th><th class="num">Risiko</th><th class="num">Tinggi+</th><th class="num">Rata-rata</th></tr></thead><tbody>
      <tr v-for="u in units" :key="u.name"><td class="t-main">{{ u.name }}</td><td class="num">{{ u.n }}</td><td class="num"><b :style="u.high ? 'color:var(--bad-ink)' : ''">{{ u.high }}</b></td><td class="num mono">{{ u.avg }}</td></tr>
    </tbody></table></div></div>
    <div class="card" style="grid-column:span 12"><div class="card-h"><h3>10 risiko utama</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Risiko</th><th>Unit</th><th>Kategori</th><th class="num">Skor</th><th>Level</th><th>Evaluasi</th><th>Treatment</th><th>Tren</th></tr></thead><tbody>
      <tr v-for="r in top" :key="r.id"><td><Link :href="`/risks/${r.id}`" class="code-link">{{ r.code }}</Link></td><td class="t-main wrap">{{ r.name }}</td><td class="t-sub">{{ r.unit }}</td><td class="t-sub">{{ r.category }}</td><td class="num mono">{{ r.residual_score }}</td><td><Pill kind="level" :value="r.residual_level" /></td><td><Pill kind="evaluation" :value="r.evaluation" /></td><td>{{ $page.props.labels.treatments[r.treatment] }}</td><td><Pill :value="r.trend" :tone="r.trend === 'up' ? 'bad' : r.trend === 'down' ? 'ok' : ''" /></td></tr>
    </tbody></table></div></div>
  </div>
</template>
