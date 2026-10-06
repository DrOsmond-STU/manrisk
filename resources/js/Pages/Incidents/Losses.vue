<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Chart from '../../Components/Chart.vue';
import Kpi from '../../Components/Kpi.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ losses: Array, by_year: Array, by_category: Array, categories: Array, incidents: Array, can: Object });
const modal = ref(false); const item = ref(null);
const fields = computed(() => [{ key: 'year', label: 'Tahun', type: 'number', min: 2000, max: 2100, required: true, default: new Date().getFullYear() }, { key: 'amount', label: 'Nilai kerugian (Rp)', type: 'number', min: 0, required: true }, { key: 'risk_name', label: 'Risiko', required: true, span: true }, { key: 'event', label: 'Peristiwa', required: true, span: true }, { key: 'category_id', label: 'Kategori', type: 'select', options: props.categories }, { key: 'incident_id', label: 'Insiden', type: 'select', options: props.incidents.map((i) => ({ id: i.id, name: `${i.code} · ${i.title}` })) }, { key: 'description', label: 'Keterangan', type: 'textarea', span: true }]);
const yearOpt = { xAxis: { type: 'category', data: props.by_year.map((y) => y.year) }, yAxis: { type: 'value', axisLabel: { formatter: (v) => fmt.short(v) } }, series: [{ type: 'bar', data: props.by_year.map((y) => y.total), itemStyle: { color: '#d03b3b', borderRadius: [6, 6, 0, 0] }, barMaxWidth: 40 }], tooltip: { trigger: 'axis', valueFormatter: (v) => fmt.money(v) } };
const catOpt = { tooltip: { trigger: 'item', valueFormatter: (v) => fmt.money(v) }, series: [{ type: 'pie', radius: ['40%', '70%'], data: props.by_category.map((c) => ({ name: c.name, value: c.total })), label: { fontSize: 11 } }] };
const total = props.losses.reduce((a, l) => a + Number(l.amount), 0);
</script>
<template>
  <Head title="Loss event database" />
  <PageHead kicker="Pemantauan" title="Loss event database" sub="Basis data kerugian historis sebagai dasar kalibrasi skala dampak dan analisis tren kerugian per kategori.">
    <button v-if="can.write" type="button" class="btn c-green" @click="item = null; modal = true"><Icon name="plus" />Tambah kerugian</button>
  </PageHead>
  <div class="kpis"><Kpi label="Total kerugian tercatat" :value="fmt.short(total)" /><Kpi label="Jumlah peristiwa" :value="losses.length" /><Kpi label="Tahun berjalan" :value="fmt.short(by_year.find((y) => y.year == new Date().getFullYear())?.total || 0)" level="vh" /></div>
  <div class="s-grid"><div class="card" style="grid-column:span 7"><div class="card-h"><h3>Kerugian per tahun</h3></div><div class="card-b"><Chart :option="yearOpt" height="240px" /></div></div><div class="card" style="grid-column:span 5"><div class="card-h"><h3>Per kategori</h3></div><div class="card-b"><Chart :option="catOpt" height="240px" /></div></div>
    <div class="card" style="grid-column:1/-1"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Tahun</th><th>Risiko</th><th>Peristiwa</th><th>Kategori</th><th>Insiden</th><th>Risiko</th><th class="num">Kerugian</th><th></th></tr></thead><tbody>
      <tr v-for="l in losses" :key="l.id"><td class="mono">{{ l.year }}</td><td class="t-main wrap">{{ l.risk_name }}</td><td class="fg2 wrap">{{ l.event }}<div class="t-sub">{{ l.description }}</div></td><td class="t-sub">{{ l.category?.name }}</td><td><Link v-if="l.incident" :href="`/incidents/${l.incident.id}`" class="code-link">{{ l.incident.code }}</Link></td><td><Link v-if="l.incident?.risk" :href="`/risks/${l.incident.risk.id}`" class="code-link">{{ l.incident.risk.code }}</Link></td><td class="num"><b>{{ fmt.money(l.amount) }}</b></td><td><span class="row" style="justify-content:flex-end"><button v-if="can.write" type="button" class="btn sm c-blue" @click="item = l; modal = true">Ubah</button><ConfirmButton v-if="can.delete" :href="`/incidents/losses/${l.id}`" /></span></td></tr>
      <tr v-if="!losses.length"><td colspan="8"><div class="empty">Belum ada data kerugian.</div></td></tr></tbody></table></div></div></div>
  <CrudModal :show="modal" :title="item ? 'Ubah kerugian' : 'Tambah kerugian'" :fields="fields" :item="item" :url="item ? `/incidents/losses/${item.id}` : '/incidents/losses'" :method="item ? 'put' : 'post'" @close="modal = false" />
</template>
