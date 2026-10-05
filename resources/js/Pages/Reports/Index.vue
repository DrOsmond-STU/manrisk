<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
import Pill from '../../Components/Pill.vue';
import { fmt, toast } from '../../lib/format';
const props = defineProps({ types: Object, units: Array, jobs: Array });
const form = ref({ type: 'register', format: 'xlsx', unit_id: '', year: new Date().getFullYear(), include_closed: false });
const busy = ref(false);
const generate = async () => {
  busy.value = true;
  try {
    const token = document.querySelector('meta[name=csrf-token]').content;
    const res = await fetch('/reports', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/octet-stream, application/json' }, body: JSON.stringify(form.value) });
    if (!res.ok) { toast('Gagal membuat laporan (' + res.status + ').', 'bad'); return; }
    const blob = await res.blob(); const cd = res.headers.get('Content-Disposition') || ''; const m = cd.match(/filename="?([^";]+)"?/);
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = m ? m[1] : `laporan.${form.value.format}`; a.click(); URL.revokeObjectURL(a.href);
    toast('Laporan diunduh.', 'ok'); setTimeout(() => window.location.reload(), 800);
  } finally { busy.value = false; }
};
const desc = { register: 'Seluruh risiko dengan penyebab, peristiwa, dampak, skor inheren/residual/target, evaluasi, treatment, dan status.', profile: 'Ringkasan profil risiko: distribusi level, appetite per kategori, status action plan, KRI, insiden.', heatmap: 'Daftar risiko tersusun per sel matriks.', action_plans: 'Status seluruh action plan beserta PIC, tenggat, progres.', controls: 'Daftar kontrol dan hasil efektivitas desain/operasi.', kri: 'Nilai terkini dan status seluruh KRI.', incidents: 'Insiden dan kerugian pada tahun yang dipilih.', executive: 'Laporan eksekutif untuk pimpinan: ringkasan, appetite, risiko utama, rekomendasi.' };
</script>
<template>
  <Head title="Laporan" />
  <PageHead kicker="Pelaporan" title="Laporan & ekspor" sub="Hasilkan laporan PDF untuk pimpinan/auditor atau Excel untuk analisis lanjutan. Setiap ekspor dicatat pada audit trail." />
  <div class="s-grid">
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Buat laporan</h3></div><div class="card-b stack">
      <Field v-model="form.type" type="select" label="Jenis laporan" :options="types" empty="" required />
      <p class="hint">{{ desc[form.type] }}</p>
      <div class="row" style="gap:8px"><Field v-model="form.format" type="select" label="Format" :options="{ pdf: 'PDF', xlsx: 'Excel (XLSX)' }" empty="" style="flex:1" /><Field v-model="form.unit_id" type="select" label="Unit (opsional)" :options="units" empty="Semua unit" style="flex:1" /></div>
      <div class="row" style="gap:8px"><Field v-if="form.type === 'incidents'" v-model="form.year" type="number" label="Tahun" style="flex:1" /><Field v-model="form.include_closed" type="checkbox" label="Sertakan risiko yang sudah ditutup" /></div>
      <button type="button" class="btn block" :class="form.format === 'pdf' ? 'c-red' : 'c-green'" :disabled="busy" @click="generate"><Icon name="file" />{{ busy ? 'Menyusun…' : `Unduh ${form.format.toUpperCase()}` }}</button>
    </div></div>
    <div class="card" style="grid-column:span 7"><div class="card-h"><h3>Riwayat laporan</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Jenis</th><th>Format</th><th>Oleh</th><th>Status</th></tr></thead><tbody>
      <tr v-for="j in jobs" :key="j.id"><td style="white-space:nowrap">{{ fmt.datetime(j.created_at) }}</td><td class="t-main">{{ types[j.type] || j.type }}</td><td class="mono">{{ j.format.toUpperCase() }}</td><td class="t-sub">{{ j.user?.name }}</td><td><Pill :value="j.status === 'done' ? 'done' : j.status === 'failed' ? 'rejected' : 'running'" /><div v-if="j.error" class="hint">{{ j.error }}</div></td></tr>
      <tr v-if="!jobs.length"><td colspan="5"><div class="empty">Belum ada laporan dibuat.</div></td></tr></tbody></table></div></div>
  </div>
</template>
