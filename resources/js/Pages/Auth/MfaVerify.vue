<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
const props = defineProps({ email: String, ttl: Number, wait: Number, trust_days: Number, has_recovery: Boolean, status: String, error: String });
const form = useForm({ code: '', mode: 'otp', trust: false });
const left = ref(props.wait || 0);
let timer = null;
const tick = () => { if (left.value > 0) left.value -= 1; };
onMounted(() => { timer = setInterval(tick, 1000); });
onBeforeUnmount(() => clearInterval(timer));
const submit = () => form.post('/login/verify', { onFinish: () => form.reset('code') });
const resend = () => router.post('/login/verify/resend', {}, { onSuccess: () => { left.value = 60; } });
const switchMode = () => { form.mode = form.mode === 'otp' ? 'recovery' : 'otp'; form.code = ''; form.clearErrors(); };
</script>
<template>
  <Head title="Verifikasi dua langkah" />
  <div class="login">
    <section class="brandside">
      <div class="brand"><span class="brand-mark" aria-hidden="true"><i v-for="(k, i) in ['l', 'm', 'h', 'm', 'h', 'vh', 'h', 'vh', 'vh']" :key="i" :style="`background:var(--lv-${k})`"></i></span><span><b>ManRisk</b><small>ERM · ISO 31000:2018</small></span></div>
      <div class="claim">
        <h2>Verifikasi dua langkah melindungi akun Anda.</h2>
        <p>Meski kata sandi diketahui orang lain, akun tetap aman karena masuk membutuhkan kode yang hanya dikirim ke email Anda.</p>
      </div>
    </section>
    <section class="formside">
      <form class="lcard" novalidate @submit.prevent="submit">
        <h1>Verifikasi dua langkah</h1>
        <p v-if="form.mode === 'otp'" class="sub">Masukkan kode 6 digit yang dikirim ke <b>{{ email }}</b>. Kode berlaku {{ ttl }} menit.</p>
        <p v-else class="sub">Masukkan salah satu kode pemulihan yang Anda simpan saat mengaktifkan verifikasi dua langkah. Setiap kode hanya dapat dipakai sekali.</p>
        <div v-if="status" class="alert-box info" role="status" style="margin-bottom:14px">{{ status }}</div>
        <div v-if="error" class="alert-box bad" role="alert" style="margin-bottom:14px">{{ error }}</div>
        <div v-if="form.errors.code" class="alert-box bad" role="alert" style="margin-bottom:14px">{{ form.errors.code }}</div>
        <div class="field">
          <label for="code">{{ form.mode === 'otp' ? 'Kode verifikasi' : 'Kode pemulihan' }}</label>
          <input v-if="form.mode === 'otp'" id="code" v-model="form.code" class="inp mono otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" placeholder="000000" required autofocus>
          <input v-else id="code" v-model="form.code" class="inp mono" type="text" autocomplete="off" autocapitalize="characters" maxlength="11" placeholder="XXXXX-XXXXX" required autofocus>
        </div>
        <label v-if="trust_days > 0" class="row chk" style="margin-bottom:14px"><input v-model="form.trust" type="checkbox"><span>Percayai perangkat ini selama {{ trust_days }} hari (jangan pilih di komputer bersama)</span></label>
        <button class="btn full c-blue" type="submit" :disabled="form.processing || !form.code">{{ form.processing ? 'Memeriksa…' : 'Verifikasi & masuk' }}</button>
        <div class="row" style="justify-content:space-between;margin-top:14px;gap:8px;flex-wrap:wrap">
          <button v-if="form.mode === 'otp'" type="button" class="btn sm c-cyan" :disabled="left > 0" @click="resend">{{ left > 0 ? `Kirim ulang (${left} dtk)` : 'Kirim ulang kode' }}</button>
          <button v-if="has_recovery || form.mode === 'recovery'" type="button" class="btn sm" @click="switchMode">{{ form.mode === 'otp' ? 'Pakai kode pemulihan' : 'Pakai kode email' }}</button>
          <button type="button" class="btn sm c-red" @click="router.post('/login/verify/cancel')">Batal</button>
        </div>
        <p class="foot">Tidak menerima email? Periksa folder spam, tunggu sebentar lalu kirim ulang. Bila tetap tidak masuk, gunakan kode pemulihan atau hubungi administrator ManRisk. Setiap percobaan verifikasi dicatat.</p>
      </form>
    </section>
  </div>
</template>
