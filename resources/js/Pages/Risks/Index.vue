<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Pagination from '../../Components/Pagination.vue';
import Field from '../../Components/Field.vue';
const props = defineProps({ risks: Object, filters: Object, units: Array, categories: Array, owners: Array, objectives: Array, processes: { type: Array, default: () => [] }, can: Object });
const L = usePage().props.labels;
const f = reactive({ q: props.filters.q || '', unit_id: props.filters.unit_id || '', category_id: props.filters.category_id || '', level: props.filters.level || '', status: props.filters.status || '', evaluation: props.filters.evaluation || '', owner_id: props.filters.owner_id || '', objective_id: props.filters.objective_id || '', process_id: props.filters.process_id || '', trend: props.filters.trend || '', mode: props.filters.mode || '', l: props.filters.l || '', i: props.filters.i || '', no_controls: props.filters.no_controls || '', period: props.filters.period || '', ids: props.filters.ids || '', per_page: props.filters.per_page || '', sort: props.filters.sort || 'residual_score', dir: props.filters.dir || 'desc' });
const active = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null));
const exportUrl = (fmt) => `/risks/export?${new URLSearchParams({ ...active(), format: fmt })}`;
// Filter ganda (mis. dari dashboard "Tinggi & sangat tinggi") tetap dapat dipilih di dropdown
const levelOpts = { ...L.levels, 'high,very_high': 'Tinggi & sangat tinggi' };
const evalOpts = { ...L.evaluations, 'escalate,critical': 'Perlu eskalasi & kritis', 'treat,escalate,critical': 'Perlu penanganan ke atas' };
const procName = (id) => props.processes.find((p) => p.id == id)?.name || `#${id}`;
const scopeQs = () => new URLSearchParams(Object.fromEntries(Object.entries(active()).filter(([k]) => ['unit_id', 'category_id', 'level', 'status', 'evaluation', 'owner_id', 'objective_id', 'process_id', 'q'].includes(k)))).toString();
const modeLabel = { inherent: 'inheren', residual: 'residual', target: 'target' };
let t;
const apply = () => router.get('/risks', Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null)), { preserveState: true, replace: true });
watch(f, () => { clearTimeout(t); t = setTimeout(apply, 350); });
const sortBy = (c) => { if (f.sort === c) f.dir = f.dir === 'asc' ? 'desc' : 'asc'; else { f.sort = c; f.dir = c === 'name' || c === 'code' ? 'asc' : 'desc'; } };
const reset = () => Object.assign(f, { q: '', unit_id: '', category_id: '', level: '', status: '', evaluation: '', owner_id: '', objective_id: '', process_id: '', trend: '', mode: '', l: '', i: '', no_controls: '', period: '', ids: '' });
</script>
<template>
  <Head title="Risk Register" />
  <PageHead kicker="Manajemen Risiko" title="Risk Register" :sub="`${risks.total} risiko terdaftar · identifikasi, analisis, evaluasi, dan treatment dalam satu register.`">
    <Link v-if="can.create" href="/risks/create" class="btn c-green"><Icon name="plus" />Identifikasi risiko baru</Link>
    <Link :href="`/risks/matrix${scopeQs() ? '?' + scopeQs() : ''}`" class="btn c-violet"><Icon name="target" />Matriks</Link>
    <Link :href="`/reports?type=register${scopeQs() ? '&' + scopeQs() : ''}`" class="btn c-indigo"><Icon name="file" />Laporan dari filter ini</Link>
    <a :href="exportUrl('xlsx')" class="btn c-green"><Icon name="file" />Excel</a>
    <a :href="exportUrl('pdf')" class="btn c-red"><Icon name="file" />PDF</a>
    <Link v-if="can.import" href="/import/risk" class="btn c-teal"><Icon name="upload" />Impor Excel</Link>
  </PageHead>
  <div v-if="f.l && f.i || f.no_controls || f.trend || f.process_id || f.period || f.ids" class="row" style="gap:6px">
    <span v-if="f.l && f.i" class="pill run">Sel {{ modeLabel[f.mode || 'residual'] }} L{{ f.l }} × I{{ f.i }}</span>
    <span v-if="f.no_controls" class="pill warn">Risiko tanpa kontrol</span><span v-if="f.trend" class="pill run">Tren: {{ f.trend }}</span><span v-if="f.process_id" class="pill run">Proses: {{ procName(f.process_id) }}</span><span v-if="f.period" class="pill run">Tahun {{ f.period }}</span><span v-if="f.ids" class="pill run">{{ f.ids.split(',').length }} risiko hasil impor</span>
    <button type="button" class="btn sm ghost c-indigo" @click="Object.assign(f, { mode: '', l: '', i: '', no_controls: '', trend: '', process_id: '', period: '', ids: '' })">Hapus filter khusus</button>
  </div>
  <div class="card"><div class="card-b filters">
    <Field v-model="f.q" placeholder="Cari kode / nama / peristiwa" label="Cari" style="flex:1;min-width:200px" />
    <Field v-model="f.unit_id" type="select" label="Unit" :options="units" empty="Semua unit" />
    <Field v-model="f.category_id" type="select" label="Kategori" :options="categories" empty="Semua" />
    <Field v-model="f.level" type="select" label="Level" :options="levelOpts" empty="Semua" />
    <Field v-model="f.evaluation" type="select" label="Evaluasi" :options="evalOpts" empty="Semua" />
    <Field v-model="f.status" type="select" label="Status" :options="{ active: 'Aktif (belum ditutup)', ...L.risk_statuses }" empty="Semua" />
    <Field v-model="f.owner_id" type="select" label="Pemilik" :options="owners" empty="Semua" />
    <Field v-model="f.objective_id" type="select" label="Sasaran" :options="objectives.map((o) => ({ id: o.id, name: o.code }))" empty="Semua" />
    <button type="button" class="btn ghost c-indigo" @click="reset">Reset</button>
  </div></div>
  <div class="card"><div class="card-b flush tbl-wrap"><table class="tbl">
    <thead><tr><th class="sortable" :class="{ on: f.sort === 'code' }" @click="sortBy('code')">Kode</th><th class="sortable" :class="{ on: f.sort === 'name' }" @click="sortBy('name')">Risiko</th><th>Unit / Pemilik</th><th>Kategori</th><th class="num sortable" :class="{ on: f.sort === 'inherent_score' }" @click="sortBy('inherent_score')">Inheren</th><th class="num sortable" :class="{ on: f.sort === 'residual_score' }" @click="sortBy('residual_score')">Residual</th><th>Level</th><th>Evaluasi</th><th>Status</th><th class="num">AP / Ktrl / KRI</th></tr></thead>
    <tbody>
      <tr v-for="r in risks.data" :key="r.id" class="click" @click="router.visit(`/risks/${r.id}`)"><td><span class="code-link">{{ r.code }}</span></td><td class="wrap"><div class="t-main">{{ r.name }}</div><div class="t-sub clamp">{{ r.event }}</div></td><td class="t-sub">{{ r.unit?.name }}<br>{{ r.owner?.name }}</td><td class="t-sub">{{ r.category?.name }}</td><td class="num mono">{{ r.inherent_score }}</td><td class="num mono"><b>{{ r.residual_score }}</b> <span class="muted" style="font-size:11px">({{ r.residual_l }}×{{ r.residual_i }})</span></td><td><Pill kind="level" :value="r.residual_level" /></td><td><Pill kind="evaluation" :value="r.evaluation" /></td><td><Pill :value="r.status" /></td><td class="num mono">{{ r.action_plans_count }} / {{ r.controls_count }} / {{ r.kris_count }}</td></tr>
      <tr v-if="!risks.data.length"><td colspan="10"><div class="empty">Tidak ada risiko yang cocok dengan filter.</div></td></tr>
    </tbody></table></div><Pagination :data="risks" /></div>
</template>
