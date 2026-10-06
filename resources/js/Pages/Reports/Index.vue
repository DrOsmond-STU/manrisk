<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import { Head } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
import Pill from '../../Components/Pill.vue';
import { fmt, toast } from '../../lib/format';
const props = defineProps({ types: Object, formats: Object, units: Array, categories: Array, owners: Array, initial: Object, jobs: Array, schedules: Array });
const labels = usePage().props.labels;
const sf = useForm({ type: 'kri', format: 'pdf', frequency: 'monthly', day: 1, recipients: [], unit_id: '' });
const rcpt = ref('');
watch(() => sf.type, (t) => { if (!props.formats[t].includes(sf.format)) sf.format = props.formats[t][0]; });
watch(() => sf.frequency, (f) => { if (f === 'weekly' && sf.day > 7) sf.day = 1; });
const saveSchedule = () => sf.transform((d) => ({ ...d, recipients: rcpt.value.split(/[,;\s]+/).filter(Boolean) })).post('/reports/schedules', { preserveScroll: true, onSuccess: () => { rcpt.value = ''; } });
const ym = (d) => d.toISOString().slice(0, 7);
// filter risiko sama dengan Risk Register; nilai awal dapat datang dari query string (/reports?type=register&unit_id=..&level=high,very_high)
const init = props.initial || {};
const split = (v) => (v ? String(v).split(',') : []);
const blank = { unit_id: '', category_id: '', owner_id: '', objective_id: '', process_id: '', status: '', q: '', level: [], evaluation: [] };
const form = ref({ type: props.types[init.type] ? init.type : 'register', format: 'xlsx', year: new Date().getFullYear(), include_closed: false, limit: 20, period_from: ym(new Date(Date.now() - 90 * 864e5)), period_to: ym(new Date()),
  ...blank, ...Object.fromEntries(Object.entries(init).filter(([k]) => k in blank)), level: split(init.level), evaluation: split(init.evaluation) });
