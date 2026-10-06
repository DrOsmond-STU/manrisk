<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
const props = defineProps({ organization: Object, policy: Object });
const st = props.organization.settings || {};
const form = useForm({ name: props.organization.name, code: props.organization.code, settings: { review_cycle: st.review_cycle || 'quarterly', fiscal_year_start: st.fiscal_year_start || 1, currency: st.currency || 'IDR', appetite_statement: st.appetite_statement || '', appetite_basis: st.appetite_basis || '', appetite_date: st.appetite_date || '', mfa_required_roles: st.mfa_required_roles || [], mfa_trust_days: st.mfa_trust_days ?? 0 } });
const roles = usePage().props.labels.roles;
const trustLabel = (d) => (d ? `${d} hari` : 'Tidak diizinkan (kode selalu diminta)');
const isSuper = usePage().props.auth.user.role === 'super_admin';
</script>
<template>
  <Head title="Pengaturan organisasi" />
  <PageHead kicker="Administrasi" title="Pengaturan organisasi" sub="Identitas organisasi (tenant) dan kebijakan keamanan yang berlaku. Kewajiban verifikasi dua langkah (MFA) diatur di sini; kebijakan keamanan lain diatur lewat berkas lingkungan server (.env)." />
  <div class="s-grid">
    <form class="card" style="grid-column:span 6" @submit.prevent="form.put('/settings/organization')"><div class="card-h"><h3>Identitas</h3></div><div class="card-b stack">
      <Field v-model="form.name" label="Nama organisasi" required :error="form.errors.name" :disabled="!isSuper" /><Field v-model="form.code" label="Kode" required :error="form.errors.code" :disabled="!isSuper" />
      <div class="row" style="gap:8px"><Field v-model="form.settings.review_cycle" type="select" label="Siklus review" :options="{ monthly: 'Bulanan', quarterly: 'Triwulanan', semester: 'Semesteran' }" empty="" style="flex:1" :disabled="!isSuper" /><Field v-model="form.settings.fiscal_year_start" type="number" label="Awal tahun anggaran (bulan)" min="1" max="12" style="flex:1" :disabled="!isSuper" /><Field v-model="form.settings.currency" label="Mata uang" maxlength="5" style="flex:1" :disabled="!isSuper" /></div>
      <Field v-model="form.settings.appetite_statement" type="textarea" label="Pernyataan risk appetite organisasi" :rows="4" maxlength="4000" :disabled="!isSuper" :error="form.errors['settings.appetite_statement']" />
      <div class="row" style="gap:8px"><Field v-model="form.settings.appetite_basis" label="Dasar penetapan" maxlength="500" style="flex:2" :disabled="!isSuper" /><Field v-model="form.settings.appetite_date" type="date" label="Tanggal penetapan" style="flex:1" :disabled="!isSuper" /></div>
      <div v-if="isSuper" class="row" style="justify-content:flex-end"><button class="btn c-green" type="submit" :disabled="form.processing">Simpan</button></div></div></form>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Kebijakan keamanan aktif</h3></div><div class="card-b"><dl class="kv"><dt>Panjang sandi min.</dt><dd>{{ policy.password_min }} karakter, huruf besar/kecil, angka, simbol; 5 sandi terakhir tidak boleh diulang</dd><dt>Kunci akun</dt><dd>{{ policy.login_max_attempts }} kali gagal → kunci {{ policy.login_lock_minutes }} menit</dd><dt>Sesi</dt><dd>idle {{ policy.session_idle_minutes }} menit · maksimum {{ policy.session_absolute_hours }} jam</dd><dt>SLA persetujuan</dt><dd>{{ policy.approval_sla_days }} hari kerja per tahap</dd><dt>Unggahan</dt><dd>maks {{ Math.round(policy.upload_max_kb / 1024) }} MB · PDF/DOC/XLS/JPG/PNG</dd><dt>AI</dt><dd>{{ policy.ai.enabled ? 'aktif' : 'nonaktif' }} · {{ policy.ai.has_key ? policy.ai.model : 'tanpa kunci API (mode lokal)' }}</dd><dt>MFA</dt><dd>{{ form.settings.mfa_required_roles.length ? form.settings.mfa_required_roles.map((r) => roles[r]).join(', ') : 'opsional per pengguna' }} · email {{ policy.mail_ready ? 'siap' : 'belum siap' }}</dd><dt>Lainnya</dt><dd>CSRF, header CSP/HSTS, cookie HttpOnly+SameSite, audit trail append-only, log autentikasi</dd></dl></div></div>
    <form class="card" style="grid-column:span 12" @submit.prevent="form.put('/settings/organization', { preserveScroll: true })"><div class="card-h"><h3><Icon name="shield" /> Verifikasi dua langkah (MFA via email)</h3><span class="pill" :class="form.settings.mfa_required_roles.length ? 'ok' : 'warn'">{{ form.settings.mfa_required_roles.length ? `Wajib untuk ${form.settings.mfa_required_roles.length} peran` : 'Opsional' }}</span></div><div class="card-b stack">
      <p class="muted" style="margin:0">Pengguna pada peran yang dipilih wajib memasukkan kode 6 digit dari email setiap kali masuk (berlaku {{ policy.mfa_ttl }} menit). Pengguna lain tetap dapat mengaktifkan MFA sendiri dari halaman Profil.</p>
      <div v-if="!policy.mail_ready" class="alert-box warn"><Icon name="alert" /><span>Server email (SMTP) belum siap mengirim. <template v-if="isSuper">Atur dan uji di <Link href="/admin/mail">Administrasi → Email & SMTP</Link></template><template v-else>Minta Super Admin mengatur menu Email & SMTP</template> sebelum mewajibkan MFA.</span></div>
      <Field v-model="form.settings.mfa_required_roles" type="multi" label="Wajibkan MFA untuk peran" :options="roles" :disabled="!isSuper || (!policy.mail_ready && !form.settings.mfa_required_roles.length)" :error="form.errors['settings.mfa_required_roles']" />
      <Field v-model="form.settings.mfa_trust_days" type="select" label="Perangkat tepercaya (lewati kode di peramban yang sama)" :options="Object.fromEntries(policy.trust_options.map((d) => [d, trustLabel(d)]))" empty="" :disabled="!isSuper" style="max-width:360px" />
      <span class="hint">Disarankan: wajibkan minimal untuk Super Admin dan Risk Administrator. Kode pemulihan dapat dibuat pengguna di Profil bila email tidak dapat diakses.</span>
      <div v-if="isSuper" class="row" style="justify-content:flex-end"><button class="btn c-green" type="submit" :disabled="form.processing">Simpan kebijakan</button></div></div></form>
  </div>
</template>
