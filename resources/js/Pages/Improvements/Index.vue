<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Pill from '../../Components/Pill.vue';
import Kpi from '../../Components/Kpi.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ items: Array, filters: Object, filter_labels: Object, lessons: Array, sources: Object, subject_types: Object, stats: Object, users: Array, units: Array, risks: Array, can: Object });
const modal = ref(false); const item = ref(null); const lessonModal = ref(false);
const qp = new URLSearchParams(window.location.search);
// filter tautan (sumber/risiko/id) dipertahankan saat filter status/sumber diubah
const fixed = Object.fromEntries(['subject_type', 'subject_id', 'risk_id', 'id'].filter((k) => props.filters[k]).map((k) => [k, props.filters[k]]));
const special = Object.keys(fixed).length > 0;
const f = reactive({ status: props.filters.status || '', source_type: props.filters.source_type || '' });
let t; watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get('/improvements', { ...fixed, ...Object.fromEntries(Object.entries(f).filter(([, v]) => v)) }, { preserveState: true, replace: true }), 350); });
if (qp.get('from') && props.can.write) { item.value = null; modal.value = true; }
const closeModal = () => { modal.value = false; try { const q = new URLSearchParams(Object.entries({ ...fixed, ...f }).filter(([, v]) => v)).toString(); window.history.replaceState(window.history.state, '', `/improvements${q ? `?${q}` : ''}`); } catch (e) { /* abaikan */ } };
const prefill = qp.get('from') ? { source_type: qp.get('from'), source_ref: qp.get('ref') || '', status: 'open', subject_type: qp.get('subj') || '', subject_id: qp.get('sid') || '', risk_id: qp.get('rid') || '' } : null;
const riskOpts = computed(() => props.risks.map((r) => ({ id: r.id, name: `${r.code} · ${r.name}` })));
const fields = computed(() => [{ key: 'title', label: 'Tindakan perbaikan', required: true, span: true }, { key: 'source_type', label: 'Sumber', type: 'select', options: props.sources, empty: '', required: true, default: 'incident' }, { key: 'source_ref', label: 'Referensi (kode)', placeholder: 'INC-2026-001 / C-2026-003' }, { key: 'risk_id', label: 'Risiko terkait', type: 'select', options: riskOpts.value, span: true }, { key: 'description', label: 'Uraian', type: 'textarea', span: true }, { key: 'pic_id', label: 'PIC', type: 'select', options: props.users }, { key: 'unit_id', label: 'Unit', type: 'select', options: props.units }, { key: 'due_date', label: 'Tenggat', type: 'date' }, { key: 'status', label: 'Status', type: 'select', options: { open: 'Terbuka', in_progress: 'Berjalan', done: 'Selesai' }, empty: '', required: true, default: 'open' },
  { key: 'subject_type', type: 'hidden', style: 'display:none' }, { key: 'subject_id', type: 'hidden', style: 'display:none' }]);
