<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import Chart from '../../Components/Chart.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ control: Object, plans: Array, can: Object });
const L = usePage().props.labels;
const c = props.control;
const modal = ref(false);
const form = useForm({ tested_at: new Date().toISOString().slice(0, 10), design_eff: c.design_eff || 3, operating_eff: c.operating_eff || 3, note: '', next_test_at: '' });
const save = () => form.post(`/controls/${c.id}/assess`, { onSuccess: () => (modal.value = false) });
const hist = [...c.assessments].reverse();
const opt = { legend: { top: 0 }, xAxis: { type: 'category', data: hist.map((a) => fmt.date(a.tested_at)) }, yAxis: { type: 'value', min: 0, max: 4 }, series: [{ name: 'Desain', type: 'line', data: hist.map((a) => a.design_eff) }, { name: 'Operasi', type: 'line', data: hist.map((a) => a.operating_eff) }] };
const effOpts = Object.entries(L.effectiveness).map(([v, l]) => ({ id: Number(v), name: `${v} · ${l}` }));
</script>
<template>
  <Head :title="c.code" />
  <PageHead :kicker="`Kontrol · ${L.effectiveness[c.overall] || 'belum diuji'}`" :title="`${c.code} · ${c.name}`">
    <Link href="/controls" class="btn ghost c-indigo">‹ Daftar kontrol</Link>
    <Link v-if="can.upload" :href="`/documents?subject_kind=control&subject_id=${c.id}&upload=1`" class="btn c-orange"><Icon name="upload" />Unggah bukti</Link>
    <button v-if="can.write" type="button" class="btn c-green" @click="modal = true"><Icon name="plus" />Catat pengujian</button>
  </PageHead>
  <div class="kpis"><div class="kpi"><div class="k-l">Efektivitas desain</div><div class="k-v" style="font-size:20px">{{ L.effectiveness[c.design_eff] || '—' }}</div></div><div class="kpi"><div class="k-l">Efektivitas operasi</div><div class="k-v" style="font-size:20px">{{ L.effectiveness[c.operating_eff] || '—' }}</div></div><div class="kpi"><div class="k-l">Uji terakhir</div><div class="k-v" style="font-size:20px">{{ fmt.date(c.last_tested_at) }}</div></div><div class="kpi"><div class="k-l">Uji berikutnya</div><div class="k-v" style="font-size:20px" :style="c.next_test_at && new Date(c.next_test_at) < new Date() ? 'color:var(--bad-ink)' : ''">{{ fmt.date(c.next_test_at) }}</div></div></div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Profil kontrol</h3></div><div class="card-b"><dl class="kv"><dt>Tujuan</dt><dd>{{ c.objective || '—' }}</dd><dt>Deskripsi</dt><dd>{{ c.description || '—' }}</dd><dt>Tipe / mode</dt><dd><Pill :value="c.type" /> <Pill :value="c.mode" /></dd><dt>Frekuensi</dt><dd>{{ c.frequency }}</dd><dt>Pemilik</dt><dd>{{ c.owner?.name || '—' }} · {{ c.unit?.name || '' }}</dd><dt>Status</dt><dd><Pill :value="c.active ? 'active' : 'inactive'" /></dd></dl>
      <h4 style="margin:14px 0 6px">Risiko yang dikendalikan</h4><div class="stack" style="gap:6px"><Link v-for="r in c.risks" :key="r.id" :href="`/risks/${r.id}`" class="row between plain" style="padding:8px 10px;border:1px solid var(--line);border-radius:8px"><span><span class="code-link">{{ r.code }}</span> {{ r.name }}</span><Pill kind="level" :value="r.residual_level" /></Link><div v-if="!c.risks.length" class="hint">Belum dikaitkan ke risiko.</div></div></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Riwayat pengujian</h3></div><div class="card-b"><Chart v-if="hist.length" :option="opt" height="180px" /><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Tanggal</th><th>Penguji</th><th>Desain</th><th>Operasi</th><th>Catatan</th></tr></thead><tbody><tr v-for="a in c.assessments" :key="a.id"><td>{{ fmt.date(a.tested_at) }}</td><td class="t-sub">{{ a.tester?.name }}</td><td><Pill :value="L.effectiveness[a.design_eff]" :tone="a.design_eff >= 3 ? 'ok' : a.design_eff === 2 ? 'warn' : 'bad'" /></td><td><Pill :value="L.effectiveness[a.operating_eff]" :tone="a.operating_eff >= 3 ? 'ok' : a.operating_eff === 2 ? 'warn' : 'bad'" /></td><td class="fg2 wrap">{{ a.note }}</td></tr><tr v-if="!c.assessments.length"><td colspan="5"><div class="empty">Belum pernah diuji.</div></td></tr></tbody></table></div></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Action plan risiko terkait</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Rencana</th><th>Risiko</th><th>Tenggat</th><th>Status</th></tr></thead><tbody><tr v-for="p in plans" :key="p.id"><td><Link :href="`/action-plans/${p.id}`" class="code-link">{{ p.code }}</Link></td><td class="t-main wrap">{{ p.title }}<div class="t-sub">{{ p.pic }} · {{ p.progress }}%</div></td><td><Link :href="`/risks/${p.risk_id}`" class="code-link">{{ p.risk }}</Link></td><td>{{ fmt.date(p.due_date) }}</td><td><Pill :value="p.status" /></td></tr><tr v-if="!plans.length"><td colspan="5"><div class="empty">Belum ada action plan pada risiko terkait.</div></td></tr></tbody></table></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Improvement dari kontrol ini</h3><Link v-if="c.improvements.length" :href="`/improvements?subject_type=control&subject_id=${c.id}`" class="btn sm ghost c-indigo">Lihat semua</Link></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Tindakan</th><th>PIC</th><th>Tenggat</th><th>Status</th></tr></thead><tbody><tr v-for="m in c.improvements" :key="m.id"><td><Link :href="`/improvements?subject_type=control&subject_id=${c.id}`" class="code-link">{{ m.code }}</Link></td><td class="t-main wrap">{{ m.title }}</td><td class="t-sub">{{ m.pic?.name }}</td><td>{{ fmt.date(m.due_date) }}</td><td><Pill :value="m.status" /></td></tr><tr v-if="!c.improvements.length"><td colspan="5"><div class="empty">Tidak ada improvement terkait.</div></td></tr></tbody></table></div></div>
    <div class="card" style="grid-column:1/-1"><div class="card-h"><h3>Dokumen bukti</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Dokumen</th><th>Tipe</th><th>Status</th><th>Diunggah</th><th></th></tr></thead><tbody><tr v-for="d in c.documents" :key="d.id"><td class="t-main">{{ d.title }}</td><td>{{ L.document_types[d.type] }}</td><td><Pill :value="d.status" /></td><td class="t-sub">{{ d.uploader?.name }} · {{ fmt.date(d.created_at) }}</td><td><span class="row" style="justify-content:flex-end"><a :href="`/documents/${d.id}/download`" class="btn sm c-blue">Unduh</a><Link :href="`/documents?history=${d.id}`" class="btn sm ghost c-indigo">Riwayat</Link></span></td></tr><tr v-if="!c.documents.length"><td colspan="5"><div class="empty">Belum ada dokumen. Gunakan tombol “Unggah bukti”.</div></td></tr></tbody></table></div></div>
  </div>
  <Modal :show="modal" title="Catat hasil pengujian kontrol" @close="modal = false">
    <div class="form-grid"><Field v-model="form.tested_at" type="date" label="Tanggal uji" required :error="form.errors.tested_at" /><Field v-model="form.next_test_at" type="date" label="Uji berikutnya (opsional)" :error="form.errors.next_test_at" hint="Kosongkan untuk dihitung dari frekuensi" /><Field v-model="form.design_eff" type="select" label="Efektivitas desain" :options="effOpts" empty="" required :error="form.errors.design_eff" /><Field v-model="form.operating_eff" type="select" label="Efektivitas operasi" :options="effOpts" empty="" required :error="form.errors.operating_eff" /><Field v-model="form.note" type="textarea" label="Catatan / temuan" span :rows="3" /></div>
    <template #footer><button class="btn ghost c-indigo" @click="modal = false">Batal</button><button class="btn c-green" :disabled="form.processing" @click="save">Simpan</button></template>
  </Modal>
</template>
