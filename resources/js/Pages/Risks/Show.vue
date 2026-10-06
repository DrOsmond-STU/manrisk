<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import Heatmap from '../../Components/Heatmap.vue';
import Chart from '../../Components/Chart.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ risk: Object, projected: Object, statement: String, audit: Array, criteria: Object, can: Object });
const L = usePage().props.labels;
const tab = ref((typeof window !== 'undefined' && window.location.hash.slice(1)) || 'summary');
import { watch } from 'vue';
watch(tab, (t) => { try { history.replaceState(null, '', '#' + t); } catch (e) { /* abaikan */ } });
const modal = ref(null);
const r = props.risk;
const submitForm = useForm({ note: '' });
const closeForm = useForm({ reason: '' });
const lessonForm = useForm({ text: '' });
const planForm = useForm({ risk_id: r.id, title: '', description: '', pic_id: r.owner_id, priority: 'medium', start_date: '', due_date: '', budget: null, expected_dl: 1, expected_di: 0 });
const docForm = useForm({ file: null, title: '', type: 'evidence', subject_kind: 'risk', subject_id: r.id, status: 'draft', expires_at: '' });
const reviewForm = useForm({ risk_id: r.id, period_type: 'quarterly', period: `${new Date().getFullYear()}-Q${Math.ceil((new Date().getMonth() + 1) / 3)}`, note: '', decision: 'continue' });
const doSubmit = () => submitForm.post(`/risks/${r.id}/submit`, { onSuccess: () => (modal.value = null) });
const doClose = () => closeForm.post(`/risks/${r.id}/close`, { onSuccess: () => (modal.value = null) });
const doLesson = () => lessonForm.post(`/risks/${r.id}/lessons`, { onSuccess: () => { lessonForm.reset(); modal.value = null; } });
const doPlan = () => planForm.post('/action-plans', { onSuccess: () => { planForm.reset('title', 'description', 'due_date'); modal.value = null; } });
const doDoc = () => docForm.post('/documents', { forceFormData: true, onSuccess: () => { docForm.reset(); modal.value = null; } });
const doReview = () => reviewForm.post('/reviews', { onSuccess: () => (modal.value = null) });
const periods = r.snapshots.map((s) => s.period);
const trendOpt = { xAxis: { type: 'category', data: periods.map(fmt.period) }, yAxis: { type: 'value', min: 0, max: 25 }, series: [{ name: 'Residual', type: 'line', smooth: true, data: r.snapshots.map((s) => s.residual_score), areaStyle: { opacity: .12 }, lineStyle: { width: 3 } }, { name: 'Inheren', type: 'line', data: r.snapshots.map((s) => s.inherent_score), lineStyle: { type: 'dashed' } }], legend: { top: 0 } };
const users = usePage().props.auth.user;
const countsFor = (l, i) => ({ [`${l}-${i}`]: 1 });
const VF = { name: 'Nama', cause: 'Penyebab', event: 'Peristiwa', impact: 'Dampak', existing_controls: 'Kontrol', treatment: 'Treatment', inherent_score: 'Skor inheren', residual_score: 'Skor residual', target_score: 'Skor target', residual_level: 'Level', evaluation: 'Evaluasi' };
const diff = (v) => { const vs = r.versions; const i = vs.findIndex((x) => x.id === v.id); const prev = vs[i + 1]; if (!prev) return []; const a = prev.snapshot || {}, b = v.snapshot || {}; return Object.keys(VF).filter((k) => (a[k] ?? '') !== (b[k] ?? '') && (k in a || k in b)).map((k) => ({ k: VF[k], from: a[k], to: b[k] })); };
</script>
<template>
  <Head :title="r.code" />
  <PageHead :kicker="`Risk Register · ${r.unit?.name}`" :title="`${r.code} · ${r.name}`">
    <Pill :value="r.status" /><Pill kind="level" :value="r.residual_level" />
    <Link v-if="can.update && r.status !== 'pending'" :href="`/risks/${r.id}/edit`" class="btn c-blue"><Icon name="refresh" />Ubah / nilai ulang</Link>
    <button v-if="can.submit" type="button" class="btn c-green" @click="modal = 'submit'"><Icon name="send" />Ajukan persetujuan</button>
    <button v-if="can.close && r.status !== 'pending'" type="button" class="btn c-amber" @click="modal = 'close'"><Icon name="lock" />Ajukan penutupan</button>
    <ConfirmButton v-if="can.delete && r.status === 'draft'" :href="`/risks/${r.id}`" cls="btn c-red" label="Hapus draft" />
  </PageHead>
  <div v-if="r.status === 'pending'" class="alert-box warn"><Icon name="inbox" /><span>Risiko sedang menunggu persetujuan ({{ r.approvals?.[0]?.code }}); perubahan dikunci sampai keputusan diberikan.</span></div>
  <div class="kpis">
    <div class="kpi"><div class="k-l">Inheren</div><div class="k-v">{{ r.inherent_score }}</div><div class="k-s">L{{ r.inherent_l }} × I{{ r.inherent_i }}</div></div>
    <div class="kpi lvl" :style="`--c:var(--lv-${{ low: 'l', medium: 'm', high: 'h', very_high: 'vh' }[r.residual_level]})`"><div class="k-l"><i class="sw"></i>Residual</div><div class="k-v">{{ r.residual_score }}</div><div class="k-s">L{{ r.residual_l }} × I{{ r.residual_i }} · {{ L.levels[r.residual_level] }}</div></div>
    <div class="kpi"><div class="k-l">Target</div><div class="k-v">{{ r.target_score }}</div><div class="k-s">L{{ r.target_l }} × I{{ r.target_i }}</div></div>
    <div class="kpi"><div class="k-l">Proyeksi setelah action plan</div><div class="k-v">{{ projected.score }}</div><div class="k-s">L{{ projected.l }} × I{{ projected.i }} · {{ L.levels[projected.level] }}</div></div>
    <div class="kpi"><div class="k-l">Evaluasi</div><div class="k-v" style="font-size:18px"><Pill kind="evaluation" :value="r.evaluation" /></div><div class="k-s">appetite {{ r.category?.appetite }} · tolerance {{ r.category?.tolerance }}</div></div>
    <div class="kpi"><div class="k-l">Tren</div><div class="k-v" style="font-size:18px"><Pill :value="r.trend" :tone="r.trend === 'up' ? 'bad' : r.trend === 'down' ? 'ok' : ''" /></div><div class="k-s">skor sebelumnya {{ r.previous_score ?? '—' }} · versi {{ r.version }}</div></div>
  </div>
  <div class="tabs"><button v-for="t in [['summary', 'Ringkasan'], ['plans', `Action plan`, r.action_plans.length], ['controls', 'Kontrol', r.controls.length], ['kris', 'KRI', r.kris.length], ['incidents', 'Insiden', r.incidents.length], ['reviews', 'Review', r.reviews.length], ['docs', 'Dokumen', r.documents.length], ['versions', 'Versi & persetujuan', r.versions.length], ['audit', 'Audit trail']]" :key="t[0]" type="button" :class="{ on: tab === t[0] }" @click="tab = t[0]">{{ t[1] }}<span v-if="t[2] !== undefined" class="c">{{ t[2] }}</span></button></div>

  <div v-if="tab === 'summary'" class="s-grid">
    <div class="card" style="grid-column:span 7"><div class="card-h"><h3>Pernyataan risiko</h3></div><div class="card-b stack">
      <p style="font-size:15px;line-height:1.6">{{ statement }}</p>
      <dl class="kv"><dt>Kategori</dt><dd>{{ r.category?.name }}</dd><dt>Sumber</dt><dd><Pill :value="r.source_type" /> {{ L.source_kinds[r.source_kind] }}</dd><dt>Sasaran strategis</dt><dd>{{ r.objective ? `${r.objective.code} · ${r.objective.name}` : '—' }}</dd><dt>Proses bisnis</dt><dd>{{ r.process?.name || '—' }}</dd><dt>Risk owner</dt><dd>{{ r.owner?.name }} <span class="muted">({{ L.roles[r.owner?.role] }})</span></dd><dt>Kontrol yang ada</dt><dd>{{ r.existing_controls || '—' }}</dd><dt>Treatment</dt><dd><b>{{ L.treatments[r.treatment] }}</b> — {{ r.treatment_note || 'tanpa catatan' }}</dd><dt>Target selesai</dt><dd>{{ fmt.date(r.due_date) }}</dd><dt>Dibuat</dt><dd>{{ fmt.datetime(r.created_at) }} oleh {{ r.creator?.name }}</dd><dt v-if="r.closed_at">Ditutup</dt><dd v-if="r.closed_at">{{ fmt.datetime(r.closed_at) }} — {{ r.closed_reason }}</dd></dl>
      <div v-if="r.lessons.length"><h4 style="margin:6px 0">Lesson learned</h4><div class="stack" style="gap:6px"><div v-for="l in r.lessons" :key="l.id" class="alert-box info"><Icon name="book" /><span>{{ l.text }} <span class="hint">— {{ l.creator?.name }}, {{ fmt.date(l.created_at) }}</span></span></div></div></div>
      <div class="row"><button v-if="can.update" type="button" class="btn sm c-cyan" @click="modal = 'lesson'"><Icon name="plus" />Lesson learned</button><Link v-if="$page.props.auth.user.role !== 'auditor'" :href="`/ai?risk=${r.id}`" class="btn sm c-violet"><Icon name="spark" />Tanya AI</Link></div>
    </div></div>
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Posisi pada matriks</h3></div><div class="card-b stack">
      <Heatmap :counts="{ [`${r.inherent_l}-${r.inherent_i}`]: 'I', [`${r.residual_l}-${r.residual_i}`]: 'R', [`${r.target_l}-${r.target_i}`]: 'T' }" :matrix="criteria?.matrix" :selected="`${r.residual_l}-${r.residual_i}`" compact />
      <p class="hint">I = inheren, R = residual, T = target.</p>
      <Chart :option="trendOpt" height="180px" />
    </div></div>
  </div>

  <div v-if="tab === 'plans'" class="card"><div class="card-h"><h3>Action plan mitigasi</h3><button v-if="can.plan && r.status !== 'closed'" type="button" class="btn sm c-green" @click="modal = 'plan'"><Icon name="plus" />Tambah action plan</button></div>
    <div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Rencana</th><th>PIC</th><th>Prioritas</th><th>Tenggat</th><th>Progres</th><th class="num">ΔL/ΔI</th></tr></thead><tbody>
      <tr v-for="p in r.action_plans" :key="p.id" class="click" @click="router.visit(`/action-plans/${p.id}`)"><td class="code-link">{{ p.code }}</td><td class="wrap t-main">{{ p.title }}</td><td class="t-sub">{{ p.pic?.name }}</td><td><Pill :value="p.priority" map="priorities" :tone="p.priority === 'critical' ? 'bad' : p.priority === 'high' ? 'warn' : ''" /></td><td :class="{ 'fg2': true }">{{ fmt.date(p.due_date) }}<span v-if="p.cancelled_at" class="hint"> (dibatalkan)</span></td><td><div class="prog"><div class="bar"><i :class="{ done: p.progress >= 100, late: p.progress < 100 && new Date(p.due_date) < new Date() }" :style="`width:${p.progress}%`"></i></div><span>{{ p.progress }}%</span></div></td><td class="num mono">-{{ p.expected_dl }} / -{{ p.expected_di }}</td></tr>
      <tr v-if="!r.action_plans.length"><td colspan="7"><div class="empty">Belum ada action plan.</div></td></tr></tbody></table></div></div>

  <div v-if="tab === 'controls'" class="card"><div class="card-h"><h3>Kontrol terkait</h3><Link href="/controls" class="btn sm ghost c-indigo">Kelola kontrol</Link></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Kontrol</th><th>Tipe</th><th>Pemilik</th><th class="num">Desain</th><th class="num">Operasi</th><th>Uji terakhir</th></tr></thead><tbody>
    <tr v-for="c in r.controls" :key="c.id" class="click" @click="router.visit(`/controls/${c.id}`)"><td class="code-link">{{ c.code }}</td><td class="t-main wrap">{{ c.name }}</td><td><Pill :value="c.type" /></td><td class="t-sub">{{ c.owner?.name }}</td><td class="num">{{ L.effectiveness[c.design_eff] || '—' }}</td><td class="num">{{ L.effectiveness[c.operating_eff] || '—' }}</td><td>{{ fmt.date(c.last_tested_at) }}</td></tr>
    <tr v-if="!r.controls.length"><td colspan="7"><div class="empty">Belum ada kontrol dikaitkan. Ubah risiko untuk mengaitkan kontrol.</div></td></tr></tbody></table></div></div>

  <div v-if="tab === 'kris'" class="card"><div class="card-h"><h3>Key Risk Indicator</h3><Link href="/kris" class="btn sm ghost c-indigo">Kelola KRI</Link></div><div class="card-b kri-grid">
    <div v-for="k in r.kris" :key="k.id" class="kri"><div class="kh"><div><div class="mono muted" style="font-size:11px">{{ k.code }}</div><div class="kn">{{ k.name }}</div></div><Pill :value="k.status" /></div><div class="kval">{{ fmt.num(k.last_value, k.decimals) }} <small>{{ k.unit }}</small></div><div class="th"><span>Waspada {{ k.threshold_warn }}</span><span>Kritis {{ k.threshold_crit }}</span></div></div>
    <div v-if="!r.kris.length" class="empty" style="grid-column:1/-1">Belum ada KRI untuk risiko ini.</div></div></div>

  <div v-if="tab === 'incidents'" class="card"><div class="card-h"><h3>Insiden terkait</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Tanggal</th><th>Insiden</th><th class="num">Kerugian</th><th>Status</th></tr></thead><tbody>
    <tr v-for="i in r.incidents" :key="i.id" class="click" @click="router.visit(`/incidents/${i.id}`)"><td class="code-link">{{ i.code }}</td><td>{{ fmt.date(i.occurred_at) }}</td><td class="t-main wrap">{{ i.title }}</td><td class="num">{{ fmt.short(i.loss_amount) }}</td><td><Pill :value="i.status" /></td></tr>
    <tr v-if="!r.incidents.length"><td colspan="5"><div class="empty">Tidak ada insiden tercatat.</div></td></tr></tbody></table></div></div>

  <div v-if="tab === 'reviews'" class="card"><div class="card-h"><h3>Review berkala</h3><button v-if="can.review && r.status !== 'closed'" type="button" class="btn sm c-teal" @click="modal = 'review'"><Icon name="plus" />Catat review</button></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Periode</th><th class="num">Skor sebelum → kini</th><th>Tren</th><th>Keputusan</th><th>Catatan</th><th>Reviewer</th></tr></thead><tbody>
    <tr v-for="v in r.reviews" :key="v.id"><td class="mono">{{ v.period }}</td><td class="num mono">{{ v.previous_score ?? '—' }} → {{ v.current_score }}</td><td><Pill :value="v.trend" :tone="v.trend === 'up' ? 'bad' : v.trend === 'down' ? 'ok' : ''" /></td><td><Pill :value="v.decision" /></td><td class="wrap fg2">{{ v.note }}</td><td class="t-sub">{{ v.reviewer?.name }}<br>{{ fmt.date(v.signed_at) }}</td></tr>
    <tr v-if="!r.reviews.length"><td colspan="6"><div class="empty">Belum ada review.</div></td></tr></tbody></table></div></div>

  <div v-if="tab === 'docs'" class="card"><div class="card-h"><h3>Dokumen & bukti</h3><button v-if="can.document" type="button" class="btn sm c-orange" @click="modal = 'doc'"><Icon name="upload" />Unggah</button></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Dokumen</th><th>Tipe</th><th>Status</th><th>Diunggah</th><th></th></tr></thead><tbody>
    <tr v-for="d in r.documents" :key="d.id"><td class="t-main wrap">{{ d.title }}<div class="t-sub">{{ d.original_name }} · {{ (d.size / 1024).toFixed(0) }} KB · v{{ d.version }}</div></td><td>{{ L.document_types[d.type] }}</td><td><Pill :value="d.status" /></td><td class="t-sub">{{ d.uploader?.name }}<br>{{ fmt.date(d.created_at) }}</td><td><a :href="`/documents/${d.id}/download`" class="btn sm c-blue">Unduh</a></td></tr>
    <tr v-if="!r.documents.length"><td colspan="5"><div class="empty">Belum ada dokumen.</div></td></tr></tbody></table></div></div>

  <div v-if="tab === 'versions'" class="s-grid">
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Riwayat versi penilaian</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Versi</th><th class="num">Inheren</th><th class="num">Residual</th><th class="num">Target</th><th>Catatan</th><th>Oleh</th><th>Disetujui</th></tr></thead><tbody>
      <tr v-for="v in r.versions" :key="v.id"><td class="mono">v{{ v.version }}</td><td class="num mono">{{ v.inherent_l * v.inherent_i }}</td><td class="num mono">{{ v.residual_l * v.residual_i }}</td><td class="num mono">{{ v.target_l * v.target_i }}</td><td class="wrap fg2">{{ v.note }}<div v-for="d in diff(v)" :key="d.k" class="hint"><b>{{ d.k }}</b>: {{ d.from ?? '∅' }} → {{ d.to }}</div></td><td class="t-sub">{{ v.creator?.name }}<br>{{ fmt.date(v.created_at) }}</td><td class="t-sub">{{ v.approved_at ? `${v.approver?.name || ''} ${fmt.date(v.approved_at)}` : '—' }}</td></tr></tbody></table></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Pengajuan persetujuan</h3><Link href="/approvals" class="btn sm ghost c-indigo">Kotak persetujuan</Link></div><div class="card-b stack">
      <div v-for="a in r.approvals" :key="a.id" class="alert-box" :class="{ ok: a.status === 'approved', bad: a.status === 'rejected', warn: a.status === 'pending' || a.status === 'revision' }"><Icon name="inbox" /><div style="flex:1"><div class="row between"><b>{{ a.code }} · {{ L.approval_types[a.type] }}</b><Pill :value="a.status" /></div><div class="hint">Diajukan {{ a.requester?.name }}, {{ fmt.datetime(a.created_at) }} — {{ a.note }}</div>
        <div class="steps" style="margin-top:6px"><div v-for="s in a.steps" :key="s.id" class="st" :class="{ done: s.action === 'approve', bad: s.action && s.action !== 'approve', cur: !s.action && s.step_no === a.current_step && a.status === 'pending' }"><b>{{ s.step_no }}. {{ L.roles[s.role] }}</b><br>{{ s.action ? `${{ approve: 'Disetujui', revise: 'Revisi', reject: 'Ditolak' }[s.action]} · ${s.approver?.name || ''}` : (s.step_no === a.current_step && a.status === 'pending' ? 'Menunggu' : '—') }}<div v-if="s.note" class="hint">{{ s.note }}</div></div></div></div></div>
      <div v-if="!r.approvals.length" class="empty">Belum ada pengajuan.</div></div></div>
  </div>

  <div v-if="tab === 'audit'" class="card"><div class="card-h"><h3>Audit trail risiko</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Perubahan</th></tr></thead><tbody>
    <tr v-for="a in audit" :key="a.id"><td class="t-sub" style="white-space:nowrap">{{ fmt.datetime(a.created_at) }}</td><td>{{ a.user?.name || 'sistem' }}</td><td><span class="pill run">{{ a.action }}</span></td><td class="wrap"><span v-for="(v, k) in (a.changes || {})" :key="k" class="hint" style="display:block"><b>{{ k }}</b>: {{ typeof v[0] === 'object' ? JSON.stringify(v[0]) : (v[0] ?? '∅') }} → {{ typeof v[1] === 'object' ? JSON.stringify(v[1]) : v[1] }}</span></td></tr></tbody></table></div></div>

  <Modal :show="modal === 'submit'" title="Ajukan persetujuan" @close="modal = null">
    <p>Risiko <b>{{ r.code }}</b> akan diajukan sebagai <b>{{ r.treatment === 'retain' ? 'penerimaan risiko' : 'risiko baru' }}</b>. Rantai persetujuan: Risk Owner → Risk Manager{{ r.residual_score >= 16 ? ' → Management (skor ≥ 16)' : '' }}; tahap yang sama dengan peran Anda dilewati.</p>
    <Field v-model="submitForm.note" type="textarea" label="Catatan untuk penyetuju" :error="submitForm.errors.note || submitForm.errors.approval" />
    <template #footer><button class="btn ghost c-indigo" @click="modal = null">Batal</button><button class="btn c-green" :disabled="submitForm.processing" @click="doSubmit">Ajukan</button></template>
  </Modal>
  <Modal :show="modal === 'close'" title="Ajukan penutupan risiko" @close="modal = null">
    <p>Penutupan memerlukan persetujuan. Seluruh action plan harus selesai atau dibatalkan.</p>
    <Field v-model="closeForm.reason" type="textarea" label="Alasan penutupan" required :error="closeForm.errors.reason || closeForm.errors.approval" />
    <template #footer><button class="btn ghost c-indigo" @click="modal = null">Batal</button><button class="btn c-amber" :disabled="closeForm.processing" @click="doClose">Ajukan penutupan</button></template>
  </Modal>
  <Modal :show="modal === 'lesson'" title="Lesson learned" @close="modal = null">
    <Field v-model="lessonForm.text" type="textarea" label="Pembelajaran" required :error="lessonForm.errors.text" :rows="4" />
    <template #footer><button class="btn ghost c-indigo" @click="modal = null">Batal</button><button class="btn c-green" :disabled="lessonForm.processing" @click="doLesson">Simpan</button></template>
  </Modal>
  <Modal :show="modal === 'plan'" title="Tambah action plan" wide @close="modal = null">
    <div class="form-grid">
      <Field v-model="planForm.title" label="Judul rencana" required span :error="planForm.errors.title" maxlength="255" />
      <Field v-model="planForm.description" type="textarea" label="Uraian" span :rows="2" />
      <Field v-model="planForm.pic_id" type="select" label="PIC" :options="$page.props.auth.user ? [{ id: r.owner_id, name: r.owner?.name }] : []" :error="planForm.errors.pic_id" hint="PIC lain dapat dipilih dari halaman Action Plan." />
      <Field v-model="planForm.priority" type="select" label="Prioritas" :options="L.priorities" empty="" required />
      <Field v-model="planForm.start_date" type="date" label="Mulai" /><Field v-model="planForm.due_date" type="date" label="Tenggat" required :error="planForm.errors.due_date" />
      <Field v-model="planForm.budget" type="number" label="Anggaran (Rp)" min="0" /><div class="row" style="gap:8px"><Field v-model="planForm.expected_dl" type="number" label="Penurunan L" min="0" max="4" style="flex:1" /><Field v-model="planForm.expected_di" type="number" label="Penurunan I" min="0" max="4" style="flex:1" /></div>
    </div>
    <template #footer><button class="btn ghost c-indigo" @click="modal = null">Batal</button><button class="btn c-green" :disabled="planForm.processing" @click="doPlan">Simpan</button></template>
  </Modal>
  <Modal :show="modal === 'doc'" title="Unggah dokumen / bukti" @close="modal = null">
    <div class="stack">
      <Field v-model="docForm.title" label="Judul dokumen" required :error="docForm.errors.title" />
      <Field v-model="docForm.type" type="select" label="Tipe" :options="L.document_types" empty="" required />
      <div class="field"><label>Berkas (PDF/DOC/XLS/JPG/PNG, maks 25 MB)</label><input type="file" class="inp" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" @change="docForm.file = $event.target.files[0]"><span v-if="docForm.errors.file" class="err-msg">{{ docForm.errors.file }}</span></div>
      <div class="row" style="gap:8px"><Field v-model="docForm.status" type="select" label="Status" :options="{ draft: 'Draft', review: 'Review', approved: 'Disetujui' }" empty="" style="flex:1" /><Field v-model="docForm.expires_at" type="date" label="Kedaluwarsa" style="flex:1" /></div>
    </div>
    <template #footer><button class="btn ghost c-indigo" @click="modal = null">Batal</button><button class="btn c-orange" :disabled="docForm.processing || !docForm.file" @click="doDoc">Unggah</button></template>
  </Modal>
  <Modal :show="modal === 'review'" title="Catat hasil review" @close="modal = null">
    <div class="stack">
      <div class="row" style="gap:8px"><Field v-model="reviewForm.period_type" type="select" label="Jenis" :options="{ monthly: 'Bulanan', quarterly: 'Triwulanan', semester: 'Semesteran', annual: 'Tahunan', adhoc: 'Ad hoc' }" empty="" style="flex:1" /><Field v-model="reviewForm.period" label="Periode" required style="flex:1" :error="reviewForm.errors.period" /></div>
      <Field v-model="reviewForm.decision" type="select" label="Keputusan" :options="{ continue: 'Lanjutkan treatment', change_treatment: 'Ubah treatment', close: 'Rekomendasi tutup (butuh persetujuan)', escalate: 'Eskalasi ke manajemen' }" empty="" required />
      <Field v-model="reviewForm.note" type="textarea" label="Catatan review" :rows="4" :error="reviewForm.errors.note" />
    </div>
    <template #footer><button class="btn ghost c-indigo" @click="modal = null">Batal</button><button class="btn c-teal" :disabled="reviewForm.processing" @click="doReview">Simpan review</button></template>
  </Modal>
</template>
