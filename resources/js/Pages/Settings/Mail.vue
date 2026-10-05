<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ mail: Object, encryptions: Object, in_use: Boolean });
const form = useForm({ enabled: props.mail.enabled, host: props.mail.host, port: props.mail.port, encryption: props.mail.encryption, username: props.mail.username, password: '', from_address: props.mail.from_address, from_name: props.mail.from_name });
const test = useForm({ to: usePage().props.auth.user.email });
const portFor = { ssl: 465, tls: 587, none: 25 };
const setEnc = (v) => { form.encryption = v; if (!form.port || Object.values(portFor).includes(Number(form.port))) form.port = portFor[v]; };
const save = () => form.put('/admin/mail', { preserveScroll: true, onSuccess: () => form.reset('password') });
const source = computed(() => (props.mail.enabled ? 'Pengaturan aplikasi (halaman ini)' : `Berkas .env server (MAIL_MAILER=${props.mail.env_mailer})`));
</script>
<template>
  <Head title="Email & SMTP" />
  <PageHead kicker="Administrasi" title="Email & SMTP" sub="Server email untuk kode verifikasi dua langkah (MFA), notifikasi peringatan, dan laporan terjadwal. Sandi SMTP disimpan terenkripsi." />
  <div class="s-grid">
    <form class="card" style="grid-column:span 7" @submit.prevent="save"><div class="card-h"><h3>Server SMTP</h3><span class="pill" :class="mail.ready ? 'ok' : 'warn'">{{ mail.ready ? 'Siap mengirim' : 'Belum dapat mengirim' }}</span></div><div class="card-b stack">
      <Field v-model="form.enabled" type="checkbox" label="Gunakan pengaturan SMTP dari aplikasi (menggantikan MAIL_* di .env)" />
      <template v-if="form.enabled">
        <div class="row" style="gap:8px;flex-wrap:wrap"><Field v-model="form.host" label="Host SMTP" required placeholder="mail.domainanda.com" :error="form.errors.host" style="flex:3;min-width:200px" /><Field v-model="form.port" type="number" label="Port" required min="1" max="65535" :error="form.errors.port" style="flex:1;min-width:90px" /></div>
        <div class="field"><label>Enkripsi</label><div class="chips-sel"><label v-for="(l, k) in encryptions" :key="k" :class="{ on: form.encryption === k }"><input type="radio" style="display:none" :checked="form.encryption === k" @change="setEnc(k)">{{ l }}</label></div><span v-if="form.encryption === 'none'" class="hint" style="color:var(--bad)">Tanpa enkripsi, sandi dan kode OTP dikirim terbuka di jaringan. Hindari kecuali server berada di jaringan lokal.</span></div>
        <div class="row" style="gap:8px;flex-wrap:wrap"><Field v-model="form.username" label="Nama pengguna SMTP" placeholder="noreply@domainanda.com" :error="form.errors.username" style="flex:1;min-width:200px" /><Field v-model="form.password" type="password" label="Sandi SMTP" :placeholder="mail.has_password ? '•••••••• (biarkan kosong bila tidak diubah)' : ''" :error="form.errors.password" style="flex:1;min-width:200px" /></div>
        <div class="row" style="gap:8px;flex-wrap:wrap"><Field v-model="form.from_address" type="email" label="Alamat pengirim" required placeholder="noreply@domainanda.com" :error="form.errors.from_address" style="flex:1;min-width:200px" /><Field v-model="form.from_name" label="Nama pengirim" :error="form.errors.from_name" style="flex:1;min-width:200px" /></div>
      </template>
      <div v-else class="alert-box info"><Icon name="info" /><span>Saat nonaktif, aplikasi memakai konfigurasi <span class="mono">MAIL_*</span> di berkas .env server.</span></div>
      <div v-if="in_use && !mail.ready" class="alert-box warn"><Icon name="alert" /><span>Verifikasi dua langkah sedang dipakai, tetapi email belum dapat dikirim. Pengguna ber-MFA hanya dapat masuk dengan kode pemulihan.</span></div>
      <div class="row" style="justify-content:flex-end"><button class="btn c-green" type="submit" :disabled="form.processing">Simpan</button></div>
    </div></form>
    <div style="grid-column:span 5" class="stack">
      <div class="card"><div class="card-h"><h3>Status</h3></div><div class="card-b"><dl class="kv"><dt>Sumber konfigurasi</dt><dd>{{ source }}</dd><dt>Status kirim</dt><dd>{{ mail.ready ? 'Siap' : 'Belum siap (email hanya ditulis ke log server)' }}</dd><dt>Uji terakhir berhasil</dt><dd>{{ mail.tested_at ? fmt.datetime(mail.tested_at) : 'Belum pernah' }}</dd><dt>Dipakai MFA</dt><dd>{{ in_use ? 'Ya — email tidak dapat dimatikan' : 'Belum' }}</dd></dl></div></div>
      <form class="card" @submit.prevent="test.post('/admin/mail/test', { preserveScroll: true })"><div class="card-h"><h3>Kirim email uji</h3></div><div class="card-b stack">
        <p class="muted" style="margin:0">Simpan pengaturan dahulu, lalu kirim email uji. Kewajiban MFA per peran dapat diaktifkan di <b>Pengaturan Organisasi</b> setelah email berhasil terkirim.</p>
        <Field v-model="test.to" type="email" label="Kirim ke" required :error="test.errors.to" />
        <div class="row" style="justify-content:flex-end"><button class="btn c-blue" type="submit" :disabled="test.processing || !mail.ready"><Icon name="send" />{{ test.processing ? 'Mengirim…' : 'Kirim email uji' }}</button></div>
      </div></form>
      <div class="card"><div class="card-h"><h3>Contoh hosting cPanel</h3></div><div class="card-b t-sub">Buat akun email (mis. <span class="mono">noreply@domain</span>) di cPanel → Email Accounts. Host: <span class="mono">mail.domain</span>, port 465 (SSL/TLS) atau 587 (STARTTLS), nama pengguna = alamat email lengkap, sandi = sandi akun email. Alamat pengirim harus sama dengan akun tersebut agar tidak ditolak/masuk spam.</div></div>
    </div>
  </div>
</template>