const syncFormat = (t) => { if (!props.formats[t].includes(form.value.format)) form.value.format = props.formats[t][0]; };
syncFormat(form.value.type); watch(() => form.value.type, syncFormat);
const showFilters = ref(Object.keys(init).some((k) => k !== 'type'));
const opt = (list, id) => list.find((o) => String(o.id) === String(id))?.name?.replace(/^(— )+/, '') || `#${id}`;
const applied = computed(() => { const f = form.value; return [
  f.unit_id && ['unit_id', `Unit: ${opt(props.units, f.unit_id)} + sub-unit`], f.category_id && ['category_id', `Kategori: ${opt(props.categories, f.category_id)}`], f.owner_id && ['owner_id', `Pemilik: ${opt(props.owners, f.owner_id)}`],
  f.level.length && ['level', `Level: ${f.level.map((l) => labels.levels[l]).join(', ')}`], f.evaluation.length && ['evaluation', `Evaluasi: ${f.evaluation.map((e) => labels.evaluations[e]).join(', ')}`],
  f.status && ['status', `Status: ${f.status === 'active' ? 'Aktif' : labels.risk_statuses[f.status]}`], f.objective_id && ['objective_id', `Sasaran #${f.objective_id}`], f.process_id && ['process_id', `Proses #${f.process_id}`], f.q && ['q', `Kata kunci: “${f.q}”`],
].filter(Boolean); });
const clearFilter = (k) => { form.value[k] = Array.isArray(blank[k]) ? [] : ''; };
const payload = () => { const f = { ...form.value, level: form.value.level.join(','), evaluation: form.value.evaluation.join(',') }; return Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null)); };
const busy = ref(false);
const generate = async () => {
  busy.value = true;
  try {
    const token = document.querySelector('meta[name=csrf-token]').content;
    const res = await fetch('/reports', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/octet-stream, application/json' }, body: JSON.stringify(payload()) });
    if (!res.ok || !(res.headers.get('Content-Disposition') || '').includes('attachment')) { toast('Gagal membuat laporan (' + res.status + ').', 'bad'); return; }
    const blob = await res.blob(); const cd = res.headers.get('Content-Disposition') || ''; const m = cd.match(/filename="?([^";]+)"?/);
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = m ? m[1] : `laporan.${form.value.format}`; a.click(); URL.revokeObjectURL(a.href);
    toast('Laporan diunduh.', 'ok'); setTimeout(() => window.location.reload(), 800);
  } finally { busy.value = false; }
};
// ulangi laporan dari riwayat dengan parameter yang sama (berkas tidak disimpan di server, jadi dibuat ulang dari data terkini)
const rerun = (j) => { const p = j.params || {}; form.value = { ...form.value, ...blank, type: j.type, format: j.format, ...p, level: split(p.level), evaluation: split(p.evaluation), include_closed: !!p.include_closed }; showFilters.value = applied.value.length > 0; generate(); };
const desc = { top: '10/20 risiko prioritas dengan perlakuan dan action plan.', residual: 'Perjalanan skor inheren → residual → proyeksi mitigasi → target per risiko.', trend: 'Perbandingan skor residual & distribusi level antardua periode snapshot.', overdue: 'Action plan yang lewat tenggat per unit/PIC dengan lama keterlambatan.', register: 'Seluruh risiko dengan penyebab, peristiwa, dampak, skor inheren/residual/target, evaluasi, treatment, dan status.', profile: 'Ringkasan profil risiko: distribusi level, appetite per kategori, status action plan, KRI, insiden.', heatmap: 'Daftar risiko tersusun per sel matriks.', action_plans: 'Status seluruh action plan beserta PIC, tenggat, progres.', controls: 'Daftar kontrol dan hasil efektivitas desain/operasi.', kri: 'Nilai terkini dan status seluruh KRI.', incidents: 'Insiden dan kerugian pada tahun yang dipilih.', executive: 'Laporan eksekutif untuk pimpinan: ringkasan, appetite, risiko utama, rekomendasi.' };
</script>
<template>
  <Head title="Laporan" />
  <PageHead kicker="Pelaporan" title="Laporan & ekspor" sub="Hasilkan laporan PDF untuk pimpinan/auditor atau Excel untuk analisis lanjutan. Setiap ekspor dicatat pada audit trail." />
  <div class="s-grid">
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Buat laporan</h3></div><div class="card-b stack">
      <Field v-model="form.type" type="select" label="Jenis laporan" :options="types" empty="" required />
      <p class="hint">{{ desc[form.type] }}</p>
      <div class="row" style="gap:8px"><Field v-model="form.format" type="select" label="Format" :options="Object.fromEntries(formats[form.type].map((f) => [f, f === 'pdf' ? 'PDF' : 'Excel (XLSX)']))" empty="" style="flex:1" /><Field v-model="form.unit_id" type="select" label="Unit (termasuk sub-unit)" :options="units" empty="Semua unit" style="flex:1" /></div>
      <div v-if="applied.length" class="row" style="gap:6px;flex-wrap:wrap;align-items:center"><span class="hint">Filter diterapkan:</span><span v-for="[k, l] in applied" :key="k" class="pill run">{{ l }} <button type="button" class="btn sm ghost" style="padding:0 4px" :title="`Hapus filter ${l}`" @click="clearFilter(k)">×</button></span></div>
      <button type="button" class="btn sm ghost c-indigo" style="align-self:flex-start" @click="showFilters = !showFilters"><Icon name="search" />{{ showFilters ? 'Sembunyikan filter risiko' : 'Filter risiko lainnya' }}</button>
      <template v-if="showFilters">
        <div class="row" style="gap:8px"><Field v-model="form.category_id" type="select" label="Kategori" :options="categories" empty="Semua" style="flex:1" /><Field v-model="form.owner_id" type="select" label="Pemilik risiko" :options="owners" empty="Semua" style="flex:1" /></div>
        <div class="row" style="gap:8px"><Field v-model="form.status" type="select" label="Status risiko" :options="{ active: 'Aktif (belum ditutup)', ...labels.risk_statuses }" empty="Bawaan (aktif)" style="flex:1" /><Field v-model="form.q" label="Kata kunci" placeholder="kode / nama / pernyataan" maxlength="100" style="flex:1" /></div>
        <Field v-model="form.level" type="multi" label="Level residual" :options="labels.levels" /><Field v-model="form.evaluation" type="multi" label="Evaluasi" :options="labels.evaluations" />
      </template>
      <div class="row" style="gap:8px"><Field v-if="form.type === 'incidents'" v-model="form.year" type="number" label="Tahun" style="flex:1" /><Field v-if="form.type === 'top'" v-model="form.limit" type="select" label="Jumlah" :options="{ 10: '10 teratas', 20: '20 teratas' }" empty="" style="flex:1" /><template v-if="form.type === 'trend'"><Field v-model="form.period_from" type="month" label="Periode awal" style="flex:1" /><Field v-model="form.period_to" type="month" label="Periode akhir" style="flex:1" /></template><Field v-model="form.include_closed" type="checkbox" label="Sertakan risiko yang sudah ditutup" /></div>
      <button type="button" class="btn block" :class="form.format === 'pdf' ? 'c-red' : 'c-green'" :disabled="busy" @click="generate"><Icon name="file" />{{ busy ? 'Menyusun…' : `Unduh ${form.format.toUpperCase()}` }}</button>
    </div></div>
    <div class="card" style="grid-column:span 7"><div class="card-h"><h3>Riwayat laporan</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Jenis</th><th>Format</th><th>Oleh</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="j in jobs" :key="j.id"><td style="white-space:nowrap">{{ fmt.datetime(j.created_at) }}</td><td class="t-main">{{ types[j.type] || j.type }}</td><td class="mono">{{ j.format.toUpperCase() }}</td><td class="t-sub">{{ j.user?.name }}</td><td><Pill :value="j.status === 'done' ? 'done' : j.status === 'failed' ? 'rejected' : 'running'" /><div v-if="j.error" class="hint">{{ j.error }}</div></td><td><button v-if="!j.params?.schedule && formats[j.type]?.includes(j.format)" type="button" class="btn sm ghost c-blue" :disabled="busy" title="Berkas tidak disimpan di server; laporan dibuat ulang dengan parameter yang sama dari data terkini" @click="rerun(j)"><Icon name="refresh" />Buat ulang</button></td></tr>
      <tr v-if="!jobs.length"><td colspan="6"><div class="empty">Belum ada laporan dibuat.</div></td></tr></tbody></table></div></div>
    <div class="card" style="grid-column:1/-1"><div class="card-h"><h3>Laporan terjadwal</h3><span class="sub">dikirim otomatis lewat email pukul 07.00 sesuai jadwal</span></div><div class="card-b stack">
      <div class="filters">
        <Field v-model="sf.type" type="select" label="Jenis" :options="types" empty="" /><Field v-model="sf.format" type="select" label="Format" :options="Object.fromEntries(formats[sf.type].map((f) => [f, f.toUpperCase()]))" empty="" />
        <Field v-model="sf.frequency" type="select" label="Frekuensi" :options="{ monthly: 'Bulanan (tanggal)', weekly: 'Mingguan (hari ke-)' }" empty="" /><Field v-model="sf.day" type="number" :label="sf.frequency === 'weekly' ? 'Hari (1=Senin)' : 'Tanggal'" min="1" :max="sf.frequency === 'weekly' ? 7 : 28" />
        <div class="field" style="flex:1;min-width:240px"><label>Penerima (pisahkan koma)</label><input v-model="rcpt" class="inp" placeholder="direksi@contoh.go.id, umr@contoh.go.id"><span v-if="Object.keys(sf.errors).length" class="err-msg">{{ Object.values(sf.errors)[0] }}</span></div>
        <button type="button" class="btn c-green" :disabled="sf.processing" @click="saveSchedule"><Icon name="plus" />Jadwalkan</button>
      </div>
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Laporan</th><th>Jadwal</th><th>Penerima</th><th>Terakhir dikirim</th><th>Pembuat</th><th></th></tr></thead><tbody>
        <tr v-for="s in schedules" :key="s.id"><td class="t-main">{{ types[s.type] }} <span class="mono muted">{{ s.format.toUpperCase() }}</span></td><td>{{ s.frequency === 'weekly' ? `Mingguan, hari ke-${s.day}` : `Bulanan, tanggal ${s.day}` }}</td><td class="t-sub">{{ s.recipients.join(', ') }}</td><td>{{ fmt.datetime(s.last_sent_at) }}<div v-if="s.last_error" class="err-msg">{{ s.last_error }}</div></td><td class="t-sub">{{ s.user?.name }}</td><td><ConfirmButton :href="`/reports/schedules/${s.id}`" message="Hapus jadwal laporan ini?" /></td></tr>
        <tr v-if="!schedules.length"><td colspan="6"><div class="empty">Belum ada laporan terjadwal.</div></td></tr></tbody></table></div>
    </div></div>
  </div>
</template>
