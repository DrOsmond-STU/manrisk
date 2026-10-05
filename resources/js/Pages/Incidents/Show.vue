<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Pill from '../../Components/Pill.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ incident: Object, categories: Array, can: Object });
const L = usePage().props.labels; const i = props.incident;
const modal = ref(null);
const fields = computed(() => [
  { key: 'title', label: 'Judul insiden', required: true, span: true }, { key: 'occurred_at', label: 'Waktu kejadian', type: 'datetime-local', required: true }, { key: 'location', label: 'Lokasi' },
  { key: 'chronology', label: 'Kronologi', type: 'textarea', span: true }, { key: 'cause', label: 'Penyebab', type: 'textarea', rows: 2 }, { key: 'impact', label: 'Dampak', type: 'textarea', rows: 2 },
  { key: 'loss_amount', label: 'Kerugian (Rp)', type: 'number', min: 0 }, { key: 'loss_type', label: 'Jenis kerugian' }, { key: 'response', label: 'Respons awal', type: 'textarea', rows: 2 }, { key: 'corrective_action', label: 'Tindakan korektif', type: 'textarea', rows: 2 },
  { key: 'status', label: 'Status', type: 'select', options: L.incident_statuses, empty: '', required: true },
]);
const lf = useForm({ text: '' });
const saveLesson = () => lf.post(`/incidents/${i.id}/lessons`, { onSuccess: () => { lf.reset(); modal.value = null; } });
</script>
<template>
  <Head :title="i.code" />
  <PageHead :kicker="`Insiden · ${fmt.datetime(i.occurred_at)}`" :title="`${i.code} · ${i.title}`">
    <Pill :value="i.status" /><Link href="/incidents" class="btn ghost c-indigo">‹ Daftar</Link>
    <button v-if="can.update" type="button" class="btn c-blue" @click="modal = 'edit'">Ubah / perbarui status</button>
    <button v-if="can.update" type="button" class="btn c-cyan" @click="modal = 'lesson'"><Icon name="book" />Lesson learned</button>
    <Link v-if="!i.risk && can.create_risk" :href="`/risks/create?incident=${i.id}`" class="btn c-green"><Icon name="plus" />Buat risiko dari insiden</Link>
    <Link v-if="can.update" :href="`/improvements?from=incident&ref=${i.code}`" class="btn c-teal"><Icon name="up" />Improvement</Link>
    <ConfirmButton v-if="$page.props.auth.user.role !== 'auditor' && can.update && ['super_admin', 'risk_admin', 'risk_manager'].includes($page.props.auth.user.role)" :href="`/incidents/${i.id}`" cls="btn c-red" />
  </PageHead>
  <div class="s-grid">
    <div class="card" style="grid-column:span 7"><div class="card-h"><h3>Rincian insiden</h3></div><div class="card-b"><dl class="kv"><dt>Lokasi</dt><dd>{{ i.location || '—' }}</dd><dt>Risiko terkait</dt><dd><Link v-if="i.risk" :href="`/risks/${i.risk.id}`" class="code-link">{{ i.risk.code }}</Link> {{ i.risk?.name || '—' }}</dd><dt>Unit</dt><dd>{{ i.unit?.name || '—' }}</dd><dt>Kronologi</dt><dd>{{ i.chronology || '—' }}</dd><dt>Penyebab</dt><dd>{{ i.cause || '—' }}</dd><dt>Dampak</dt><dd>{{ i.impact || '—' }}</dd><dt>Kerugian</dt><dd><b>{{ fmt.money(i.loss_amount) }}</b> <span class="muted">{{ i.loss_type }}</span></dd><dt>Respons</dt><dd>{{ i.response || '—' }}</dd><dt>Tindakan korektif</dt><dd>{{ i.corrective_action || '—' }}</dd><dt>Dilaporkan</dt><dd>{{ i.reporter?.name || '—' }} · {{ fmt.datetime(i.created_at) }}</dd><dt v-if="i.closed_at">Ditutup</dt><dd v-if="i.closed_at">{{ fmt.datetime(i.closed_at) }}</dd></dl></div></div>
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Lesson learned</h3></div><div class="card-b stack"><div v-for="l in i.lessons" :key="l.id" class="alert-box info"><Icon name="book" /><span>{{ l.text }}<div class="hint">{{ l.creator?.name }} · {{ fmt.date(l.created_at) }}</div></span></div><div v-if="!i.lessons.length" class="empty">Belum ada pembelajaran dicatat.</div></div>
      <div class="card-h"><h3>Loss event</h3></div><div class="card-b stack"><div v-for="l in i.loss_events" :key="l.id" class="row between" style="font-size:13px"><span>{{ l.event }} <span class="muted">({{ l.category?.name || '—' }})</span></span><b>{{ fmt.money(l.amount) }}</b></div><div v-if="!i.loss_events.length" class="hint">Tidak ada kerugian tercatat.</div></div>
      <div class="card-h"><h3>Dokumen</h3></div><div class="card-b stack"><div v-for="d in i.documents" :key="d.id" class="row between" style="font-size:13px"><span>{{ d.title }}</span><a :href="`/documents/${d.id}/download`" class="btn sm c-blue">Unduh</a></div><div v-if="!i.documents.length" class="hint">Belum ada dokumen.</div></div></div>
  </div>
  <CrudModal :show="modal === 'edit'" title="Ubah insiden" :fields="fields" :item="i" :url="`/incidents/${i.id}`" method="put" wide @close="modal = null" />
  <Modal :show="modal === 'lesson'" title="Lesson learned" @close="modal = null"><Field v-model="lf.text" type="textarea" label="Pembelajaran" required :error="lf.errors.text" :rows="4" /><template #footer><button class="btn ghost c-indigo" @click="modal = null">Batal</button><button class="btn c-green" :disabled="lf.processing" @click="saveLesson">Simpan</button></template></Modal>
</template>