const subjectHref = (s) => ({ control: `/controls/${s.id}`, kri: s.risk_id ? `/kris?risk_id=${s.risk_id}` : '/kris', incident: `/incidents/${s.id}`, review: s.risk_id ? `/reviews?risk_id=${s.risk_id}` : '/reviews' })[s.type];
const lessonHref = (s) => ({ risk: `/risks/${s.id}`, incident: `/incidents/${s.id}` })[s.type];
const lf = useForm({ text: '' });
const saveLesson = () => lf.post('/lessons', { onSuccess: () => { lf.reset(); lessonModal.value = false; } });
</script>
<template>
  <Head title="Perbaikan berkelanjutan" />
  <PageHead kicker="ISO 31000 §5.7" title="Continual improvement & lesson learned" sub="Tindak lanjut dari insiden, temuan audit, kegagalan kontrol, pelanggaran KRI, dan hasil review untuk memperbaiki kerangka dan proses manajemen risiko.">
    <button v-if="can.write" type="button" class="btn c-green" @click="item = null; modal = true"><Icon name="plus" />Tindakan perbaikan</button>
    <button v-if="can.write" type="button" class="btn c-cyan" @click="lessonModal = true"><Icon name="book" />Lesson learned</button>
  </PageHead>
  <div class="kpis"><Kpi label="Terbuka" :value="stats.open || 0" level="h" /><Kpi label="Berjalan" :value="stats.in_progress || 0" level="m" /><Kpi label="Selesai" :value="stats.done || 0" level="l" /><Kpi label="Lesson learned" :value="lessons.length" /></div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 8"><div class="card-b filters"><Field v-model="f.status" type="select" label="Status" :options="{ active: 'Belum selesai', open: 'Terbuka', in_progress: 'Berjalan', done: 'Selesai' }" empty="Semua" /><Field v-model="f.source_type" type="select" label="Sumber" :options="sources" empty="Semua" /></div>
      <div v-if="special" class="card-b row" style="gap:6px;flex-wrap:wrap"><span class="hint">Filter:</span><span v-if="filter_labels.subject" class="pill run">{{ filter_labels.subject }}</span><span v-if="filter_labels.risk" class="pill run">Risiko {{ filter_labels.risk }}</span><span v-if="filters.id" class="pill run">Improvement #{{ filters.id }}</span><Link href="/improvements" class="btn sm ghost c-indigo">hapus filter</Link></div>
      <div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Tindakan</th><th>Sumber</th><th>Risiko</th><th>PIC</th><th>Tenggat</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="i in items" :key="i.id"><td class="code-link">{{ i.code }}</td><td class="t-main wrap">{{ i.title }}<div class="t-sub">{{ i.description }}</div></td><td class="t-sub">{{ sources[i.source_type] }}<br><Link v-if="i.subject" :href="subjectHref(i.subject)" class="code-link">{{ i.subject.code || `${subject_types[i.subject.type]} #${i.subject.id}` }}</Link><span v-else class="mono">{{ i.source_ref }}</span></td><td><Link v-if="i.risk" :href="`/risks/${i.risk.id}`" class="code-link">{{ i.risk.code }}</Link><span v-else class="t-sub">—</span></td><td class="t-sub">{{ i.pic?.name }}<br>{{ i.unit?.name }}</td><td :style="i.status !== 'done' && i.due_date && new Date(i.due_date) < new Date() ? 'color:var(--bad-ink);font-weight:600' : ''">{{ fmt.date(i.due_date) }}</td><td><Pill :value="i.status" /></td><td><span class="row" style="justify-content:flex-end"><button v-if="i.can_update" type="button" class="btn sm c-blue" @click="item = i; modal = true">Ubah</button><ConfirmButton v-if="can.delete" :href="`/improvements/${i.id}`" /></span></td></tr>
      <tr v-if="!items.length"><td colspan="8"><div class="empty">{{ special || f.status || f.source_type ? 'Tidak ada tindakan perbaikan sesuai filter.' : 'Belum ada tindakan perbaikan.' }}</div></td></tr></tbody></table></div></div>
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Lesson learned</h3></div><div class="card-b stack" style="max-height:640px;overflow:auto"><div v-for="l in lessons" :key="l.id" class="alert-box info"><Icon name="book" /><span style="flex:1">{{ l.text }}<div class="hint">{{ l.creator }} · {{ fmt.date(l.created_at) }}<span v-if="l.subject"> · {{ l.subject.label }} <Link v-if="lessonHref(l.subject)" :href="lessonHref(l.subject)" class="code-link">{{ l.subject.code }}</Link><span v-else>{{ l.subject.code }}</span></span></div></span><ConfirmButton v-if="can.delete" :href="`/lessons/${l.id}`" cls="btn sm ghost c-red" label="×" /></div><div v-if="!lessons.length" class="empty">Belum ada.</div></div></div>
  </div>
  <CrudModal :show="modal" :title="item ? 'Ubah tindakan' : 'Tindakan perbaikan'" :fields="fields" :item="item || prefill" :url="item ? `/improvements/${item.id}` : '/improvements'" :method="item ? 'put' : 'post'" @close="closeModal" />
  <Modal :show="lessonModal" title="Lesson learned organisasi" @close="lessonModal = false"><Field v-model="lf.text" type="textarea" label="Pembelajaran" required :error="lf.errors.text" :rows="4" /><template #footer><button class="btn ghost c-indigo" @click="lessonModal = false">Batal</button><button class="btn c-green" :disabled="lf.processing" @click="saveLesson">Simpan</button></template></Modal>
</template>
