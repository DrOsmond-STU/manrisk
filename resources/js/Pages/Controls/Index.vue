<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Pill from '../../Components/Pill.vue';
import Kpi from '../../Components/Kpi.vue';
import Field from '../../Components/Field.vue';
import Chart from '../../Components/Chart.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ controls: Array, filters: Object, stats: Object, users: Array, units: Array, risks: Array, can: Object });
const L = usePage().props.labels;
const modal = ref(false); const item = ref(null); const assess = ref(null);
const f = reactive({ q: props.filters.q || '', type: props.filters.type || '', eff: props.filters.eff || '' });
let t; watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get('/controls', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true }), 350); });
const fields = computed(() => [
  { key: 'name', label: 'Nama kontrol', required: true, span: true }, { key: 'objective', label: 'Tujuan kontrol', type: 'textarea', span: true, rows: 2 },
  { key: 'type', label: 'Tipe', type: 'select', options: { preventive: 'Preventif', detective: 'Detektif', corrective: 'Korektif' }, empty: '', required: true, default: 'preventive' },
  { key: 'mode', label: 'Mode', type: 'select', options: { manual: 'Manual', automated: 'Otomatis' }, empty: '', required: true, default: 'manual' },
  { key: 'frequency', label: 'Frekuensi', type: 'select', options: { daily: 'Harian', weekly: 'Mingguan', monthly: 'Bulanan', quarterly: 'Triwulanan', semester: 'Semesteran', annual: 'Tahunan', event: 'Per kejadian' }, empty: '', required: true, default: 'monthly' },
  { key: 'owner_id', label: 'Pemilik kontrol', type: 'select', options: props.users }, { key: 'unit_id', label: 'Unit', type: 'select', options: props.units },
  { key: 'description', label: 'Deskripsi pelaksanaan', type: 'textarea', span: true, rows: 2 }, { key: 'active', label: 'Aktif', type: 'checkbox', default: true },
]);
const open = (c) => { item.value = c ? { ...c, risk_ids: c.risks.map((r) => r.id) } : null; modal.value = true; };
const typeOpt = { tooltip: { trigger: 'item' }, series: [{ type: 'pie', radius: ['45%', '70%'], data: Object.entries(props.stats.by_type || {}).map(([k, v]) => ({ name: { preventive: 'Preventif', detective: 'Detektif', corrective: 'Korektif' }[k] || k, value: v })), label: { fontSize: 11 } }] };
const effLabel = (v) => (v ? L.effectiveness[v] : '—');
const effTone = (v) => (!v ? '' : v >= 3 ? 'ok' : v === 2 ? 'warn' : 'bad');
</script>
<template>
  <Head title="Kontrol & efektivitas" />
  <PageHead kicker="Penanganan & Kontrol" title="Manajemen kontrol & efektivitas" sub="Daftar kontrol, kaitannya dengan risiko, dan hasil pengujian efektivitas desain/operasi (1 Tidak Efektif – 4 Sangat Efektif).">
    <button v-if="can.write" type="button" class="btn c-green" @click="open(null)"><Icon name="plus" />Kontrol baru</button>
  </PageHead>
  <div class="kpis"><Kpi label="Kontrol aktif" :value="stats.total" /><Kpi label="Efektif (≥ 3)" :value="stats.effective" level="l" /><Kpi label="Lemah (≤ 2)" :value="stats.weak" level="vh" /><Kpi label="Belum diuji" :value="stats.untested" level="m" /><Kpi label="Jatuh tempo pengujian" :value="stats.due" level="h" /></div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 9"><div class="card-b filters"><Field v-model="f.q" label="Cari" placeholder="kode / nama" style="flex:1" /><Field v-model="f.type" type="select" label="Tipe" :options="{ preventive: 'Preventif', detective: 'Detektif', corrective: 'Korektif' }" empty="Semua" /><Field v-model="f.eff" type="select" label="Efektivitas" :options="{ weak: 'Lemah saja' }" empty="Semua" /></div>
      <div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Kontrol</th><th>Tipe</th><th>Pemilik</th><th>Desain</th><th>Operasi</th><th>Uji berikut</th><th>Risiko</th><th></th></tr></thead><tbody>
        <tr v-for="c in controls" :key="c.id" class="click" @click="router.visit(`/controls/${c.id}`)"><td class="code-link">{{ c.code }}</td><td class="t-main wrap">{{ c.name }}<div class="t-sub">{{ c.frequency }} · {{ c.mode }}</div></td><td><Pill :value="c.type" /></td><td class="t-sub">{{ c.owner?.name }}</td><td><Pill :value="effLabel(c.design_eff)" :tone="effTone(c.design_eff)" /></td><td><Pill :value="effLabel(c.operating_eff)" :tone="effTone(c.operating_eff)" /></td><td :style="c.next_test_at && new Date(c.next_test_at) < new Date() ? 'color:var(--bad-ink);font-weight:600' : ''">{{ fmt.date(c.next_test_at) }}</td><td class="t-sub">{{ c.risks.map((r) => r.code).join(', ') || '—' }}</td><td @click.stop><span class="row" style="justify-content:flex-end"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open(c)">Ubah</button><ConfirmButton v-if="can.delete" :href="`/controls/${c.id}`" /></span></td></tr>
        <tr v-if="!controls.length"><td colspan="9"><div class="empty">Tidak ada kontrol.</div></td></tr></tbody></table></div></div>
    <div class="card" style="grid-column:span 3"><div class="card-h"><h3>Komposisi tipe</h3></div><div class="card-b"><Chart :option="typeOpt" height="220px" /></div></div>
  </div>
  <CrudModal :show="modal" :title="item ? 'Ubah kontrol' : 'Kontrol baru'" :fields="[...fields, { key: 'risk_ids', label: 'Risiko yang dikendalikan', type: 'multi', options: risks }]" :item="item" :url="item ? `/controls/${item.id}` : '/controls'" :method="item ? 'put' : 'post'" wide @close="modal = false" />
</template>
