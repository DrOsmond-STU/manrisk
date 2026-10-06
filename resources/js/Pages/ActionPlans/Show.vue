<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Chart from '../../Components/Chart.vue';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ plan: Object, can: Object });
const L = usePage().props.labels; const p = props.plan;
const log = [...p.progress_log].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
const progOpt = { xAxis: { type: 'category', data: ['Mulai', ...log.map((l) => fmt.date(l.created_at))] }, yAxis: { type: 'value', min: 0, max: 100 }, series: [{ type: 'line', step: 'end', data: [0, ...log.map((l) => l.to_pct)], areaStyle: { opacity: .12 }, lineStyle: { width: 3 } }] };
const verify = (action) => { const note = action === 'reject' ? window.prompt('Alasan pengembalian:') : ''; if (action === 'reject' && !note) return; router.post(`/action-plans/${p.id}/verify`, { action, note }); };
const prog = ref(false);
const pf = useForm({ pct: p.progress, note: '', evidence: null });
const openProg = () => { pf.pct = p.progress; pf.note = ''; pf.evidence = null; pf.clearErrors(); prog.value = true; };
const saveProg = () => pf.transform((d) => ({ progress: d.pct, note: d.note, ...(d.evidence ? { evidence: d.evidence } : {}) })).post(`/action-plans/${p.id}/progress`, { preserveScroll: true, forceFormData: true, onSuccess: () => (prog.value = false) });
const cancel = () => { const reason = window.prompt('Alasan pembatalan:'); if (reason) router.post(`/action-plans/${p.id}/cancel`, { reason }, { preserveScroll: true }); };
</script>
<template>
  <Head :title="p.code" />
  <PageHead :kicker="`Action plan · ${p.risk?.code}`" :title="`${p.code} · ${p.title}`"><Link href="/action-plans" class="btn ghost c-indigo">‹ Daftar</Link><Link v-if="can.view_risk" :href="`/risks/${p.risk_id}`" class="btn c-blue">Risiko {{ p.risk?.code }}</Link>
    <button v-if="can.progress && !p.cancelled_at && p.progress < 100" type="button" class="btn c-teal" @click="openProg">Catat progres</button>
    <Link v-if="can.upload && !p.cancelled_at" :href="`/documents?subject_kind=action_plan&subject_id=${p.id}&upload=1`" class="btn c-orange"><Icon name="upload" />Unggah bukti</Link>
    <button v-if="can.update && !p.cancelled_at && p.progress < 100" type="button" class="btn c-amber" @click="cancel">Batalkan</button>
    <template v-if="can.verify"><button type="button" class="btn c-amber" @click="verify('reject')">Kembalikan</button><button type="button" class="btn c-green" @click="verify('approve')"><Icon name="send" />Verifikasi selesai</button></template></PageHead>
  <div v-if="p.status === 'verify'" class="alert-box warn"><Icon name="inbox" /><span>Action plan sudah 100% dan menunggu verifikasi Risk Owner.</span></div>
  <div class="kpis"><div class="kpi"><div class="k-l">Status</div><div class="k-v" style="font-size:18px"><Pill :value="p.status" /></div></div><div class="kpi"><div class="k-l">Progres</div><div class="k-v">{{ p.progress }}%</div><div class="meter"><i :style="`width:${p.progress}%`"></i></div></div><div class="kpi"><div class="k-l">Tenggat</div><div class="k-v" style="font-size:20px">{{ fmt.date(p.due_date) }}</div><div class="k-s">mulai {{ fmt.date(p.start_date) }}</div></div><div class="kpi"><div class="k-l">Anggaran</div><div class="k-v" style="font-size:20px">{{ fmt.short(p.budget) }}</div></div><div class="kpi"><div class="k-l">Perkiraan penurunan</div><div class="k-v" style="font-size:20px">L −{{ p.expected_dl }} · I −{{ p.expected_di }}</div></div></div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Rincian</h3></div><div class="card-b"><dl class="kv"><dt>Risiko</dt><dd><template v-if="p.risk"><Link v-if="can.view_risk" :href="`/risks/${p.risk_id}`" class="code-link">{{ p.risk.code }}</Link><span v-else class="mono">{{ p.risk.code }}</span> {{ p.risk.name }}<div><Link :href="`/action-plans?risk_id=${p.risk_id}`" class="hint">Lihat semua action plan risiko ini →</Link></div></template><span v-else>—</span></dd><dt>Uraian</dt><dd>{{ p.description || '—' }}</dd><dt>PIC</dt><dd><Link v-if="p.pic" :href="`/action-plans?pic_id=${p.pic.id}`" class="plain">{{ p.pic.name }}</Link><span v-else>—</span></dd><dt>Unit</dt><dd>{{ p.unit?.name || '—' }}</dd><dt>Prioritas</dt><dd><Pill :value="p.priority" map="priorities" /></dd><dt v-if="p.completed_at">Selesai</dt><dd v-if="p.completed_at">{{ fmt.datetime(p.completed_at) }}</dd><dt v-if="p.verified_at">Diverifikasi</dt><dd v-if="p.verified_at">{{ fmt.datetime(p.verified_at) }} · {{ p.verifier?.name }}</dd><dt v-if="p.cancelled_at">Dibatalkan</dt><dd v-if="p.cancelled_at">{{ fmt.datetime(p.cancelled_at) }} — {{ p.cancel_reason }}</dd></dl></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Riwayat progres</h3></div><div class="card-b"><Chart v-if="log.length" :option="progOpt" height="160px" /><div class="timeline"><div v-for="l in p.progress_log" :key="l.id" class="tl"><i :class="{ ok: l.to_pct >= 100 }"></i><div><b>{{ l.from_pct }}% → {{ l.to_pct }}%</b> <span class="hint">{{ l.user?.name }} · {{ fmt.datetime(l.created_at) }}</span><div class="fg2">{{ l.note }}</div></div></div><div v-if="!p.progress_log.length" class="empty">Belum ada catatan progres.</div></div></div></div>
    <div class="card" style="grid-column:1/-1"><div class="card-h"><h3>Bukti pelaksanaan</h3><Link v-if="p.documents.length" :href="`/documents?subject_kind=action_plan&subject_id=${p.id}`" class="btn sm ghost c-indigo">Buka di Dokumen</Link></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Dokumen</th><th>Tipe</th><th>Status</th><th>Diunggah</th><th></th></tr></thead><tbody><tr v-for="d in p.documents" :key="d.id"><td class="t-main">{{ d.title }}<div class="t-sub">v{{ d.version }} · {{ d.original_name }}</div></td><td>{{ L.document_types[d.type] }}</td><td><Pill :value="d.status" /></td><td class="t-sub">{{ d.uploader?.name }} · {{ fmt.date(d.created_at) }}</td><td><span class="row" style="justify-content:flex-end"><a :href="`/documents/${d.id}/download`" class="btn sm c-blue">Unduh</a><Link :href="`/documents?history=${d.id}`" class="btn sm ghost c-indigo">Riwayat</Link></span></td></tr><tr v-if="!p.documents.length"><td colspan="5"><div class="empty">Belum ada bukti. Lampirkan saat mencatat progres atau gunakan tombol “Unggah bukti”.</div></td></tr></tbody></table></div></div>
  </div>
  <Modal :show="prog" title="Catat progres" @close="prog = false">
    <p><b>{{ p.code }}</b> · {{ p.title }}</p>
    <Field v-model="pf.pct" type="number" label="Progres (%)" min="0" max="100" required :error="pf.errors.progress" />
    <input v-model.number="pf.pct" type="range" min="0" max="100" step="5" style="width:100%;accent-color:var(--accent)">
    <Field v-model="pf.note" type="textarea" label="Catatan (wajib saat 100%)" :error="pf.errors.note" :rows="3" />
    <div class="field"><label>Bukti pelaksanaan {{ pf.pct >= 100 ? (p.documents.length ? '(opsional, sudah ada bukti)' : '(wajib)') : '(opsional)' }}</label><input type="file" class="inp" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" @change="pf.evidence = $event.target.files[0]"><span v-if="pf.errors.evidence" class="err-msg">{{ pf.errors.evidence }}</span></div>
    <template #footer><button class="btn ghost c-indigo" @click="prog = false">Batal</button><button class="btn c-green" :disabled="pf.processing" @click="saveProg">Simpan</button></template>
  </Modal>
</template>
