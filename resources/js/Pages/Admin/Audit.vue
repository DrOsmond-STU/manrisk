<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
import Pagination from '../../Components/Pagination.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ logs: Object, filters: Object, actions: Array, subjects: Array, users: Array, auth_logs: Array });
const tab = ref('audit');
const f = reactive({ q: props.filters.q || '', action: props.filters.action || '', subject: props.filters.subject || '', user_id: props.filters.user_id || '', from: props.filters.from || '', to: props.filters.to || '' });
let t; watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get('/admin/audit', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true }), 350); });
// ekspor CSV memakai filter yang sama dengan layar
const csvUrl = computed(() => `/admin/audit/export?${new URLSearchParams(Object.fromEntries(Object.entries(f).filter(([, v]) => v)))}`);
const isSuper = usePage().props.auth.user.role === 'super_admin';
// tautan ke halaman objek yang diaudit (bila ada halamannya; objek yang dihapus tidak ditautkan)
const linkFor = (l) => { const id = l.subject_id; if (!id || l.action === 'deleted') return null;
  return { risk: `/risks/${id}`, action_plan: `/action-plans/${id}`, control: `/controls/${id}`, incident: `/incidents/${id}`, approval: `/approvals?id=${id}`, kri: '/kris', improvement: '/improvements', review: '/reviews', document: `/documents?history=${id}`,
    org_unit: '/organization/units', objective: '/organization/objectives', user: isSuper ? '/admin/users' : null }[l.kind] || null; };
const show = (v) => (v === null || v === undefined ? '∅' : typeof v === 'object' ? JSON.stringify(v) : String(v));
</script>
<template>
  <Head title="Audit trail" />
  <PageHead kicker="Administrasi" title="Audit trail & log autentikasi" sub="Jejak setiap pembuatan, perubahan (nilai lama → baru), penghapusan, persetujuan, unduhan, dan ekspor. Catatan hanya dapat ditambah, tidak dapat diubah atau dihapus."><a :href="csvUrl" class="btn c-green"><Icon name="file" />Ekspor CSV</a></PageHead>
  <div class="tabs"><button type="button" :class="{ on: tab === 'audit' }" @click="tab = 'audit'">Audit trail<span class="c">{{ logs.total }}</span></button><button v-if="auth_logs.length" type="button" :class="{ on: tab === 'auth' }" @click="tab = 'auth'">Autentikasi<span class="c">{{ auth_logs.length }}</span></button></div>
  <div v-if="tab === 'audit'" class="card"><div class="card-b filters"><Field v-model="f.q" label="Cari" placeholder="label / konteks" style="flex:1" /><Field v-model="f.action" type="select" label="Aksi" :options="actions" empty="Semua" /><Field v-model="f.subject" type="select" label="Objek" :options="subjects" empty="Semua" /><Field v-model="f.user_id" type="select" label="Pengguna" :options="users" empty="Semua" /><Field v-model="f.from" type="date" label="Dari" /><Field v-model="f.to" type="date" label="Sampai" /></div>
    <div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Perubahan</th><th>Konteks / IP</th></tr></thead><tbody>
      <tr v-for="l in logs.data" :key="l.id"><td style="white-space:nowrap" class="t-sub">{{ fmt.datetime(l.created_at) }}</td><td>{{ l.user || 'sistem' }}</td><td><span class="pill" :class="{ ok: l.action === 'created', run: l.action === 'updated', bad: l.action === 'deleted', warn: /approval|submitted/.test(l.action) }">{{ l.action }}</span></td><td><Link v-if="linkFor(l)" :href="linkFor(l)"><b>{{ l.subject }}</b> <span class="mono">#{{ l.subject_id }}</span></Link><template v-else><b>{{ l.subject }}</b> <span class="mono muted">#{{ l.subject_id }}</span></template><div class="t-sub">{{ l.label }}</div></td><td class="wrap" style="max-width:420px"><span v-for="(v, k) in (l.changes || {})" :key="k" class="hint" style="display:block"><b>{{ k }}</b>: {{ show(v[0]) }} → {{ show(v[1]) }}</span></td><td class="t-sub mono">{{ l.context }}<br>{{ l.ip }}</td></tr>
      <tr v-if="!logs.data.length"><td colspan="6"><div class="empty">Tidak ada catatan.</div></td></tr></tbody></table></div><Pagination :data="logs" /></div>
  <div v-else class="card"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Peristiwa</th><th>Email</th><th>Pengguna</th><th>IP</th><th>Perangkat</th><th>Detail</th></tr></thead><tbody><tr v-for="l in auth_logs" :key="l.id"><td style="white-space:nowrap">{{ fmt.datetime(l.created_at) }}</td><td><span class="pill" :class="/failed|locked|disabled|expired|deleted/.test(l.event) ? 'bad' : 'ok'">{{ l.event }}</span></td><td class="mono">{{ l.email }}</td><td>{{ l.user?.name }}</td><td class="mono">{{ l.ip }}</td><td class="t-sub clamp" style="max-width:260px">{{ l.user_agent }}</td><td class="t-sub">{{ l.detail }}</td></tr></tbody></table></div></div>
</template>
