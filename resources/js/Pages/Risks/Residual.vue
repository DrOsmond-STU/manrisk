<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Chart from '../../Components/Chart.vue';
import { fmt } from '../../lib/format';
import ScopeFilter from '../../Components/ScopeFilter.vue';
const props = defineProps({ periods: Array, risks: Array, filters: { type: Object, default: () => ({}) }, units: Array, categories: Array });
const top = props.risks.slice(0, 8);
const opt = { legend: { type: 'scroll', top: 0 }, xAxis: { type: 'category', data: props.periods.map(fmt.period) }, yAxis: { type: 'value', min: 0, max: 25 }, series: top.map((r) => ({ name: r.code, type: 'line', smooth: true, data: r.series })) };
const spark = (s) => s.map((v) => (v === null ? '·' : v)).join(' ');
</script>
<template>
  <Head title="Monitoring residual" />
  <PageHead kicker="Pemantauan" title="Monitoring risiko residual" sub="Pergerakan skor residual per bulan dari snapshot otomatis, dibandingkan terhadap target." />
  <ScopeFilter path="/risks/residual" :filters="filters" :units="units" :categories="categories" />
  <div class="card"><div class="card-h"><h3>Tren 12 bulan — 8 risiko tertinggi</h3></div><div class="card-b"><Chart :option="opt" height="300px" /></div></div>
  <div class="card"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Risiko</th><th>Unit</th><th class="num">Inheren</th><th class="num">Residual</th><th class="num">Target</th><th>Level</th><th>Tren</th><th>12 bulan</th></tr></thead><tbody>
    <tr v-for="r in risks" :key="r.id"><td><Link :href="`/risks/${r.id}`" class="code-link">{{ r.code }}</Link></td><td class="t-main wrap">{{ r.name }}</td><td class="t-sub"><Link :href="`/risks?unit_id=${r.unit_id}&status=active`">{{ r.unit }}</Link></td><td class="num mono">{{ r.inherent_score }}</td><td class="num mono"><b>{{ r.residual_score }}</b></td><td class="num mono">{{ r.target_score }}</td><td><Pill kind="level" :value="r.residual_level" /></td><td><Pill :value="r.trend" :tone="r.trend === 'up' ? 'bad' : r.trend === 'down' ? 'ok' : ''" /></td><td class="mono muted" style="font-size:11px;white-space:nowrap">{{ spark(r.series) }}</td></tr>
  </tbody></table></div></div>
</template>
