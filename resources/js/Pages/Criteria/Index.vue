<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import Pill from '../../Components/Pill.vue';
import { fmt, lvKey } from '../../lib/format';
const props = defineProps({ versions: Array, categories: Array, default_matrix: Object, can: Object });
const L = usePage().props.labels;
const tab = ref('criteria');
const active = computed(() => props.versions.find((v) => v.active) || props.versions[0]);
const view = ref(active.value);
const catModal = ref(false); const catItem = ref(null);
const catFields = computed(() => [{ key: 'name', label: 'Nama kategori', required: true }, { key: 'name_en', label: 'Nama (EN)' }, { key: 'appetite', label: 'Risk appetite (skor)', type: 'number', min: 1, max: 25, required: true, default: 6, hint: 'Skor residual di atas appetite → dipantau' }, { key: 'tolerance', label: 'Risk tolerance (skor)', type: 'number', min: 1, max: 25, required: true, default: 9, hint: 'Di atas tolerance → wajib ditangani' }, { key: 'description', label: 'Deskripsi', type: 'textarea', span: true }, { key: 'parent_id', label: 'Induk', type: 'select', options: props.categories.filter((c) => c.id !== catItem.value?.id) }, { key: 'sort', label: 'Urutan', type: 'number', default: 0 }, { key: 'active', label: 'Aktif', type: 'checkbox', default: true }]);
const newModal = ref(false);
const base = () => { const a = active.value; return { effective_from: new Date().toISOString().slice(0, 10), likelihood: a ? JSON.parse(JSON.stringify(a.likelihood)) : [1, 2, 3, 4, 5].map((v) => ({ v, label: '', desc: '' })), impact: a ? JSON.parse(JSON.stringify(a.impact)) : [1, 2, 3, 4, 5].map((v) => ({ v, label: '', dims: {} })), dimensions: a ? JSON.parse(JSON.stringify(a.dimensions)) : [{ key: 'fin', label: 'Finansial' }], matrix: a ? { ...a.matrix } : { ...props.default_matrix }, thresholds: a ? { ...a.thresholds } : { escalate: 16, critical: 20 }, activate: true }; };
const nf = useForm(base());
const openNew = () => { Object.assign(nf, base()); nf.clearErrors(); newModal.value = true; };
const cycle = (k) => { const order = ['low', 'medium', 'high', 'very_high']; nf.matrix[k] = order[(order.indexOf(nf.matrix[k]) + 1) % 4]; };
const addDim = () => nf.dimensions.push({ key: `d${nf.dimensions.length + 1}`, label: '' });
const saveNew = () => nf.post('/criteria', { onSuccess: () => (newModal.value = false) });
const activate = (v) => { if (confirm(`Aktifkan kriteria versi ${v.version}? Seluruh skor risiko akan dihitung ulang.`)) router.post(`/criteria/${v.id}/activate`); };
</script>
<template>
  <Head title="Kriteria & taksonomi" />
  <PageHead kicker="ISO 31000 §6.3.4" title="Kriteria risiko & taksonomi" sub="Skala kemungkinan/dampak, matriks 5×5, ambang eskalasi, serta kategori risiko dengan risk appetite dan tolerance. Kriteria diversikan; versi aktif dipakai untuk seluruh perhitungan.">
    <button v-if="can.write && tab === 'criteria'" type="button" class="btn c-green" @click="openNew"><Icon name="plus" />Versi kriteria baru</button>
    <button v-if="can.write && tab === 'taxonomy'" type="button" class="btn c-green" @click="catItem = null; catModal = true"><Icon name="plus" />Kategori</button>
  </PageHead>
  <div class="tabs"><button type="button" :class="{ on: tab === 'criteria' }" @click="tab = 'criteria'">Kriteria & matriks<span class="c">v{{ active?.version }}</span></button><button type="button" :class="{ on: tab === 'taxonomy' }" @click="tab = 'taxonomy'">Taksonomi & appetite<span class="c">{{ categories.length }}</span></button></div>
  <div v-if="tab === 'criteria' && view" class="s-grid">
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Versi kriteria</h3></div><div class="card-b stack">
      <div v-for="v in versions" :key="v.id" class="row between" style="padding:8px 10px;border:1px solid var(--line);border-radius:10px" :style="view?.id === v.id ? 'outline:2px solid var(--accent)' : ''"><div><b>Versi {{ v.version }}</b> <Pill v-if="v.active" value="active" /><div class="hint">berlaku {{ fmt.date(v.effective_from) }} · {{ v.creator?.name || 'sistem' }}</div></div><span class="row"><button type="button" class="btn sm ghost c-indigo" @click="view = v">Lihat</button><button v-if="can.write && !v.active" type="button" class="btn sm c-amber" @click="activate(v)">Aktifkan</button></span></div>
      <div class="alert-box info"><Icon name="info" /><span>Ambang: eskalasi ≥ <b>{{ view.thresholds?.escalate }}</b>, kritis ≥ <b>{{ view.thresholds?.critical }}</b>. Risiko dengan skor residual ≥ eskalasi wajib disetujui Management.</span></div>
    </div></div>
    <div class="card" style="grid-column:span 8"><div class="card-h"><h3>Matriks level (versi {{ view.version }})</h3></div><div class="card-b">
      <div class="hm"><span class="yt">Kemungkinan →</span><template v-for="l in [5, 4, 3, 2, 1]" :key="l"><span class="ax y"><b>{{ l }}</b><span class="axl">{{ view.likelihood?.[l - 1]?.label }}</span></span><div v-for="i in 5" :key="i" class="cell" :class="'lv-' + lvKey(view.matrix[`${l}-${i}`])"><b>{{ l * i }}</b><small>{{ L.levels[view.matrix[`${l}-${i}`]] }}</small></div></template><span></span><span class="ax x"></span><span v-for="i in 5" :key="'x' + i" class="ax x"><b>{{ i }}</b><span class="axl">{{ view.impact?.[i - 1]?.label }}</span></span><span class="xt">Dampak →</span></div>
    </div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Skala kemungkinan</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th class="num">Nilai</th><th>Label</th><th>Deskripsi</th></tr></thead><tbody><tr v-for="x in view.likelihood" :key="x.v"><td class="num mono">{{ x.v }}</td><td class="t-main">{{ x.label }} <span class="muted">{{ x.en }}</span></td><td class="fg2 wrap">{{ x.desc }}</td></tr></tbody></table></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Skala dampak</h3><span class="sub">{{ (view.dimensions || []).map((d) => d.label).join(' · ') }}</span></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th class="num">Nilai</th><th>Label</th><th v-for="d in view.dimensions" :key="d.key">{{ d.label }}</th></tr></thead><tbody><tr v-for="x in view.impact" :key="x.v"><td class="num mono">{{ x.v }}</td><td class="t-main">{{ x.label }}</td><td v-for="d in view.dimensions" :key="d.key" class="fg2 wrap" style="font-size:12px">{{ x.dims?.[d.key] }}</td></tr></tbody></table></div></div>
  </div>
  <div v-if="tab === 'taxonomy'" class="card"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kategori</th><th>Deskripsi</th><th class="num">Appetite</th><th class="num">Tolerance</th><th class="num">Risiko aktif</th><th>Status</th><th></th></tr></thead><tbody>
    <tr v-for="c in categories" :key="c.id"><td class="t-main">{{ c.name }}<div class="t-sub">{{ c.name_en }}</div></td><td class="fg2 wrap">{{ c.description }}</td><td class="num mono">{{ c.appetite }}</td><td class="num mono">{{ c.tolerance }}</td><td class="num">{{ c.risks_count }}</td><td><Pill :value="c.active ? 'active' : 'inactive'" /></td><td><span class="row" style="justify-content:flex-end"><button v-if="can.write" type="button" class="btn sm c-blue" @click="catItem = c; catModal = true">Ubah</button><ConfirmButton v-if="can.delete" :href="`/criteria/categories/${c.id}`" /></span></td></tr>
  </tbody></table></div></div>
  <CrudModal :show="catModal" :title="catItem ? 'Ubah kategori' : 'Tambah kategori'" :fields="catFields" :item="catItem" :url="catItem ? `/criteria/categories/${catItem.id}` : '/criteria/categories'" :method="catItem ? 'put' : 'post'" @close="catModal = false" />
  <Modal :show="newModal" title="Versi kriteria baru" wide @close="newModal = false">
    <div class="stack">
      <div v-if="Object.keys(nf.errors).length" class="alert-box bad"><Icon name="alert" /><span>{{ Object.values(nf.errors)[0] }}</span></div>
      <div class="form-grid cols-3"><Field v-model="nf.effective_from" type="date" label="Berlaku sejak" required /><Field v-model="nf.thresholds.escalate" type="number" label="Ambang eskalasi (skor)" min="2" max="25" required /><Field v-model="nf.thresholds.critical" type="number" label="Ambang kritis (skor)" min="2" max="25" required /></div>
      <h4 style="margin:0">Skala kemungkinan</h4><div v-for="x in nf.likelihood" :key="x.v" class="row" style="gap:8px"><span class="mono" style="width:20px">{{ x.v }}</span><Field v-model="x.label" placeholder="Label" style="flex:1" /><Field v-model="x.desc" placeholder="Deskripsi" style="flex:2" /></div>
      <h4 style="margin:0">Dimensi dampak <button type="button" class="btn sm ghost c-teal" @click="addDim">+ dimensi</button></h4><div class="row" style="gap:8px"><template v-for="(d, i) in nf.dimensions" :key="i"><Field v-model="d.key" placeholder="kunci" style="width:90px" /><Field v-model="d.label" placeholder="Label" style="flex:1" /></template></div>
      <h4 style="margin:0">Skala dampak</h4><div v-for="x in nf.impact" :key="x.v" class="row" style="gap:8px"><span class="mono" style="width:20px">{{ x.v }}</span><Field v-model="x.label" placeholder="Label" style="flex:1" /><Field v-for="d in nf.dimensions" :key="d.key" v-model="x.dims[d.key]" :placeholder="d.label" style="flex:1" /></div>
      <h4 style="margin:0">Matriks level (klik sel untuk mengubah)</h4>
      <div class="hm compact"><span class="yt">L</span><template v-for="l in [5, 4, 3, 2, 1]" :key="l"><span class="ax y"><b>{{ l }}</b></span><button v-for="i in 5" :key="i" type="button" class="cell" :class="'lv-' + lvKey(nf.matrix[`${l}-${i}`])" @click="cycle(`${l}-${i}`)"><b>{{ l * i }}</b></button></template><span></span><span class="ax x"></span><span v-for="i in 5" :key="'x' + i" class="ax x"><b>{{ i }}</b></span><span class="xt">I</span></div>
      <Field v-model="nf.activate" type="checkbox" label="Aktifkan versi ini sekarang (seluruh skor dihitung ulang)" />
    </div>
    <template #footer><button class="btn ghost c-indigo" @click="newModal = false">Batal</button><button class="btn c-green" :disabled="nf.processing" @click="saveNew">Simpan versi</button></template>
  </Modal>
</template>
