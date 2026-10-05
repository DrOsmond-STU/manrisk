<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Pill from '../../Components/Pill.vue';
import Kpi from '../../Components/Kpi.vue';
import Field from '../../Components/Field.vue';
import Modal from '../../Components/Modal.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ plans: Array, filters: Object, stats: Object, risks: Array, users: Array, units: Array, can: Object });
const L = usePage().props.labels;
const view = ref('table'); const modal = ref(false); const item = ref(null); const prog = ref(null);
const f = reactive({ q: props.filters.q || '', status: props.filters.status || '', pic_id: props.filters.pic_id || '', risk_id: props.filters.risk_id || '', unit_id: props.filters.unit_id || '', mine: props.filters.mine ? '1' : '' });
let t; watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get('/action-plans', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true }), 350); });
const fields = computed(() => [
  { key: 'risk_id', label: 'Risiko', type: 'select', options: props.risks.map((r) => ({ id: r.id, name: `${r.code} · ${r.name}` })), required: true, span: true },
  { key: 'title', label: 'Judul rencana', required: true, span: true }, { key: 'description', label: 'Uraian', type: 'textarea', span: true, rows: 2 },
  { key: 'pic_id', label: 'PIC', type: 'select', options: props.users }, { key: 'unit_id', label: 'Unit pelaksana', type: 'select', options: props.units },
  { key: 'priority', label: 'Prioritas', type: 'select', options: L.priorities, empty: '', required: true, default: 'medium' }, { key: 'budget', label: 'Anggaran (Rp)', type: 'number', min: 0 },
  { key: 'start_date', label: 'Mulai', type: 'date' }, { key: 'due_date', label: 'Tenggat', type: 'date', required: true },
  { key: 'expected_dl', label: 'Perkiraan penurunan kemungkinan', type: 'number', min: 0, max: 4, default: 1 }, { key: 'expected_di', label: 'Perkiraan penurunan dampak', type: 'number', min: 0, max: 4, default: 0 },
]);
const pf = useForm({ pct: 0, note: '', evidence: null });
const openProg = (p) => { prog.value = p; pf.pct = p.progress; pf.note = ''; pf.clearErrors(); };
const saveProg = () => pf.transform((d) => ({ progress: d.pct, note: d.note, ...(d.evidence ? { evidence: d.evidence } : {}) })).post(`/action-plans/${prog.value.id}/progress`, { preserveScroll: true, forceFormData: true, onSuccess: () => (prog.value = null) });
const cancel = (p) => { const reason = window.prompt('Alasan pembatalan:'); if (reason) router.post(`/action-plans/${p.id}/cancel`, { reason }, { preserveScroll: true }); };
const cols = [['todo', 'Belum mulai'], ['running', 'Berjalan'], ['overdue', 'Terlambat'], ['verify', 'Menunggu verifikasi'], ['done', 'Selesai']];
const byStatus = (s) => props.plans.filter((p) => p.status === s);
</script>
<template>
  <Head title="Action plan" />
  <PageHead kicker="Penanganan Risiko" title="Mitigasi & action plan" sub="Rencana tindakan per risiko dengan PIC, tenggat, anggaran, progres, dan perkiraan penurunan skor. Pengingat otomatis H-7, H-1, dan eskalasi saat terlambat.">
    <div class="seg"><button type="button" :class="{ on: view === 'table' }" @click="view = 'table'">Tabel</button><button type="button" :class="{ on: view === 'kanban' }" @click="view = 'kanban'">Kanban</button></div>
    <button v-if="can.write" type="button" class="btn c-green" @click="item = null; modal = true"><Icon name="plus" />Action plan</button>
  </PageHead>
  <div class="kpis"><Kpi label="Belum mulai" :value="stats.todo || 0" /><Kpi label="Berjalan" :value="stats.running || 0" level="m" /><Kpi label="Terlambat" :value="stats.overdue || 0" level="vh" /><Kpi label="Menunggu verifikasi" :value="stats.verify || 0" level="m" /><Kpi label="Selesai" :value="stats.done || 0" level="l" /><Kpi label="Dibatalkan" :value="stats.cancelled || 0" /></div>
  <div class="card"><div class="card-b filters"><Field v-model="f.q" label="Cari" placeholder="kode / judul" style="flex:1" /><Field v-model="f.status" type="select" label="Status" :options="{ todo: 'Belum mulai', running: 'Berjalan', overdue: 'Terlambat', verify: 'Menunggu verifikasi', done: 'Selesai', cancelled: 'Dibatalkan' }" empty="Semua" /><Field v-model="f.pic_id" type="select" label="PIC" :options="users" empty="Semua" /><Field v-model="f.risk_id" type="select" label="Risiko" :options="risks.map((r) => ({ id: r.id, name: r.code }))" empty="Semua" /><Field v-model="f.unit_id" type="select" label="Unit" :options="units" empty="Semua" /><Field v-model="f.mine" type="checkbox" label="Action plan saya" @update:modelValue="(v) => (f.mine = v ? '1' : '')" /></div>
    <div v-if="view === 'table'" class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Rencana</th><th>Risiko</th><th>PIC</th><th>Prioritas</th><th>Tenggat</th><th>Progres</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="p in plans" :key="p.id"><td><Link :href="`/action-plans/${p.id}`" class="code-link">{{ p.code }}</Link></td><td class="t-main wrap">{{ p.title }}</td><td><Link :href="`/risks/${p.risk_id}`" class="code-link">{{ p.risk?.code }}</Link></td><td class="t-sub">{{ p.pic?.name }}</td><td><Pill :value="p.priority" map="priorities" :tone="p.priority === 'critical' ? 'bad' : p.priority === 'high' ? 'warn' : ''" /></td><td>{{ fmt.date(p.due_date) }}</td><td><div class="prog"><div class="bar"><i :class="{ done: p.progress >= 100, late: p.status === 'overdue' }" :style="`width:${p.progress}%`"></i></div><span>{{ p.progress }}%</span></div></td><td><Pill :value="p.status" /></td><td><span class="row" style="justify-content:flex-end"><button v-if="p.can_progress && p.status !== 'cancelled' && p.progress < 100" type="button" class="btn sm c-teal" @click="openProg(p)">Progres</button><button v-if="p.can_update" type="button" class="btn sm c-blue" @click="item = p; modal = true">Ubah</button><button v-if="p.can_update && !p.cancelled_at && p.progress < 100" type="button" class="btn sm c-amber" @click="cancel(p)">Batalkan</button><ConfirmButton v-if="can.delete" :href="`/action-plans/${p.id}`" /></span></td></tr>
      <tr v-if="!plans.length"><td colspan="9"><div class="empty">Tidak ada action plan.</div></td></tr></tbody></table></div>
    <div v-else class="card-b kanban"><div v-for="c in cols" :key="c[0]" class="kcol"><h4>{{ c[1] }} ({{ byStatus(c[0]).length }})</h4><div v-for="p in byStatus(c[0])" :key="p.id" class="kcard"><div class="row between"><Link :href="`/action-plans/${p.id}`" class="code-link">{{ p.code }}</Link><Pill :value="p.priority" map="priorities" :tone="p.priority === 'critical' ? 'bad' : p.priority === 'high' ? 'warn' : ''" /></div><b>{{ p.title }}</b><div class="hint">{{ p.risk?.code }} · {{ p.pic?.name }} · {{ fmt.date(p.due_date) }}</div><div class="prog" style="margin-top:6px"><div class="bar"><i :class="{ done: p.progress >= 100, late: p.status === 'overdue' }" :style="`width:${p.progress}%`"></i></div><span>{{ p.progress }}%</span></div><button v-if="p.can_progress && p.progress < 100" type="button" class="btn sm c-teal" style="margin-top:6px" @click="openProg(p)">Catat progres</button></div></div></div>
  </div>
  <CrudModal :show="modal" :title="item ? 'Ubah action plan' : 'Action plan baru'" :fields="fields" :item="item" :url="item ? `/action-plans/${item.id}` : '/action-plans'" :method="item ? 'put' : 'post'" wide @close="modal = false" />
  <Modal :show="!!prog" title="Catat progres" @close="prog = null">
    <p v-if="prog"><b>{{ prog.code }}</b> · {{ prog.title }}</p>
    <Field v-model="pf.pct" type="number" label="Progres (%)" min="0" max="100" required :error="pf.errors.progress" />
    <input v-model.number="pf.pct" type="range" min="0" max="100" step="5" style="width:100%;accent-color:var(--accent)">
    <Field v-model="pf.note" type="textarea" label="Catatan (wajib saat 100%)" :error="pf.errors.note" :rows="3" />
    <div class="field"><label>Bukti pelaksanaan {{ pf.pct >= 100 ? '(wajib bila belum ada bukti)' : '(opsional)' }}</label><input type="file" class="inp" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" @change="pf.evidence = $event.target.files[0]"><span v-if="pf.errors.evidence" class="err-msg">{{ pf.errors.evidence }}</span></div>
    <template #footer><button class="btn ghost c-indigo" @click="prog = null">Batal</button><button class="btn c-green" :disabled="pf.processing" @click="saveProg">Simpan</button></template>
  </Modal>
</template>
