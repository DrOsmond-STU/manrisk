<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
const props = defineProps({ organization: Object, policy: Object });
const form = useForm({ name: props.organization.name, code: props.organization.code, settings: { review_cycle: props.organization.settings?.review_cycle || 'quarterly', fiscal_year_start: props.organization.settings?.fiscal_year_start || 1, currency: props.organization.settings?.currency || 'IDR' } });
const isSuper = usePage().props.auth.user.role === 'super_admin';
</script>
<template>
  <Head title="Pengaturan organisasi" />
  <PageHead kicker="Administrasi" title="Pengaturan organisasi" sub="Identitas organisasi (tenant) dan kebijakan keamanan yang berlaku. Kebijakan keamanan diatur lewat berkas lingkungan server (.env)." />
  <div class="s-grid">
    <form class="card" style="grid-column:span 6" @submit.prevent="form.put('/settings/organization')"><div class="card-h"><h3>Identitas</h3></div><div class="card-b stack">
      <Field v-model="form.name" label="Nama organisasi" required :error="form.errors.name" :disabled="!isSuper" /><Field v-model="form.code" label="Kode" required :error="form.errors.code" :disabled="!isSuper" />
      <div class="row" style="gap:8px"><Field v-model="form.settings.review_cycle" type="select" label="Siklus review" :options="{ monthly: 'Bulanan', quarterly: 'Triwulanan', semester: 'Semesteran' }" empty="" style="flex:1" :disabled="!isSuper" /><Field v-model="form.settings.fiscal_year_start" type="number" label="Awal tahun anggaran (bulan)" min="1" max="12" style="flex:1" :disabled="!isSuper" /><Field v-model="form.settings.currency" label="Mata uang" maxlength="5" style="flex:1" :disabled="!isSuper" /></div>
      <div v-if="isSuper" class="row" style="justify-content:flex-end"><button class="btn c-green" type="submit" :disabled="form.processing">Simpan</button></div></div></form>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Kebijakan keamanan aktif</h3></div><div class="card-b"><dl class="kv"><dt>Panjang sandi min.</dt><dd>{{ policy.password_min }} karakter, huruf besar/kecil, angka, simbol; 5 sandi terakhir tidak boleh diulang</dd><dt>Kunci akun</dt><dd>{{ policy.login_max_attempts }} kali gagal → kunci {{ policy.login_lock_minutes }} menit</dd><dt>Sesi</dt><dd>idle {{ policy.session_idle_minutes }} menit · maksimum {{ policy.session_absolute_hours }} jam</dd><dt>SLA persetujuan</dt><dd>{{ policy.approval_sla_days }} hari kerja per tahap</dd><dt>Unggahan</dt><dd>maks {{ Math.round(policy.upload_max_kb / 1024) }} MB · PDF/DOC/XLS/JPG/PNG</dd><dt>AI</dt><dd>{{ policy.ai.enabled ? 'aktif' : 'nonaktif' }} · {{ policy.ai.has_key ? policy.ai.model : 'tanpa kunci API (mode lokal)' }}</dd><dt>Lainnya</dt><dd>CSRF, header CSP/HSTS, cookie HttpOnly+SameSite, audit trail append-only, log autentikasi</dd></dl></div></div>
  </div>
</template>
