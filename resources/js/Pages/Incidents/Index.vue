<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import Pill from '../../Components/Pill.vue';
import Kpi from '../../Components/Kpi.vue';
import Field from '../../Components/Field.vue';
import Pagination from '../../Components/Pagination.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ incidents: Object, filters: Object, filter_risk: Object, stats: Object, risks: Array, units: Array, can: Object });
const L = usePage().props.labels;
const modal = ref(false);
const f = reactive({ q: props.filters.q || '', status: props.filters.status || '', year: props.filters.year || '', risk_id: props.filters.risk_id || '', unit_id: props.filters.unit_id || '' });
let t; watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get('/incidents', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true }), 350); });
const fields = computed(() => [
  { key: 'title', label: 'Judul insiden', required: true, span: true }, { key: 'occurred_at', label: 'Waktu kejadian', type: 'datetime-local', required: true }, { key: 'location', label: 'Lokasi' },
  { key: 'risk_id', label: 'Risiko terkait', type: 'select', options: props.risks.map((r) => ({ id: r.id, name: `${r.code} · ${r.name}` })) }, { key: 'unit_id', label: 'Unit', type: 'select', options: props.units },
  { key: 'chronology', label: 'Kronologi', type: 'textarea', span: true }, { key: 'cause', label: 'Penyebab', type: 'textarea', rows: 2 }, { key: 'impact', label: 'Dampak', type: 'textarea', rows: 2 },
  { key: 'loss_amount', label: 'Kerugian (Rp)', type: 'number', min: 0, default: 0 }, { key: 'loss_type', label: 'Jenis kerugian', placeholder: 'finansial / operasional / reputasi' },
  { key: 'response', label: 'Respons awal', type: 'textarea', rows: 2 }, { key: 'corrective_action', label: 'Tindakan korektif', type: 'textarea', rows: 2 },
  { key: 'status', label: 'Status', type: 'select', options: L.incident_statuses, empty: '', required: true, default: 'reported' },
]);
</script>
<template>
  <Head title="Insiden" />
  <PageHead kicker="Pemantauan" title="Pelaporan insiden" sub="Catat kejadian risiko yang terwujud: kronologi, penyebab, dampak, kerugian, respons, dan tindakan korektif. Insiden otomatis memicu peringatan dan masuk loss database bila ada kerugian.">
    <button v-if="can.write" type="button" class="btn c-red" @click="modal = true"><Icon name="alert" />Laporkan insiden</button>
  </PageHead>
  <div class="kpis"><Kpi label="Total insiden" :value="stats.total" /><Kpi label="Terbuka" :value="stats.open" level="h" /><Kpi label="Kerugian tahun ini" :value="fmt.short(stats.loss_ytd)" level="vh" /><Kpi label="Ditutup" :value="stats.by_status?.closed || 0" level="l" /></div>
  <div class="card"><div class="card-b filters"><Field v-model="f.q" label="Cari" placeholder="kode / judul" style="flex:1" /><Field v-model="f.status" type="select" label="Status" :options="{ open: 'Terbuka (belum ditutup)', ...L.incident_statuses }" empty="Semua" /><Field v-model="f.risk_id" type="select" label="Risiko" :options="risks.map((r) => ({ id: r.id, name: r.code }))" empty="Semua" /><Field v-model="f.unit_id" type="select" label="Unit" :options="units" empty="Semua" /><Field v-model="f.year" type="number" label="Tahun" min="2000" max="2100" /></div>
    <div v-if="filter_risk || f.status === 'open'" class="card-b row" style="gap:6px"><span v-if="filter_risk" class="pill run">Risiko {{ filter_risk.code }} · {{ filter_risk.name }}</span><span v-if="f.status === 'open'" class="pill run">Belum ditutup</span><Link href="/incidents" class="btn sm ghost c-indigo">hapus filter</Link></div>
    <div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Waktu</th><th>Insiden</th><th>Risiko</th><th>Unit</th><th class="num">Kerugian</th><th>Status</th></tr></thead><tbody>
      <tr v-for="i in incidents.data" :key="i.id" class="click" @click="router.visit(`/incidents/${i.id}`)"><td class="code-link">{{ i.code }}</td><td style="white-space:nowrap">{{ fmt.datetime(i.occurred_at) }}</td><td class="t-main wrap">{{ i.title }}<div class="t-sub">{{ i.location }}</div></td><td class="t-sub"><Link v-if="i.risk" :href="`/risks/${i.risk.id}`" class="code-link" @click.stop>{{ i.risk.code }}</Link></td><td class="t-sub">{{ i.unit?.name }}</td><td class="num">{{ fmt.short(i.loss_amount) }}</td><td><Pill :value="i.status" /></td></tr>
      <tr v-if="!incidents.data.length"><td colspan="7"><div class="empty">Tidak ada insiden.</div></td></tr></tbody></table></div><Pagination :data="incidents" /></div>
  <CrudModal :show="modal" title="Laporkan insiden" :fields="fields" :item="null" url="/incidents" method="post" wide @close="modal = false" />
</template>
