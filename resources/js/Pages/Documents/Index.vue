<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Kpi from '../../Components/Kpi.vue';
import Field from '../../Components/Field.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ documents: Object, filters: Object, subject: Object, stats: Object, subject_labels: Object, subject_options: Object, can: Object });
const L = usePage().props.labels;
const modal = ref(false); const edit = ref(null);
const f = reactive({ q: props.filters.q || '', type: props.filters.type || '', status: props.filters.status || '' });
if (props.filters.history) { /* tampilan riwayat versi */ }
// ?subject_kind=&subject_id= menyaring daftar ke subjek itu & mengisi awal form unggah; &upload=1 langsung membuka form
const subj = props.subject ? { subject_kind: props.subject.type, subject_id: props.subject.id } : {};
let t; watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get('/documents', { ...subj, ...Object.fromEntries(Object.entries(f).filter(([, v]) => v)) }, { preserveState: true, replace: true }), 350); });
const form = useForm({ file: null, title: '', type: 'evidence', subject_kind: subj.subject_kind || '', subject_id: subj.subject_id || '', status: 'draft', expires_at: '', replaces_id: '' });
const kinds = Object.fromEntries(Object.entries(props.subject_labels).filter(([k]) => k in (props.subject_options || {})));
watch(() => form.subject_kind, (k, o) => { if (o !== undefined && k !== o && !(k === subj.subject_kind && form.subject_id === subj.subject_id)) form.subject_id = ''; });
const subjectHref = (s) => ({ risk: `/risks/${s.id}`, control: `/controls/${s.id}`, action_plan: `/action-plans/${s.id}`, incident: `/incidents/${s.id}`, improvement: `/improvements?id=${s.id}`, review: s.risk_id ? `/reviews?risk_id=${s.risk_id}` : '/reviews' })[s.type];
const replacing = ref(null);
const newVersion = (d) => { replacing.value = d; form.reset(); form.replaces_id = d.id; form.title = d.title; form.type = d.type; modal.value = true; };
const openNew = () => { replacing.value = null; form.reset(); modal.value = true; };
const upload = () => form.post('/documents', { forceFormData: true, onSuccess: () => { form.reset(); modal.value = false; } });
const ef = useForm({ title: '', type: '', status: '', expires_at: '' });
const openEdit = (d) => { edit.value = d; ef.title = d.title; ef.type = d.type; ef.status = d.status; ef.expires_at = d.expires_at ? String(d.expires_at).slice(0, 10) : ''; };
if (props.filters.upload && props.subject && props.can.write) { modal.value = true; try { window.history.replaceState(window.history.state, '', `/documents?subject_kind=${props.subject.type}&subject_id=${props.subject.id}`); } catch (e) { /* abaikan */ } }
const saveEdit = () => ef.put(`/documents/${edit.value.id}`, { preserveScroll: true, onSuccess: () => (edit.value = null) });
</script>
<template>
  <Head title="Dokumen & bukti" />
  <PageHead kicker="Pelaporan & Dokumen" title="Manajemen dokumen & bukti" sub="Kebijakan, SOP, berita acara, hasil pengujian, dan bukti pelaksanaan. Berkas disimpan di luar web root, diverifikasi tipe isinya, dan diunduh hanya melalui otorisasi.">
    <button v-if="can.write" type="button" class="btn c-orange" @click="openNew"><Icon name="upload" />Unggah dokumen</button>
    <button v-if="filters.history" type="button" class="btn ghost c-indigo" @click="router.get('/documents')">Tutup riwayat versi</button>
  </PageHead>
  <div class="kpis"><Kpi label="Total dokumen" :value="stats.total" /><Kpi label="Kedaluwarsa ≤ 60 hari" :value="stats.expiring" level="m" /><Kpi label="Kedaluwarsa" :value="stats.expired" level="vh" /><Kpi v-for="(n, k) in stats.by_type" :key="k" :label="L.document_types[k] || k" :value="n" /></div>
  <div v-if="filters.subject_kind" class="row" style="gap:6px;margin-bottom:10px"><span class="pill run">Dokumen {{ subject ? `${subject.label} ${subject.code || ''} · ${subject.name || ''}` : 'subjek tidak ditemukan' }}</span><Link v-if="subject" :href="subjectHref(subject)" class="btn sm ghost c-blue">Buka {{ subject.label.toLowerCase() }} →</Link><Link href="/documents" class="btn sm ghost c-indigo">hapus filter</Link></div>
  <div class="card"><div class="card-b filters"><Field v-model="f.q" label="Cari" placeholder="judul / nama berkas" style="flex:1" /><Field v-model="f.type" type="select" label="Tipe" :options="L.document_types" empty="Semua" /><Field v-model="f.status" type="select" label="Status" :options="{ draft: 'Draft', review: 'Review', approved: 'Disetujui', expired: 'Kedaluwarsa' }" empty="Semua" /></div>
    <div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Dokumen</th><th>Tipe</th><th>Subjek</th><th>Status</th><th>Kedaluwarsa</th><th>Diunggah</th><th></th></tr></thead><tbody>
      <tr v-for="d in documents.data" :key="d.id"><td class="t-main wrap">{{ d.title }}<div class="t-sub">{{ d.original_name }} · {{ (d.size / 1024).toFixed(0) }} KB · v{{ d.version }}</div></td><td>{{ L.document_types[d.type] }}</td><td class="t-sub"><template v-if="d.subject">{{ d.subject.label }} <Link :href="subjectHref(d.subject)" class="code-link">{{ d.subject.code || `#${d.subject.id}` }}</Link><div v-if="d.subject.name" class="wrap">{{ d.subject.name }}</div></template><span v-else>—</span></td><td><Pill :value="d.status" /></td><td :style="d.expires_at && new Date(d.expires_at) < new Date() ? 'color:var(--bad-ink)' : ''">{{ fmt.date(d.expires_at) }}</td><td class="t-sub">{{ d.uploader }}<br>{{ fmt.date(d.created_at) }}</td><td><span class="row" style="justify-content:flex-end"><a :href="`/documents/${d.id}/download`" class="btn sm c-blue">Unduh</a><button v-if="can.write" type="button" class="btn sm c-violet" @click="newVersion(d)">Versi baru</button><button type="button" class="btn sm ghost c-indigo" @click="router.get('/documents', { history: d.id })">Riwayat</button><button v-if="d.can_delete" type="button" class="btn sm c-teal" @click="openEdit(d)">Ubah</button><ConfirmButton v-if="d.can_delete" :href="`/documents/${d.id}`" /></span></td></tr>
      <tr v-if="!documents.data.length"><td colspan="7"><div class="empty">Tidak ada dokumen.</div></td></tr></tbody></table></div><Pagination :data="documents" /></div>
  <Modal :show="modal" :title="replacing ? `Versi baru · ${replacing.title} (v${replacing.version} → v${replacing.version + 1})` : 'Unggah dokumen'" @close="modal = false">
    <div class="stack">
      <Field v-model="form.title" label="Judul" required :error="form.errors.title" />
      <div class="row" style="gap:8px"><Field v-model="form.type" type="select" label="Tipe" :options="L.document_types" empty="" required style="flex:1" /><Field v-model="form.status" type="select" label="Status" :options="{ draft: 'Draft', review: 'Review', approved: 'Disetujui' }" empty="" style="flex:1" /></div>
      <div v-if="!replacing" class="row" style="gap:8px"><Field v-model="form.subject_kind" type="select" label="Kaitkan ke" :options="kinds" empty="(tidak dikaitkan)" style="flex:1" :error="form.errors.subject_kind" /><Field v-if="form.subject_kind" v-model="form.subject_id" type="select" :label="subject_labels[form.subject_kind]" :options="subject_options[form.subject_kind] || []" required style="flex:2" :error="form.errors.subject_id" /></div>
      <div class="field"><label>Berkas (PDF/DOC/XLS/JPG/PNG, maks 25 MB)</label><input type="file" class="inp" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" @change="form.file = $event.target.files[0]"><span v-if="form.errors.file" class="err-msg">{{ form.errors.file }}</span></div>
      <Field v-model="form.expires_at" type="date" label="Tanggal kedaluwarsa (opsional)" :error="form.errors.expires_at" />
      <div v-if="form.progress" class="meter"><i :style="`width:${form.progress.percentage}%`"></i></div>
    </div>
    <template #footer><button class="btn ghost c-indigo" @click="modal = false">Batal</button><button class="btn c-orange" :disabled="form.processing || !form.file" @click="upload">Unggah</button></template>
  </Modal>
  <Modal :show="!!edit" title="Ubah metadata dokumen" @close="edit = null">
    <div class="stack"><Field v-model="ef.title" label="Judul" required :error="ef.errors.title" /><Field v-model="ef.type" type="select" label="Tipe" :options="L.document_types" empty="" /><Field v-model="ef.status" type="select" label="Status" :options="{ draft: 'Draft', review: 'Review', approved: 'Disetujui', expired: 'Kedaluwarsa' }" empty="" /><Field v-model="ef.expires_at" type="date" label="Kedaluwarsa" /></div>
    <template #footer><button class="btn ghost c-indigo" @click="edit = null">Batal</button><button class="btn c-green" :disabled="ef.processing" @click="saveEdit">Simpan</button></template>
  </Modal>
</template>
