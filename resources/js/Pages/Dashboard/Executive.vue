<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Heatmap from '../../Components/Heatmap.vue';
import PageHead from '../../Components/PageHead.vue';
import Kpi from '../../Components/Kpi.vue';
import Chart from '../../Components/Chart.vue';
import Pill from '../../Components/Pill.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ summary: Object, appetite: Array, trend: Array, top: Array, units: Array, metrics: Object, heat: Object, quarters: Array, movement: Object, appetite_statement: Object, ai_summary: String });
const heatMode = ref('residual');
const qOpt = { legend: { top: 0 }, tooltip: { trigger: 'axis' }, xAxis: { type: 'category', data: props.quarters.map((q) => q.label) }, yAxis: { type: 'value' }, series: [['very_high', 'Sangat Tinggi', '#d03b3b'], ['high', 'Tinggi', '#ec835a'], ['medium', 'Sedang', '#fab219'], ['low', 'Rendah', '#0ca30c']].map(([k, n, c]) => ({ name: n, type: 'bar', stack: 'lv', data: props.quarters.map((q) => q[k]), itemStyle: { color: c }, barMaxWidth: 40 })) };
const jm = props.metrics.journey;
const journeyOpt = { xAxis: { type: 'category', data: ['Inheren', 'Setelah kontrol', 'Proyeksi mitigasi', 'Target'] }, yAxis: { type: 'value', min: 0, max: 25 }, series: [{ type: 'bar', data: [{ value: jm.inherent, itemStyle: { color: '#d03b3b' } }, { value: jm.residual, itemStyle: { color: '#ec835a' } }, { value: jm.projected, itemStyle: { color: '#fab219' } }, { value: jm.target, itemStyle: { color: '#0ca30c' } }], barMaxWidth: 46, label: { show: true, position: 'top' } }] };
const trendOpt = { legend: { top: 0 }, xAxis: { type: 'category', data: props.trend.map((t) => fmt.period(t.period)) }, yAxis: [{ type: 'value', name: 'Jumlah' }, { type: 'value', name: 'Rata-rata', min: 0, max: 25 }], series: [{ name: 'Risiko tinggi+', type: 'bar', data: props.trend.map((t) => t.high), itemStyle: { color: '#ec835a', borderRadius: [4, 4, 0, 0] }, barMaxWidth: 22 }, { name: 'Rata-rata skor', type: 'line', yAxisIndex: 1, smooth: true, data: props.trend.map((t) => t.avg), lineStyle: { width: 3 } }] };
const pieLevels = ['very_high', 'high', 'medium', 'low']; // urutan irisan pieOpt
const pieOpt = { tooltip: { trigger: 'item' }, series: [{ type: 'pie', radius: ['45%', '72%'], data: [{ name: 'Sangat Tinggi', value: props.summary.very_high, itemStyle: { color: '#d03b3b' } }, { name: 'Tinggi', value: props.summary.high, itemStyle: { color: '#ec835a' } }, { name: 'Sedang', value: props.summary.medium, itemStyle: { color: '#fab219' } }, { name: 'Rendah', value: props.summary.low, itemStyle: { color: '#0ca30c' } }], label: { fontSize: 11 } }] };
</script>
<template>
  <Head title="Executive Dashboard" />
  <PageHead kicker="Dashboard" title="Executive Dashboard" sub="Pandangan pimpinan: profil risiko, risk appetite per kategori, tren, dan risiko utama organisasi.">
    <Link href="/reports" class="btn c-orange"><Icon name="file" />Laporan eksekutif</Link>
    <Link v-if="$page.props.auth.user.role !== 'auditor'" href="/ai" class="btn c-violet"><Icon name="spark" />Ringkasan AI</Link>
  </PageHead>
  <div v-if="ai_summary" class="alert-box info"><Icon name="spark" /><div><b>Ringkasan eksekutif</b> <span class="hint">(otomatis, 15 menit)</span><div>{{ ai_summary }}</div></div></div>
  <div v-if="appetite_statement?.appetite_statement" class="alert-box ok"><Icon name="flag" /><div><b>Pernyataan risk appetite:</b> {{ appetite_statement.appetite_statement }}</div></div>
  <div class="kpis">
    <Link href="/risks?status=active" class="plain"><Kpi label="Total risiko aktif" :value="summary.total" :sub="`rata-rata residual ${summary.avg} · inheren ${summary.avg_inherent}`" /></Link>
    <Link v-for="lv in [['very_high', 'Sangat tinggi', 'vh'], ['high', 'Tinggi', 'h'], ['medium', 'Sedang', 'm'], ['low', 'Rendah', 'l']]" :key="lv[0]" :href="`/risks?status=active&level=${lv[0]}`" class="plain"><Kpi :label="lv[1]" :value="summary[lv[0]]" :level="lv[2]" :sub="`inheren ${movement.inherent[lv[0]]}`" /></Link>
    <Link :href="`/risks?period=${new Date().getFullYear()}&sort=updated_at&dir=desc`" class="plain"><Kpi label="Risiko baru / ditutup" :value="`${movement.new} / ${movement.closed}`" sub="tahun berjalan" /></Link><Link href="/risks?status=treating" class="plain"><Kpi label="Dalam penanganan" :value="movement.treating" :sub="`${movement.above_target} melewati target`" /></Link><Link href="/risks?status=active&trend=up" class="plain"><Kpi label="Arah risiko" :value="`▲${movement.up} ▼${movement.down}`" :sub="`${movement.flat} stabil`" /></Link>
    <Link href="/action-plans" class="plain"><Kpi label="Realisasi / efektivitas mitigasi" :value="`${metrics.realization}% / ${metrics.effectiveness}%`" /></Link>
    <Link href="/risks?status=active&evaluation=escalate,critical" class="plain"><Kpi label="Perlu eskalasi" :value="summary.escalate" sub="≥ ambang eskalasi" level="vh" /></Link>
    <Link href="/action-plans?status=done" class="plain"><Kpi label="Action plan selesai" :value="`${summary.plan_done}/${summary.plan_total}`" :sub="fmt.pct(summary.plan_total ? summary.plan_done / summary.plan_total * 100 : 0) + ' penyelesaian'" /></Link>
    <Link href="/incidents/losses" class="plain"><Kpi label="Kerugian tahun berjalan" :value="fmt.short(summary.loss_ytd)" :sub="`${summary.incidents_ytd} insiden`" /></Link>
  </div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Heatmap</h3><div class="seg"><button type="button" :class="{ on: heatMode === 'inherent' }" @click="heatMode = 'inherent'">Inheren</button><button type="button" :class="{ on: heatMode === 'residual' }" @click="heatMode = 'residual'">Residual</button></div></div><div class="card-b"><Heatmap :counts="heat[heatMode]" compact @select="(k, l, i) => router.visit(`/risks?status=active&mode=${heatMode}&l=${l}&i=${i}`)" /></div></div>
    <div class="card" style="grid-column:span 3"><div class="card-h"><h3>Perjalanan risiko</h3></div><div class="card-b"><Chart :option="journeyOpt" height="250px" /></div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Distribusi level per triwulan</h3></div><div class="card-b"><Chart :option="qOpt" height="250px" /></div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Profil risiko residual</h3></div><div class="card-b"><Chart :option="pieOpt" height="250px" clickable @select="(e) => pieLevels[e.index] && router.visit(`/risks?status=active&level=${pieLevels[e.index]}`)" /></div></div>
    <div class="card" style="grid-column:span 8"><div class="card-h"><h3>Tren 12 bulan</h3><Link href="/risks/residual" class="btn sm ghost c-indigo">Monitoring residual</Link></div><div class="card-b"><Chart :option="trendOpt" height="250px" /></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Risk appetite per kategori</h3><span class="sub">skor maksimum vs appetite / tolerance</span></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kategori</th><th class="num">Risiko</th><th class="num">Maks</th><th class="num">Appetite</th><th class="num">Tolerance</th><th>Status</th></tr></thead><tbody>
      <tr v-for="c in appetite" :key="c.name" class="click" @click="router.visit(`/risks?status=active&category_id=${c.id}`)"><td class="t-main">{{ c.name }}</td><td class="num">{{ c.n }}</td><td class="num mono">{{ c.max }}</td><td class="num mono">{{ c.appetite }}</td><td class="num mono">{{ c.tolerance }}</td><td><span class="pill" :class="{ ok: c.status === 'ok', warn: c.status === 'watch', bad: c.status === 'breach' }">{{ { ok: 'Dalam appetite', watch: 'Melewati appetite', breach: 'Melewati tolerance' }[c.status] }}</span></td></tr>
    </tbody></table></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Profil per unit kerja</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Unit</th><th class="num">Risiko</th><th class="num">Tinggi+</th><th class="num">Rata-rata</th></tr></thead><tbody>
      <tr v-for="u in units" :key="u.name" class="click" @click="router.visit(`/risks?status=active&unit_id=${u.id}`)"><td class="t-main">{{ u.name }}</td><td class="num">{{ u.n }}</td><td class="num"><b :style="u.high ? 'color:var(--bad-ink)' : ''">{{ u.high }}</b></td><td class="num mono">{{ u.avg }}</td></tr>
    </tbody></table></div></div>
    <div class="card" style="grid-column:span 12"><div class="card-h"><h3>10 risiko utama</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Risiko</th><th>Unit</th><th>Kategori</th><th class="num">Skor</th><th>Level</th><th>Evaluasi</th><th>Treatment</th><th>Tren</th></tr></thead><tbody>
      <tr v-for="r in top" :key="r.id"><td><Link :href="`/risks/${r.id}`" class="code-link">{{ r.code }}</Link></td><td class="t-main wrap">{{ r.name }}</td><td class="t-sub">{{ r.unit }}</td><td class="t-sub">{{ r.category }}</td><td class="num mono">{{ r.residual_score }}</td><td><Pill kind="level" :value="r.residual_level" /></td><td><Pill kind="evaluation" :value="r.evaluation" /></td><td>{{ $page.props.labels.treatments[r.treatment] }}</td><td><Pill :value="r.trend" :tone="r.trend === 'up' ? 'bad' : r.trend === 'down' ? 'ok' : ''" /></td></tr>
    </tbody></table></div></div>
  </div>
</template>
