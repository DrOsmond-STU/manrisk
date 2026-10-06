<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Pill from '../../Components/Pill.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ scopes: Array, factors: Array, consultations: Array, units: Array, can: Object });
const modal = ref(null); const item = ref(null);
const open = (k, it) => { modal.value = k; item.value = it; };
const scopeOpts = computed(() => props.scopes.map((s) => ({ id: s.id, name: s.name })));
const F = computed(() => ({
  scope: [{ key: 'name', label: 'Nama ruang lingkup', required: true, span: true }, { key: 'period', label: 'Periode', placeholder: 'Jan–Des 2026' }, { key: 'unit_id', label: 'Unit (opsional)', type: 'select', options: props.units }, { key: 'objective', label: 'Tujuan penilaian', type: 'textarea', span: true }, { key: 'boundaries', label: 'Batasan', type: 'textarea', span: true }, { key: 'area', label: 'Area / proses yang dicakup', type: 'textarea', span: true }],
  factor: [{ key: 'scope_id', label: 'Ruang lingkup', type: 'select', options: scopeOpts.value }, { key: 'kind', label: 'Konteks', type: 'select', options: { internal: 'Internal', external: 'Eksternal' }, empty: '', required: true, default: 'internal' }, { key: 'factor', label: 'Faktor', required: true, maxlength: 120 }, { key: 'nature', label: 'Sifat', type: 'select', options: { strength: 'Kekuatan', weakness: 'Kelemahan', opportunity: 'Peluang', threat: 'Ancaman' }, empty: '', required: true, default: 'weakness' }, { key: 'condition', label: 'Kondisi / uraian', type: 'textarea', required: true, span: true }],
  consultation: [{ key: 'title', label: 'Judul kegiatan', required: true, span: true }, { key: 'scope_id', label: 'Ruang lingkup', type: 'select', options: scopeOpts.value }, { key: 'held_on', label: 'Tanggal', type: 'date', required: true }, { key: 'status', label: 'Status', type: 'select', options: { planned: 'Terjadwal', done: 'Selesai' }, empty: '', required: true, default: 'planned' }, { key: 'participants', label: 'Peserta', type: 'textarea', span: true }, { key: 'decisions', label: 'Keputusan / hasil', type: 'textarea', span: true }],
}));
const url = { scope: '/context/scopes', factor: '/context/factors', consultation: '/context/consultations' };
const swot = (nature) => props.factors.filter((f) => f.nature === nature);
</script>
<template>
  <Head title="Konteks & konsultasi" />
  <PageHead kicker="ISO 31000 §6.2–6.3" title="Ruang lingkup, konteks & konsultasi" sub="Tetapkan ruang lingkup penilaian, petakan konteks internal/eksternal (SWOT), dan catat komunikasi & konsultasi dengan pemangku kepentingan.">
    <button v-if="can.write" type="button" class="btn c-green" @click="open('scope', null)"><Icon name="plus" />Ruang lingkup</button>
    <button v-if="can.write" type="button" class="btn c-teal" @click="open('factor', null)"><Icon name="plus" />Faktor konteks</button>
    <button v-if="can.write" type="button" class="btn c-violet" @click="open('consultation', null)"><Icon name="plus" />Konsultasi</button>
  </PageHead>
  <div class="s-grid">
    <div v-for="s in scopes" :key="s.id" class="card" style="grid-column:span 6"><div class="card-h"><h3>{{ s.name }}</h3><span class="row"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open('scope', s)">Ubah</button><ConfirmButton v-if="can.delete" :href="`/context/scopes/${s.id}`" /></span></div><div class="card-b"><dl class="kv"><dt>Periode</dt><dd>{{ s.period || '—' }} <span v-if="s.unit" class="muted">· <Link :href="`/risks?unit_id=${s.unit.id}&status=active`">{{ s.unit.name }}</Link> (<Link :href="`/risks/matrix?unit_id=${s.unit.id}`">peta risiko</Link>)</span></dd><dt>Tujuan</dt><dd>{{ s.objective || '—' }}</dd><dt>Batasan</dt><dd>{{ s.boundaries || '—' }}</dd><dt>Area</dt><dd>{{ s.area || '—' }}</dd><dt>Faktor / konsultasi</dt><dd>{{ s.factors_count }} / {{ s.consultations_count }} · dibuat {{ s.creator?.name }}</dd></dl></div></div>
    <div v-if="!scopes.length" class="card" style="grid-column:1/-1"><div class="empty">Belum ada ruang lingkup penilaian.</div></div>
    <div v-for="q in [['strength', 'Kekuatan', 'ok'], ['weakness', 'Kelemahan', 'bad'], ['opportunity', 'Peluang', 'ok'], ['threat', 'Ancaman', 'bad']]" :key="q[0]" class="card" style="grid-column:span 6"><div class="card-h"><h3>{{ q[1] }}</h3><span class="sub">{{ swot(q[0]).length }} faktor</span></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Faktor</th><th>Kondisi</th><th>Konteks</th><th></th></tr></thead><tbody>
      <tr v-for="f in swot(q[0])" :key="f.id"><td class="t-main" style="white-space:nowrap">{{ f.factor }}<div v-if="f.scope" class="t-sub">{{ f.scope.name }}</div></td><td class="fg2 wrap">{{ f.condition }}</td><td><Pill :value="f.kind" /></td><td><span class="row" style="justify-content:flex-end"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open('factor', f)">Ubah</button><ConfirmButton v-if="can.delete" :href="`/context/factors/${f.id}`" /></span></td></tr>
      <tr v-if="!swot(q[0]).length"><td colspan="4"><div class="empty">Belum ada.</div></td></tr></tbody></table></div></div>
    <div class="card" style="grid-column:1/-1"><div class="card-h"><h3>Komunikasi & konsultasi</h3><span class="sub">ISO 31000 §6.2</span></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Tanggal</th><th>Kegiatan</th><th>Peserta</th><th>Keputusan</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="c in consultations" :key="c.id"><td style="white-space:nowrap">{{ fmt.date(c.held_on) }}</td><td class="t-main wrap">{{ c.title }}<div class="t-sub">{{ c.scope?.name }}</div></td><td class="fg2 wrap">{{ c.participants }}</td><td class="fg2 wrap">{{ c.decisions || '—' }}</td><td><Pill :value="c.status === 'done' ? 'done' : 'planned'" /></td><td><span class="row" style="justify-content:flex-end"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open('consultation', c)">Ubah</button><ConfirmButton v-if="can.delete" :href="`/context/consultations/${c.id}`" /></span></td></tr>
      <tr v-if="!consultations.length"><td colspan="6"><div class="empty">Belum ada konsultasi.</div></td></tr></tbody></table></div></div>
  </div>
  <CrudModal v-for="k in ['scope', 'factor', 'consultation']" :key="k" :show="modal === k" :title="(item ? 'Ubah ' : 'Tambah ') + { scope: 'ruang lingkup', factor: 'faktor konteks', consultation: 'konsultasi' }[k]" :fields="F[k]" :item="item" :url="item ? `${url[k]}/${item.id}` : url[k]" :method="item ? 'put' : 'post'" @close="modal = null" />
</template>
