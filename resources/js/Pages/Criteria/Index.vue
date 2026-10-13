<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import LocalPager from '../../Components/LocalPager.vue';
import { usePager } from '../../lib/pager';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import Pill from '../../Components/Pill.vue';
import { fmt, lvKey } from '../../lib/format';
const props = defineProps({ versions: Array, categories: Array, default_matrix: Object, default_matrices: Object, schemes: Object, tiers: Object, appetite: Object, can: Object });
const lvNames = (v) => props.schemes[v?.scheme || 'standard']?.levels || L.levels;
const L = usePage().props.labels;
const tab = ref('criteria');
const verPager = usePager(() => props.versions, 6);
const catPager = usePager(() => props.categories, 15);
const active = computed(() => props.versions.find((v) => v.active) || props.versions[0]);
const view = ref(active.value);
const catModal = ref(false); const catItem = ref(null);
const parentOpts = computed(() => props.categories.filter((c) => c.depth < 3 && c.id !== catItem.value?.id && !(catItem.value && c.path.startsWith(catItem.value.path + ' › '))).map((c) => ({ id: c.id, name: `${'— '.repeat(c.depth - 1)}${c.name}` })));
const catFields = computed(() => [{ key: 'name', label: 'Nama', required: true }, { key: 'parent_id', label: 'Induk (kosong = T1 Tema Risiko)', type: 'select', options: parentOpts.value, hint: 'Pilih T1 untuk membuat T2, atau T2 untuk membuat T3' }, { key: 'bumn_code', label: 'Kode Taksonomi Risiko Portofolio BUMN', hint: 'Pemetaan untuk agregasi ke taksonomi Kementerian BUMN' }, { key: 'name_en', label: 'Nama (EN)' }, { key: 'appetite', label: 'Risk appetite (skor)', type: 'number', min: 1, max: 25, required: true, default: 6, hint: 'Skor residual di atas appetite → dipantau' }, { key: 'tolerance', label: 'Risk tolerance (skor)', type: 'number', min: 1, max: 25, required: true, default: 9, hint: 'Di atas tolerance → wajib ditangani' }, { key: 'risk_limit', label: 'Risk limit (Rp)', type: 'number', min: 0, hint: 'Batas total eksposur residual kategori ini' }, { key: 'description', label: 'Deskripsi', type: 'textarea', span: true }, { key: 'sort', label: 'Urutan', type: 'number', default: 0 }, { key: 'active', label: 'Aktif', type: 'checkbox', default: true }]);
const newModal = ref(false);
const base = () => { const a = active.value; return { effective_from: new Date().toISOString().slice(0, 10), likelihood: a ? JSON.parse(JSON.stringify(a.likelihood)) : [1, 2, 3, 4, 5].map((v) => ({ v, label: '', desc: '' })), impact: a ? JSON.parse(JSON.stringify(a.impact)) : [1, 2, 3, 4, 5].map((v) => ({ v, label: '', dims: {} })), dimensions: a ? JSON.parse(JSON.stringify(a.dimensions)) : [{ key: 'fin', label: 'Finansial' }], scheme: a?.scheme || 'standard', matrix: a ? { ...a.matrix } : { ...props.default_matrix }, thresholds: a ? { ...a.thresholds } : { escalate: 16, critical: 20 }, activate: true }; };
const nf = useForm(base());
const openNew = () => { Object.assign(nf, base()); nf.clearErrors(); newModal.value = true; };
const cycle = (k) => { const order = Object.keys(props.schemes[nf.scheme].levels); nf.matrix[k] = order[(order.indexOf(nf.matrix[k]) + 1) % order.length]; };
const changeScheme = () => { nf.matrix = { ...props.default_matrices[nf.scheme] }; };
const schemeOpts = computed(() => Object.fromEntries(Object.entries(props.schemes).map(([k, s]) => [k, s.label])));
const addDim = () => nf.dimensions.push({ key: `d${nf.dimensions.length + 1}`, label: '' });
const saveNew = () => nf.post('/criteria', { onSuccess: () => (newModal.value = false) });
const activate = (v) => { if (confirm(`Aktifkan kriteria versi ${v.version}? Seluruh skor risiko akan dihitung ulang.`)) router.post(`/criteria/${v.id}/activate`); };
</script>
<template>
  <Head title="Kriteria & taksonomi" />
  <PageHead kicker="ISO 31000 §6.3.4" title="Kriteria risiko & taksonomi" sub="Skala kemungkinan/dampak, matriks 5×5, ambang eskalasi, serta kategori risiko dengan risk appetite dan tolerance. Kriteria diversikan; versi aktif dipakai untuk seluruh perhitungan.">
    <button v-if="can.write && tab === 'criteria'" type="button" class="btn c-green" @click="openNew"><Icon name="plus" />Versi kriteria baru</button>
    <button v-if="can.write && tab === 'taxonomy'" type="button" class="btn c-green" @click="catItem = null; catModal = true"><Icon name="plus" />Tema / kategori / peristiwa</button>
  </PageHead>
  <div class="tabs"><button type="button" :class="{ on: tab === 'criteria' }" @click="tab = 'criteria'">Kriteria & matriks<span class="c">v{{ active?.version }}</span></button><button type="button" :class="{ on: tab === 'taxonomy' }" @click="tab = 'taxonomy'">Taksonomi & appetite<span class="c">{{ categories.length }}</span></button></div>
  <div v-if="tab === 'criteria' && view" class="s-grid">
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Versi kriteria</h3></div><div class="card-b stack">
      <div v-for="v in verPager.items" :key="v.id" class="row between" style="padding:8px 10px;border:1px solid var(--line);border-radius:10px" :style="view?.id === v.id ? 'outline:2px solid var(--accent)' : ''"><div><b>Versi {{ v.version }}</b> <Pill v-if="v.active" value="active" /><div class="hint">berlaku {{ fmt.date(v.effective_from) }} · {{ v.creator?.name || 'sistem' }}</div></div><span class="row"><button type="button" class="btn sm ghost c-indigo" @click="view = v">Lihat</button><button v-if="can.write && !v.active" type="button" class="btn sm c-amber" @click="activate(v)">Aktifkan</button></span></div>
      <LocalPager :p="verPager" />
      <div class="alert-box info"><Icon name="info" /><span>Ambang: eskalasi ≥ <b>{{ view.thresholds?.escalate }}</b>, kritis ≥ <b>{{ view.thresholds?.critical }}</b>. Risiko dengan skor residual ≥ eskalasi wajib disetujui Management.</span></div>
    </div></div>
    <div class="card" style="grid-column:span 8"><div class="card-h"><h3>Matriks level (versi {{ view.version }})</h3><span class="sub">{{ schemes[view.scheme || 'standard']?.label }}</span></div><div class="card-b">
      <div class="hm"><span class="yt">Kemungkinan →</span><template v-for="l in [5, 4, 3, 2, 1]" :key="l"><span class="ax y"><b>{{ l }}</b><span class="axl">{{ view.likelihood?.[l - 1]?.label }}</span></span><div v-for="i in 5" :key="i" class="cell" :class="'lv-' + lvKey(view.matrix[`${l}-${i}`])"><b>{{ l * i }}</b><small>{{ lvNames(view)[view.matrix[`${l}-${i}`]] }}</small></div></template><span></span><span class="ax x"></span><span v-for="i in 5" :key="'x' + i" class="ax x"><b>{{ i }}</b><span class="axl">{{ view.impact?.[i - 1]?.label }}</span></span><span class="xt">Dampak →</span></div>
    </div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Skala kemungkinan</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th class="num">Nilai</th><th>Label</th><th>Deskripsi</th></tr></thead><tbody><tr v-for="x in view.likelihood" :key="x.v"><td class="num mono">{{ x.v }}</td><td class="t-main">{{ x.label }} <span class="muted">{{ x.en }}</span></td><td class="fg2 wrap">{{ x.desc }}</td></tr></tbody></table></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Skala dampak</h3><span class="sub">{{ (view.dimensions || []).map((d) => d.label).join(' · ') }}</span></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th class="num">Nilai</th><th>Label</th><th v-for="d in view.dimensions" :key="d.key">{{ d.label }}</th></tr></thead><tbody><tr v-for="x in view.impact" :key="x.v"><td class="num mono">{{ x.v }}</td><td class="t-main">{{ x.label }}</td><td v-for="d in view.dimensions" :key="d.key" class="fg2 wrap" style="font-size:12px">{{ x.dims?.[d.key] }}</td></tr></tbody></table></div></div>
  </div>
  <div v-if="tab === 'taxonomy'" class="alert-box info"><Icon name="flag" /><div><b>Pernyataan risk appetite:</b> {{ appetite?.appetite_statement || 'Belum ditetapkan — isi di Pengaturan Organisasi.' }}<div v-if="appetite?.appetite_basis" class="hint">Dasar: {{ appetite.appetite_basis }} · ditetapkan {{ fmt.date(appetite.appetite_date) }}</div></div></div>
  <div v-if="tab === 'taxonomy'" class="alert-box info"><Icon name="info" /><span>Taksonomi bertingkat sesuai PER-2/MBU/03/2023: <b>T1 Tema Risiko</b> › <b>T2 Kategori Risiko</b> › <b>T3 Kelompok Peristiwa Risiko</b>. Isi kode Taksonomi Risiko Portofolio BUMN agar profil risiko dapat diagregasi. Jumlah risiko pada T1/T2 sudah mencakup turunannya.</span></div>
  <div v-if="tab === 'taxonomy'" class="card"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Taksonomi</th><th>Tingkat</th><th>Kode BUMN</th><th class="num">Appetite</th><th class="num">Tolerance</th><th class="num">Risk limit</th><th class="num">Risiko aktif</th><th>Status</th><th></th></tr></thead><tbody>
    <tr v-for="c in catPager.items" :key="c.id"><td class="t-main wrap"><span :style="`padding-left:${(c.depth - 1) * 18}px`"><span v-if="c.depth > 1" class="muted">└ </span>{{ c.name }}</span><div class="t-sub" :style="`padding-left:${(c.depth - 1) * 18}px`">{{ c.description || c.name_en }}</div></td><td><span class="pill">{{ (tiers[c.depth] || `T${c.depth}`).split(' · ')[0] }}</span></td><td class="mono">{{ c.bumn_code || '—' }}</td><td class="num mono">{{ c.appetite }}</td><td class="num mono">{{ c.tolerance }}</td><td class="num">{{ c.risk_limit ? fmt.short(c.risk_limit) : '—' }}</td><td class="num"><Link :href="`/risks?category_id=${c.id}&status=active`">{{ c.risks_total }}</Link><div v-if="c.risks_total !== c.risks_count" class="t-sub">{{ c.risks_count }} langsung</div></td><td><Pill :value="c.active ? 'active' : 'inactive'" /></td><td><span class="row" style="justify-content:flex-end"><button v-if="can.write" type="button" class="btn sm c-blue" @click="catItem = c; catModal = true">Ubah</button><ConfirmButton v-if="can.delete" :href="`/criteria/categories/${c.id}`" /></span></td></tr>
  </tbody></table></div><LocalPager :p="catPager" /></div>
  <CrudModal :show="catModal" :title="catItem ? 'Ubah kategori' : 'Tambah kategori'" :fields="catFields" :item="catItem" :url="catItem ? `/criteria/categories/${catItem.id}` : '/criteria/categories'" :method="catItem ? 'put' : 'post'" @close="catModal = false" />
  <Modal :show="newModal" title="Versi kriteria baru" wide @close="newModal = false">
    <div class="stack">
      <div v-if="Object.keys(nf.errors).length" class="alert-box bad"><Icon name="alert" /><span>{{ Object.values(nf.errors)[0] }}</span></div>
      <div class="form-grid cols-2"><Field :model-value="nf.scheme" type="select" label="Skema level risiko" :options="schemeOpts" required hint="Skema BUMN memakai 5 level: Low, Low to Moderate, Moderate, Moderate to High, High (PER-2/MBU/03/2023)." @update:model-value="(v) => { nf.scheme = v || 'standard'; changeScheme(); }" /></div>
      <div class="form-grid cols-3"><Field v-model="nf.effective_from" type="date" label="Berlaku sejak" required /><Field v-model="nf.thresholds.escalate" type="number" label="Ambang eskalasi (skor)" min="2" max="25" required /><Field v-model="nf.thresholds.critical" type="number" label="Ambang kritis (skor)" min="2" max="25" required /></div>
      <h4 style="margin:0">Skala kemungkinan</h4><div v-for="x in nf.likelihood" :key="x.v" class="row" style="gap:8px"><span class="mono" style="width:20px">{{ x.v }}</span><Field v-model="x.label" placeholder="Label" style="flex:1" /><Field v-model="x.desc" placeholder="Deskripsi" style="flex:2" /></div>
      <h4 style="margin:0">Dimensi dampak <button type="button" class="btn sm ghost c-teal" @click="addDim">+ dimensi</button></h4><div class="row" style="gap:8px"><template v-for="(d, i) in nf.dimensions" :key="i"><Field v-model="d.key" placeholder="kunci" style="width:90px" /><Field v-model="d.label" placeholder="Label" style="flex:1" /></template></div>
      <h4 style="margin:0">Skala dampak</h4><div v-for="x in nf.impact" :key="x.v" class="row" style="gap:8px"><span class="mono" style="width:20px">{{ x.v }}</span><Field v-model="x.label" placeholder="Label" style="flex:1" /><Field v-for="d in nf.dimensions" :key="d.key" v-model="x.dims[d.key]" :placeholder="d.label" style="flex:1" /></div>
      <h4 style="margin:0">Matriks level (klik sel untuk mengubah)</h4><div class="row" style="gap:12px;flex-wrap:wrap"><span v-for="(lb, k) in schemes[nf.scheme].levels" :key="k" class="lv" :class="'lv-' + lvKey(k)"><i></i>{{ lb }}</span></div>
      <div class="hm compact"><span class="yt">L</span><template v-for="l in [5, 4, 3, 2, 1]" :key="l"><span class="ax y"><b>{{ l }}</b></span><button v-for="i in 5" :key="i" type="button" class="cell" :class="'lv-' + lvKey(nf.matrix[`${l}-${i}`])" @click="cycle(`${l}-${i}`)"><b>{{ l * i }}</b></button></template><span></span><span class="ax x"></span><span v-for="i in 5" :key="'x' + i" class="ax x"><b>{{ i }}</b></span><span class="xt">I</span></div>
      <Field v-model="nf.activate" type="checkbox" label="Aktifkan versi ini sekarang (seluruh skor dihitung ulang)" />
    </div>
    <template #footer><button class="btn ghost c-indigo" @click="newModal = false">Batal</button><button class="btn c-green" :disabled="nf.processing" @click="saveNew">Simpan versi</button></template>
  </Modal>
</template>
