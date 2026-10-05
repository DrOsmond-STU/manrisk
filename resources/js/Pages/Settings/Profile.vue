<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ user: Object, logins: Array });
const form = useForm({ name: props.user.name, position: props.user.position || '', preferences: { notify: props.user.preferences?.notify || ['database', 'mail'], theme: props.user.preferences?.theme || '' } });
const toggle = (c) => { const i = form.preferences.notify.indexOf(c); if (i >= 0) form.preferences.notify.splice(i, 1); else form.preferences.notify.push(c); };
</script>
<template>
  <Head title="Profil" />
  <PageHead kicker="Akun" :title="user.name" :sub="`${user.role_label} · ${user.unit || 'Seluruh organisasi'} · ${user.email}`"><Link href="/password" class="btn c-amber"><Icon name="lock" />Ganti kata sandi</Link></PageHead>
  <div class="s-grid">
    <form class="card" style="grid-column:span 6" @submit.prevent="form.put('/profile')"><div class="card-h"><h3>Profil & preferensi</h3></div><div class="card-b stack">
      <Field v-model="form.name" label="Nama" required :error="form.errors.name" /><Field v-model="form.position" label="Jabatan" :error="form.errors.position" />
      <div class="field"><label>Saluran notifikasi</label><div class="chips-sel"><label :class="{ on: form.preferences.notify.includes('database') }"><input type="checkbox" style="display:none" @change="toggle('database')">Lonceng di aplikasi</label><label :class="{ on: form.preferences.notify.includes('mail') }"><input type="checkbox" style="display:none" @change="toggle('mail')">Email</label></div></div>
      <div class="row" style="justify-content:flex-end"><button class="btn c-green" type="submit" :disabled="form.processing">Simpan</button></div></div></form>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Keamanan akun</h3></div><div class="card-b"><dl class="kv"><dt>Peran</dt><dd>{{ user.role_label }}</dd><dt>Login terakhir</dt><dd>{{ fmt.datetime(user.last_login_at) }} dari {{ user.last_login_ip || '—' }}</dd><dt>Sandi diganti</dt><dd>{{ fmt.datetime(user.password_changed_at) }}</dd></dl>
      <h4 style="margin:14px 0 6px">Aktivitas masuk terakhir</h4><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Peristiwa</th><th>IP</th></tr></thead><tbody><tr v-for="(l, i) in logins" :key="i"><td>{{ fmt.datetime(l.created_at) }}</td><td><span class="pill" :class="l.event === 'login_failed' ? 'bad' : 'ok'">{{ l.event }}</span></td><td class="mono">{{ l.ip }}</td></tr></tbody></table></div></div></div>
  </div>
</template>
