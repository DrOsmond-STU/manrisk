<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Pill from '../../Components/Pill.vue';
import Kpi from '../../Components/Kpi.vue';
import Chart from '../../Components/Chart.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ assessments: Array, parameters: Array, dimensions: Object, phases: Object, compliance: Object, latest: Object, can: Object });
const tab = ref('assessments');
const newModal = ref(false);
const paramModal = ref(false); const paramItem = ref(null);
const TYPES = { internal: 'Internal (tahunan)', independent: 'Independen (≥ 1× per 3 tahun)' };
const newFields = computed(() => [{ key: 'year', label: 'Tahun penilaian', type: 'number', required: true, default: new Date().getFullYear(), min: 2000, max: 2100 }, { key: 'type', label: 'Jenis penilaian', type: 'select', options: TYPES, empty: '', required: true, default: 'internal' }, { key: 'assessor', label: 'Penilai', hint: 'Tim internal atau nama pihak independen' }, { key: 'assessed_at', label: 'Tanggal penilaian', type: 'date' }, { key: 'note', label: 'Catatan', type: 'textarea', span: true }]);
const paramFields = computed(() => [{ key: 'dimension', label: 'Dimensi', type: 'select', options: props.dimensions, empty: '', required: true }, { key: 'code', label: 'Kode', required: true, maxlength: 20 }, { key: 'name', label: 'Parameter', required: true, span: true }, { key: 'guidance', label: 'Panduan / kriteria tiap skor', type: 'textarea', span: true }, { key: 'weight', label: 'Bobot', type: 'number', min: 0.01, step: 0.01, required: true, default: 1 }, { key: 'sort', label: 'Urutan', type: 'number', default: 0 }, { key: 'active', label: 'Aktif', type: 'checkbox', default: true }]);
const byDim = computed(() => Object.keys(props.dimensions).map((k) => ({ key: k, label: props.dimensions[k], items: props.parameters.filter((p) => p.dimension === k) })));
const phaseLabel = (k) => props.phases[k]?.label || '—';
const radar = computed(() => props.latest ? { tooltip: {}, radar: { indicator: Object.entries(props.dimensions).map(([k, n]) => ({ name: n.replace(' dan ', ' & '), max: 5 })), radius: '62%', axisName: { fontSize: 10 } }, series: [{ type: 'radar', data: [{ value: Object.keys(props.dimensions).map((k) => props.latest.dimensions[k] ?? 0), name: 'Skor dimensi', areaStyle: { opacity: 0.2 } }] }] } : null);
</script>
<template>
  <Head title="Risk Maturity Index" />
  <PageHead kicker="Tata Kelola · PER-2/MBU/03/2023" title="Risk Maturity Index (RMI)" sub="Penilaian tingkat kematangan penerapan manajemen risiko: internal setiap tahun dan independen paling sedikit sekali dalam tiga tahun, atas lima dimensi kematangan.">
    <button v-if="can.write && tab === 'assessments'" type="button" class="btn c-green" @click="newModal = true"><Icon name="plus" />Penilaian baru</button>
    <button v-if="can.param && tab === 'parameters'" type="button" class="btn c-green" @click="paramItem = null; paramModal = true"><Icon name="plus" />Parameter</button>
  </PageHead>
  <div class="kpis">
    <Kpi label="Indeks terakhir (final)" :value="latest?.index ?? '—'" :sub="latest ? phaseLabel(latest.phase) : 'belum ada penilaian final'" />
    <Kpi :label="`Penilaian internal ${compliance.year}`" :value="compliance.internal_this_year ? (compliance.internal_this_year.status === 'final' ? 'Final' : 'Draf') : 'Belum ada'" :level="compliance.internal_this_year?.status === 'final' ? 'l' : 'm'" sub="wajib setiap tahun" />
    <Kpi label="Penilaian independen terakhir" :value="compliance.last_independent ?? '—'" :level="compliance.independent_due ? 'vh' : 'l'" :sub="compliance.independent_due ? `jatuh tempo (≥ 1× per ${compliance.every} tahun)` : `berikutnya paling lambat ${compliance.last_independent + compliance.every}`" />
    <Kpi label="Parameter aktif" :value="parameters.filter((p) => p.active).length" :sub="`${Object.keys(dimensions).length} dimensi`" />
  </div>
  <div class="tabs"><button type="button" :class="{ on: tab === 'assessments' }" @click="tab = 'assessments'">Penilaian<span class="c">{{ assessments.length }}</span></button><button type="button" :class="{ on: tab === 'parameters' }" @click="tab = 'parameters'">Parameter<span class="c">{{ parameters.length }}</span></button></div>
  <div v-if="tab === 'assessments'" class="s-grid">
    <div class="card" style="grid-column:span 8"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Tahun</th><th>Jenis</th><th>Penilai</th><th class="num">Indeks</th><th>Fase</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="a in assessments" :key="a.id"><td class="t-main"><Link :href="`/rmi/${a.id}`" class="code-link">{{ a.year }}</Link></td><td>{{ TYPES[a.type] }}</td><td class="t-sub">{{ a.assessor || a.creator?.name || '—' }}<div class="hint">{{ fmt.date(a.assessed_at) }}</div></td><td class="num mono"><b>{{ a.score ?? '—' }}</b></td><td>{{ phaseLabel(a.phase) }}</td><td><Pill :value="a.status === 'final' ? 'Final' : 'Draf'" :tone="a.status === 'final' ? 'ok' : 'warn'" /></td><td><span class="row" style="justify-content:flex-end"><Link :href="`/rmi/${a.id}`" class="btn sm c-blue">{{ a.status === 'draft' && can.write ? 'Nilai' : 'Lihat' }}</Link><ConfirmButton v-if="can.delete && a.status === 'draft'" :href="`/rmi/${a.id}`" /></span></td></tr>
      <tr v-if="!assessments.length"><td colspan="7" class="hint">Belum ada penilaian. {{ parameters.length ? 'Buat penilaian baru.' : 'Mulai dengan menambahkan parameter penilaian di tab Parameter.' }}</td></tr>
    </tbody></table></div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Skor per dimensi</h3><span class="sub">penilaian final terakhir</span></div><div class="card-b"><Chart v-if="radar" :option="radar" height="260px" /><p v-else class="hint">Belum ada penilaian final.</p></div></div>
    <div class="card" style="grid-column:span 12"><div class="card-h"><h3>Fase kematangan</h3></div><div class="card-b"><div class="row" style="gap:10px;flex-wrap:wrap"><span v-for="(p, k) in phases" :key="k" class="pill">{{ p.label }} · ≤ {{ p.max.toFixed(1) }}</span></div><p class="hint" style="margin-top:8px">Batas fase dapat disesuaikan di konfigurasi server agar sama dengan lampiran petunjuk teknis RMI yang berlaku.</p></div></div>
  </div>
  <div v-if="tab === 'parameters'" class="stack">
    <div v-for="d in byDim" :key="d.key" class="card"><div class="card-h"><h3>{{ d.label }}</h3><span class="sub">{{ d.items.length }} parameter · bobot total {{ d.items.filter((p) => p.active).reduce((s, p) => s + p.weight, 0) }}</span></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Parameter</th><th class="num">Bobot</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="p in d.items" :key="p.id"><td class="mono">{{ p.code }}</td><td class="t-main wrap">{{ p.name }}<div v-if="p.guidance" class="t-sub" style="white-space:pre-line">{{ p.guidance }}</div></td><td class="num mono">{{ p.weight }}</td><td><Pill :value="p.active ? 'active' : 'inactive'" /></td><td><span class="row" style="justify-content:flex-end"><button v-if="can.param" type="button" class="btn sm c-blue" @click="paramItem = p; paramModal = true">Ubah</button><ConfirmButton v-if="can.delete" :href="`/rmi-parameters/${p.id}`" /></span></td></tr>
      <tr v-if="!d.items.length"><td colspan="5" class="hint">Belum ada parameter pada dimensi ini.</td></tr>
    </tbody></table></div></div>
    <p class="hint">Susun parameter sesuai lampiran petunjuk teknis penilaian RMI yang berlaku; bobot dihitung relatif terhadap seluruh parameter aktif.</p>
  </div>
  <CrudModal :show="newModal" title="Penilaian RMI baru" :fields="newFields" :item="null" url="/rmi" method="post" @close="newModal = false" />
  <CrudModal :show="paramModal" :title="paramItem ? 'Ubah parameter' : 'Tambah parameter'" :fields="paramFields" :item="paramItem" :url="paramItem ? `/rmi-parameters/${paramItem.id}` : '/rmi-parameters'" :method="paramItem ? 'put' : 'post'" @close="paramModal = false" />
</template>
