<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Field from '../../Components/Field.vue';
import Icon from '../../Components/Icon.vue';
defineOptions({ layout: AppLayout });
const props = defineProps({ forced: Boolean, min: Number });
const form = useForm({ current_password: '', password: '', password_confirmation: '' });
const submit = () => form.put('/password', { onFinish: () => form.reset() });
</script>
<template>
  <Head title="Ganti kata sandi" />
  <div class="page-h"><h1>{{ forced ? 'Buat kata sandi baru' : 'Ganti kata sandi' }}</h1><p class="muted">Minimal {{ min }} karakter dengan huruf besar, huruf kecil, angka, dan simbol. Lima kata sandi terakhir tidak dapat dipakai ulang.</p></div>
  <form class="card" style="max-width:520px" @submit.prevent="submit">
    <div class="card-b stack">
      <div v-if="forced" class="alert-box warn"><Icon name="lock" /><span>Anda masuk dengan kata sandi sementara. Buat kata sandi baru untuk melanjutkan.</span></div>
      <Field v-model="form.current_password" label="Kata sandi saat ini" type="password" required :error="form.errors.current_password" />
      <Field v-model="form.password" label="Kata sandi baru" type="password" required :error="form.errors.password" />
      <Field v-model="form.password_confirmation" label="Ulangi kata sandi baru" type="password" required />
      <div class="row" style="justify-content:flex-end"><button class="btn c-green" type="submit" :disabled="form.processing">Simpan kata sandi</button></div>
    </div>
  </form>
</template>
