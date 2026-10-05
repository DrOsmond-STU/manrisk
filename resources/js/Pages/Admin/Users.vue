<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Pill from '../../Components/Pill.vue';
import Kpi from '../../Components/Kpi.vue';
import Field from '../../Components/Field.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import { fmt, toast } from '../../lib/format';
const props = defineProps({ users: Object, filters: Object, units: Array, stats: Object, auth_logs: Array });
const page = usePage();
const L = page.props.labels;
const me = page.props.auth.user;
const modal = ref(false); const item = ref(null);
const temp = computed(() => page.props.flash?.temp_password);
const shown = ref(null);
watch(temp, (t) => { if (t) shown.value = t; }, { immediate: true });
const f = reactive({ q: props.filters.q || '', role: props.filters.role || '', active: props.filters.active ?? '' });
let t; watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get('/admin/users', Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '')), { preserveState: true, replace: true }), 350); });
const fields = computed(() => [
  { key: 'name', label: 'Nama lengkap', required: true, maxlength: 120 }, { key: 'email', label: 'Email (untuk masuk)', type: 'email', required: true, maxlength: 160 },
  { key: 'role', label: 'Peran', type: 'select', options: L.roles, empty: '', required: true, default: 'risk_officer' }, { key: 'position', label: 'Jabatan', maxlength: 120 },
  { key: 'unit_id', label: 'Unit kerja', type: 'select', options: props.units }, { key: 'scope_units', label: 'Unit tambahan yang boleh diakses (Risk Officer/Owner)', type: 'multi', options: props.units, span: true },
  ...(item.value ? [{ key: 'active', label: 'Akun aktif', type: 'checkbox', default: true }] : [{ key: 'password', label: 'Kata sandi awal (kosongkan untuk dibuat otomatis)', type: 'text', span: true, hint: 'Pengguna wajib mengganti sandi saat pertama masuk.' }]),
]);
const resetMfa = (u) => { if (confirm(`Reset MFA ${u.name}? Kode pemulihan dan perangkat tepercayanya dicabut, sesinya diputus.`)) router.post(`/admin/users/${u.id}/reset-mfa`, {}, { preserveScroll: true }); };
const reset = (u) => { if (confirm(`Reset kata sandi ${u.name}? Sandi sementara ditampilkan sekali.`)) router.post(`/admin/users/${u.id}/reset-password`, {}, { preserveScroll: true }); };
const copy = () => navigator.clipboard?.writeText(shown.value.password).then(() => toast('Disalin.', 'ok'));
</script>
<template>
  <Head title="Pengguna & akun" />
  <PageHead kicker="Administrasi" title="Pengguna & akun" sub="Kelola akun, peran, cakupan unit, dan status aktif. Pembuatan, reset sandi, dan penonaktifan dicatat pada log autentikasi.">
    <button type="button" class="btn c-green" @click="item = null; modal = true"><Icon name="plus" />Tambah pengguna</button>
  </PageHead>
  <div class="kpis"><Kpi label="Total akun" :value="stats.total" /><Kpi label="Aktif" :value="stats.active" level="l" /><Kpi v-for="(n, r) in stats.by_role" :key="r" :label="L.roles[r] || r" :value="n" /></div>
  <div class="card"><div class="card-b filters"><Field v-model="f.q" label="Cari" placeholder="nama / email" style="flex:1" /><Field v-model="f.role" type="select" label="Peran" :options="L.roles" empty="Semua" /><Field v-model="f.active" type="select" label="Status" :options="{ 1: 'Aktif', 0: 'Nonaktif' }" empty="Semua" /></div>
    <div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Pengguna</th><th>Peran</th><th>Unit</th><th>Login terakhir</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="u in users.data" :key="u.id"><td><div class="row" style="gap:10px"><span class="avatar">{{ fmt.initials(u.name) }}</span><div><b>{{ u.name }}</b><span v-if="u.id === me.id" class="hint"> (Anda)</span><div class="t-sub mono">{{ u.email }}</div><div class="t-sub">{{ u.position }}</div></div></div></td><td><Pill :value="u.role_label" tone="run" /></td><td class="t-sub">{{ u.unit || '—' }}<div v-if="u.scope_units?.length" class="hint">+{{ u.scope_units.length }} unit</div></td><td class="t-sub">{{ fmt.datetime(u.last_login_at) }}<div class="mono hint">{{ u.last_login_ip }}</div></td><td><Pill :value="u.active ? 'active' : 'inactive'" /><Pill v-if="u.must_change_password" value="Wajib ganti sandi" tone="warn" /><Pill v-if="u.mfa_enabled || u.mfa_required" :value="u.mfa_enabled ? 'MFA' : 'MFA (kebijakan)'" tone="ok" /></td><td><span class="row" style="justify-content:flex-end"><button type="button" class="btn sm c-blue" @click="item = u; modal = true">Ubah</button><button type="button" class="btn sm c-amber" @click="reset(u)">Reset sandi</button><button v-if="u.mfa_enabled" type="button" class="btn sm c-cyan" @click="resetMfa(u)">Reset MFA</button><button v-if="u.id !== me.id" type="button" class="btn sm" :class="u.active ? 'c-orange' : 'c-green'" @click="router.post(`/admin/users/${u.id}/toggle`, {}, { preserveScroll: true })">{{ u.active ? 'Nonaktifkan' : 'Aktifkan' }}</button><ConfirmButton v-if="u.id !== me.id" :href="`/admin/users/${u.id}`" message="Hapus pengguna ini? Akun dinonaktifkan dan dihapus (soft delete)." /></span></td></tr>
    </tbody></table></div><Pagination :data="users" /></div>
  <div class="card"><div class="card-h"><h3>Log autentikasi terbaru</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Peristiwa</th><th>Email</th><th>IP</th><th>Detail</th></tr></thead><tbody><tr v-for="l in auth_logs" :key="l.id"><td style="white-space:nowrap">{{ fmt.datetime(l.created_at) }}</td><td><span class="pill" :class="/failed|locked|disabled|expired/.test(l.event) ? 'bad' : 'ok'">{{ l.event }}</span></td><td class="mono">{{ l.email }}</td><td class="mono">{{ l.ip }}</td><td class="t-sub">{{ l.detail }}</td></tr></tbody></table></div></div>
  <CrudModal :show="modal" :title="item ? 'Ubah akun' : 'Tambah pengguna'" :fields="fields" :item="item" :url="item ? `/admin/users/${item.id}` : '/admin/users'" :method="item ? 'put' : 'post'" submitLabel="Simpan" @close="modal = false" />
  <Modal :show="!!shown" title="Kata sandi sementara" :closeable="false">
    <p>Akun <b>{{ shown?.email }}</b>. Sampaikan sandi ini lewat saluran aman; sandi tidak disimpan dan tidak dapat ditampilkan lagi.</p>
    <div class="pw-show"><code>{{ shown?.password }}</code><button type="button" class="btn sm c-blue" @click="copy"><Icon name="copy" />Salin</button></div>
    <template #footer><button class="btn c-green" @click="shown = null">Sudah saya catat</button></template>
  </Modal>
</template>
