<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import Chart from '../../Components/Chart.vue';
const props = defineProps({ items: Array, summary: Object, can: Object });
// Nama modul pada butir kerangka → halaman aplikasi (hanya yang boleh dibuka peran pengguna)
const role = usePage().props.auth.user.role;
const MOD = [[/risk register|identifikasi|treatment/i, '/risks', 'Risk Register'], [/analisis|matriks/i, '/risks/matrix', 'Peta Risiko'], [/evaluasi/i, '/risks/evaluation', 'Evaluasi'], [/kriteria|taksonomi/i, '/criteria', 'Kriteria'], [/konteks|konsultasi/i, '/context', 'Konteks'],
  [/organisasi/i, '/organization/units', 'Organisasi'], [/objective/i, '/organization/objectives', 'Sasaran'], [/kri|early warning/i, '/kris', 'KRI'], [/monitoring/i, '/risks/residual', 'Monitoring'], [/review/i, '/reviews', 'Review'], [/action plan/i, '/action-plans', 'Action Plan'],
  [/continual|lesson/i, '/improvements', 'Perbaikan'], [/dokumen/i, '/documents', 'Dokumen'], [/insiden/i, '/incidents', 'Insiden'], [/loss/i, '/incidents/losses', 'Loss DB'], [/laporan/i, '/reports', 'Laporan'], [/notifikasi/i, '/alerts', 'Peringatan'], [/approval/i, '/approvals', 'Persetujuan'],
  [/audit trail/i, '/admin/audit', 'Audit Trail', ['super_admin', 'risk_admin', 'risk_manager', 'auditor']], [/executive/i, '/dashboard/executive', 'Executive', ['super_admin', 'risk_admin', 'risk_manager', 'management', 'auditor']]];
const links = (m) => MOD.filter(([re, , , roles]) => re.test(m || '') && (!roles || roles.includes(role))).map(([, href, label]) => ({ href, label }));
const groups = [['principle', 'Prinsip (klausul 4)'], ['framework', 'Kerangka kerja (klausul 5)'], ['process', 'Proses (klausul 6)']];
const edit = ref(null);
const form = useForm({ status: 'partial', score: 0, note: '' });
const open = (i) => { edit.value = i; form.status = i.status; form.score = i.score ?? 0; form.note = i.note || ''; form.clearErrors(); };
const save = () => form.put(`/framework/${edit.value.id}`, { preserveScroll: true, onSuccess: () => (edit.value = null) });
const radar = { radar: { indicator: props.items.map((i) => ({ name: i.clause, max: 100 })), radius: '65%' }, series: [{ type: 'radar', data: [{ value: props.items.map((i) => i.score || 0), name: 'Skor kepatuhan', areaStyle: { opacity: .25 } }] }], tooltip: { trigger: 'item' } };
</script>
<template>
  <Head title="Kerangka ISO 31000" />
  <PageHead kicker="Tata Kelola" title="Kepatuhan kerangka ISO 31000:2018" sub="Penilaian mandiri terhadap 8 prinsip, 6 komponen kerangka kerja, dan 8 langkah proses; setiap butir dipetakan ke modul aplikasi." />
  <div class="kpis"><div v-for="g in groups" :key="g[0]" class="kpi"><div class="k-l">{{ g[1] }}</div><div class="k-v">{{ summary[g[0]].pct }}%</div><div class="k-s">{{ summary[g[0]].met }} terpenuhi · {{ summary[g[0]].partial }} sebagian · {{ summary[g[0]].unmet }} belum</div><div class="meter"><i :style="`width:${summary[g[0]].pct}%`"></i></div></div></div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Profil kepatuhan</h3></div><div class="card-b"><Chart :option="radar" height="320px" /></div></div>
    <div class="card" style="grid-column:span 8"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Klausul</th><th>Butir</th><th>Modul</th><th class="num">Skor</th><th>Status</th><th></th></tr></thead><tbody>
      <template v-for="g in groups" :key="g[0]"><tr><td colspan="6" style="background:var(--surface-2);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.05em">{{ g[1] }}</td></tr>
      <tr v-for="i in items.filter((x) => x.group === g[0])" :key="i.id"><td class="mono">{{ i.clause }}</td><td class="t-main wrap">{{ i.title }}<div class="t-sub">{{ i.note }}</div></td><td class="t-sub"><span v-if="!links(i.module).length">{{ i.module }}</span><span v-else class="row" style="gap:4px;flex-wrap:wrap"><Link v-for="l in links(i.module)" :key="l.href" :href="l.href" class="pill run">{{ l.label }}</Link></span></td><td class="num"><div class="meter" style="width:70px;display:inline-block;vertical-align:middle;margin-right:6px"><i :style="`width:${i.score || 0}%`"></i></div>{{ i.score ?? '—' }}</td><td><Pill :value="i.status" /></td><td><button v-if="can.write" type="button" class="btn sm c-blue" @click="open(i)">Nilai</button></td></tr></template>
    </tbody></table></div></div>
  </div>
  <Modal :show="!!edit" :title="`Penilaian ${edit?.clause} · ${edit?.title}`" @close="edit = null">
    <div class="stack"><Field v-model="form.status" type="select" label="Status" :options="{ met: 'Terpenuhi', partial: 'Sebagian', unmet: 'Belum terpenuhi' }" empty="" required /><Field v-model="form.score" type="number" label="Skor (0–100)" min="0" max="100" /><Field v-model="form.note" type="textarea" label="Catatan / bukti" :rows="3" /></div>
    <template #footer><button class="btn ghost c-indigo" @click="edit = null">Batal</button><button class="btn c-green" :disabled="form.processing" @click="save">Simpan</button></template>
  </Modal>
</template>
