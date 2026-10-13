<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
const props = defineProps({ status: String });
const form = useForm({ email: '', password: '' });
const show = ref(false);
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>
<template>
  <Head title="Masuk" />
  <div class="login">
    <section class="brandside">
      <div class="brand"><span class="brand-mark" aria-hidden="true"><i v-for="(k, i) in ['l', 'm', 'h', 'm', 'h', 'vh', 'h', 'vh', 'vh']" :key="i" :style="`background:var(--lv-${k})`"></i></span><span><b>ManRisk</b><small>ERM · ISO 31000:2018 · <span class="nw">PER-2/MBU/03/2023</span></small></span></div>
      <div class="claim">
        <div class="comply" aria-label="Standar dan regulasi yang dipenuhi"><span><Icon name="shield" :size="14" />ISO 31000:2018</span><span><Icon name="book" :size="14" />Permen BUMN PER-2/MBU/03/2023</span></div>
        <h2>Manajemen risiko terintegrasi, sesuai standar internasional dan regulasi pemerintah.</h2>
        <p>Risk register, matriks & evaluasi, mitigasi, kontrol, KRI, insiden, review berkala, persetujuan berjenjang, dan pelaporan — dalam satu platform.</p>
        <div class="reg">
          <h3>Sesuai Peraturan Menteri BUMN <span class="nw">PER-2/MBU/03/2023</span></h3>
          <p>Pedoman Tata Kelola dan Kegiatan Korporasi Signifikan BUMN, berlaku sejak 24 Maret 2023 dan menggantikan <span class="nw">PER-5/MBU/09/2022</span> tentang Penerapan Manajemen Risiko pada BUMN. ManRisk tidak hanya mengikuti proses ISO 31000, tetapi juga ketentuan manajemen risiko BUMN:</p>
          <ul>
            <li><b>Tiga lini &amp; organ pengelola</b> — Direksi, Dewan Komisaris/Dewan Pengawas, Komite Audit, dan Komite Pemantau Risiko</li>
            <li><b>Taksonomi risiko T1–T3</b> — terintegrasi dari BUMN induk hingga anak perusahaan, dengan kuadran sistemik, signifikan, atau netral</li>
            <li><b>Level risiko 5 tingkat &amp; kuantifikasi</b> — eksposur rupiah, risk limit, dan target residual per triwulan</li>
            <li><b>Laporan triwulanan, Risk Maturity Index &amp; kajian risiko KKS</b></li>
          </ul>
        </div>
      </div>
    </section>
    <section class="formside">
      <form class="lcard" novalidate @submit.prevent="submit">
        <h1>Masuk ke ManRisk</h1>
        <p class="sub">Integrated Enterprise Risk Management Platform<br><span class="nw">ISO 31000</span> · <span class="nw">PER-2/MBU/03/2023</span></p>
        <div v-if="status" class="alert-box info" role="status" style="margin-bottom:14px">{{ status }}</div>
        <div v-if="form.errors.email || form.errors.password" class="alert-box bad" role="alert" style="margin-bottom:14px">{{ form.errors.email || form.errors.password }}</div>
        <div class="field"><label for="email">Alamat email</label><input id="email" v-model="form.email" class="inp" type="email" autocomplete="username" inputmode="email" required autofocus maxlength="160"></div>
        <div class="field"><label for="pass">Kata sandi</label>
          <div class="row" style="gap:6px"><input id="pass" v-model="form.password" class="inp" :type="show ? 'text' : 'password'" autocomplete="current-password" required style="flex:1"><button type="button" class="icon-btn c-cyan" :aria-label="show ? 'Sembunyikan' : 'Tampilkan'" @click="show = !show"><Icon name="search" /></button></div>
        </div>
        <div style="height:6px"></div>
        <button class="btn full c-blue" type="submit" :disabled="form.processing">{{ form.processing ? 'Memeriksa…' : 'Masuk' }}</button>
        <p class="foot">Akses hanya untuk pengguna terdaftar. Lupa kata sandi? Hubungi administrator ManRisk di organisasi Anda. Setiap percobaan masuk dicatat.</p>
      </form>
    </section>
  </div>
</template>
