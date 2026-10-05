<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ user: Object, logins: Array, sessions: Number, mfa: Object, recovery_codes: Array });
const confirm = (m) => window.confirm(m);
const form = useForm({ name: props.user.name, position: props.user.position || '', preferences: { notify: props.user.preferences?.notify || ['database', 'mail'], theme: props.user.preferences?.theme || '' } });
const toggle = (c) => { const i = form.preferences.notify.indexOf(c); if (i >= 0) form.preferences.notify.splice(i, 1); else form.preferences.notify.push(c); };
// Verifikasi dua langkah (MFA via email)
const codeSent = ref(false);
const enableForm = useForm({ code: '' });
const pwForm = useForm({ password: '' });
const pwAction = ref(null);
const sendCode = () => router.post('/profile/mfa/send', {}, { preserveScroll: true, onSuccess: (p) => { codeSent.value = !p.props.flash?.error; } });
const enable = () => enableForm.post('/profile/mfa/enable', { preserveScroll: true, onSuccess: () => { enableForm.reset(); codeSent.value = false; } });
const askPw = (a) => { pwAction.value = a; pwForm.reset(); pwForm.clearErrors(); };
const runPw = () => pwForm.post(`/profile/mfa/${pwAction.value}`, { preserveScroll: true, onSuccess: () => { pwAction.value = null; pwForm.reset(); } });
const codesText = () => `Kode pemulihan ManRisk ERM — ${props.user.email}\nSetiap kode hanya dapat dipakai sekali.\n\n${(props.recovery_codes || []).join('\n')}\n`;
const copied = ref(false);
const copyCodes = async () => { try { await navigator.clipboard.writeText(codesText()); copied.value = true; } catch (e) { copied.value = false; } };
const downloadCodes = () => { const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([codesText()], { type: 'text/plain' })); a.download = 'kode-pemulihan-manrisk.txt'; a.click(); URL.revokeObjectURL(a.href); };
</script>
<template>
  <Head title="Profil" />
  <PageHead kicker="Akun" :title="user.name" :sub="`${user.role_label} · ${user.unit || 'Seluruh organisasi'} · ${user.email}`"><Link href="/password" class="btn c-amber"><Icon name="lock" />Ganti kata sandi</Link><button type="button" class="btn c-red" @click="confirm('Akhiri semua sesi lain (perangkat/peramban lain)?') && router.post('/logout-others')"><Icon name="x" />Keluar dari perangkat lain</button></PageHead>
  <div class="s-grid">
    <form class="card" style="grid-column:span 6" @submit.prevent="form.put('/profile')"><div class="card-h"><h3>Profil & preferensi</h3></div><div class="card-b stack">
      <Field v-model="form.name" label="Nama" required :error="form.errors.name" /><Field v-model="form.position" label="Jabatan" :error="form.errors.position" />
      <div class="field"><label>Saluran notifikasi</label><div class="chips-sel"><label :class="{ on: form.preferences.notify.includes('database') }"><input type="checkbox" style="display:none" @change="toggle('database')">Lonceng di aplikasi</label><label :class="{ on: form.preferences.notify.includes('mail') }"><input type="checkbox" style="display:none" @change="toggle('mail')">Email</label></div></div>
      <div class="row" style="justify-content:flex-end"><button class="btn c-green" type="submit" :disabled="form.processing">Simpan</button></div></div></form>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Keamanan akun</h3></div><div class="card-b"><dl class="kv"><dt>Peran</dt><dd>{{ user.role_label }}</dd><dt>Login terakhir</dt><dd>{{ fmt.datetime(user.last_login_at) }} dari {{ user.last_login_ip || '—' }}</dd><dt>Sandi diganti</dt><dd>{{ fmt.datetime(user.password_changed_at) }}</dd><dt>Sesi aktif</dt><dd>{{ sessions }} perangkat/peramban</dd></dl>
      <h4 style="margin:14px 0 6px">Aktivitas masuk terakhir</h4><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Peristiwa</th><th>IP</th></tr></thead><tbody><tr v-for="(l, i) in logins" :key="i"><td>{{ fmt.datetime(l.created_at) }}</td><td><span class="pill" :class="/failed|locked/.test(l.event) ? 'bad' : 'ok'">{{ l.event }}</span></td><td class="mono">{{ l.ip }}</td></tr></tbody></table></div></div></div>
    <div class="card" style="grid-column:span 12"><div class="card-h"><h3><Icon name="shield" /> Verifikasi dua langkah (MFA)</h3><span class="pill" :class="mfa.active ? 'ok' : 'warn'">{{ mfa.active ? (mfa.required && !mfa.enabled ? 'Aktif — diwajibkan kebijakan' : 'Aktif') : 'Nonaktif' }}</span></div><div class="card-b stack">
      <p class="muted" style="margin:0">Setelah kata sandi benar, ManRisk mengirim kode 6 digit ke <b>{{ mfa.email }}</b> (berlaku {{ mfa.ttl }} menit). Tanpa kode itu, akun tidak dapat dimasuki meski kata sandi bocor.</p>
      <div v-if="!mfa.mail_ready && !mfa.active" class="alert-box warn"><Icon name="alert" /><span>Server email (SMTP) belum dikonfigurasi, sehingga kode belum dapat dikirim. Minta Super Admin mengatur menu <b>Email & SMTP</b>.</span></div>

      <div v-if="recovery_codes?.length" class="alert-box warn" style="display:block">
        <b>Simpan kode pemulihan ini sekarang — hanya ditampilkan sekali.</b>
        <div>Pakai salah satu kode bila tidak dapat mengakses email. Setiap kode hanya berlaku sekali; membuat kode baru membatalkan kode lama.</div>
        <div class="codes"><span v-for="c in recovery_codes" :key="c">{{ c }}</span></div>
        <div class="row" style="gap:6px"><button type="button" class="btn sm c-blue" @click="copyCodes"><Icon name="copy" />{{ copied ? 'Tersalin' : 'Salin' }}</button><button type="button" class="btn sm c-cyan" @click="downloadCodes"><Icon name="down" />Unduh .txt</button></div>
      </div>

      <template v-if="!mfa.active">
        <div v-if="!codeSent" class="row"><button type="button" class="btn c-green" :disabled="!mfa.mail_ready" @click="sendCode"><Icon name="send" />Aktifkan — kirim kode ke email</button></div>
        <form v-else class="row" style="gap:8px;align-items:flex-end;flex-wrap:wrap" @submit.prevent="enable">
          <Field v-model="enableForm.code" label="Kode dari email" placeholder="000000" maxlength="6" :error="enableForm.errors.code" style="max-width:200px" />
          <button class="btn c-green" type="submit" :disabled="enableForm.processing || enableForm.code.length < 6">Konfirmasi & aktifkan</button>
          <button type="button" class="btn" @click="sendCode">Kirim ulang</button>
        </form>
      </template>
      <template v-else>
        <dl class="kv"><dt>Status</dt><dd>{{ mfa.enabled ? `Diaktifkan ${fmt.datetime(mfa.enabled_at)}` : 'Diwajibkan kebijakan organisasi untuk peran Anda' }}</dd><dt>Kode pemulihan</dt><dd>{{ mfa.recovery_remaining ? `${mfa.recovery_remaining} kode tersisa` : 'Belum dibuat — buat sekarang agar tetap bisa masuk bila email tidak dapat diakses' }}</dd><dt>Perangkat tepercaya</dt><dd>{{ mfa.trust_days ? `${mfa.trusted_devices} perangkat (berlaku ${mfa.trust_days} hari)` : 'Tidak diizinkan kebijakan — kode selalu diminta' }}</dd></dl>
        <div class="row" style="gap:6px;flex-wrap:wrap">
          <button type="button" class="btn c-blue" @click="askPw('recovery')"><Icon name="key" />{{ mfa.recovery_remaining ? 'Buat ulang kode pemulihan' : 'Buat kode pemulihan' }}</button>
          <button v-if="mfa.trusted_devices" type="button" class="btn c-amber" @click="router.delete('/profile/mfa/devices', { preserveScroll: true })"><Icon name="x" />Lupakan perangkat tepercaya</button>
          <button v-if="!mfa.required" type="button" class="btn c-red" @click="askPw('disable')"><Icon name="lock" />Nonaktifkan</button>
        </div>
      </template>
      <form v-if="pwAction" class="row" style="gap:8px;align-items:flex-end;flex-wrap:wrap" @submit.prevent="runPw">
        <Field v-model="pwForm.password" type="password" :label="pwAction === 'disable' ? 'Konfirmasi kata sandi untuk menonaktifkan' : 'Konfirmasi kata sandi untuk membuat kode baru'" :error="pwForm.errors.password" style="min-width:260px" />
        <button class="btn" :class="pwAction === 'disable' ? 'c-red' : 'c-blue'" type="submit" :disabled="pwForm.processing || !pwForm.password">Lanjutkan</button><button type="button" class="btn" @click="pwAction = null">Batal</button>
      </form>
    </div></div>
  </div>
</template>
