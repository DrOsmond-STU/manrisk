<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import Pagination from '../../Components/Pagination.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ reviews: Object, filters: Object, periods: Array, period_now: String, due: Array, risks: Array, can: Object });
const target = ref(null);
const form = useForm({ risk_id: '', period_type: 'quarterly', period: props.period_now, note: '', decision: 'continue' });
const open = (r) => { target.value = r || { id: '', code: '' }; form.risk_id = r?.id || ''; form.note = ''; form.decision = 'continue'; form.clearErrors(); };
const save = () => form.post('/reviews', { preserveScroll: true, onSuccess: () => (target.value = null) });
const isAdmin = computed(() => ['super_admin', 'risk_admin', 'risk_manager'].includes(usePage().props.auth.user.role));
import { usePage } from '@inertiajs/vue3';
</script>
<template>
  <Head title="Risk review" />
  <PageHead kicker="ISO 31000 §6.6" title="Review & evaluasi berkala" :sub="`Periode berjalan ${period_now}. ${due.length} risiko belum direview periode ini.`">
    <button v-if="can.write" type="button" class="btn c-green" @click="open(null)"><Icon name="plus" />Catat review</button>
    <button v-if="isAdmin" type="button" class="btn c-violet" @click="router.post('/reviews/snapshot')"><Icon name="clock" />Snapshot bulan ini</button>
  </PageHead>
  <div class="s-grid">
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Perlu direview ({{ period_now }})</h3></div><div class="card-b flush tbl-wrap" style="max-height:520px;overflow:auto"><table class="tbl"><thead><tr><th>Risiko</th><th class="num">Skor</th><th></th></tr></thead><tbody>
      <tr v-for="r in due" :key="r.id"><td class="wrap"><Link :href="`/risks/${r.id}`" class="code-link">{{ r.code }}</Link> {{ r.name }}<div class="t-sub">{{ r.unit?.name }}</div></td><td class="num"><Pill kind="level" :value="r.residual_level" /> {{ r.residual_score }}</td><td><button v-if="can.write" type="button" class="btn sm c-teal" @click="open(r)">Review</button></td></tr>
      <tr v-if="!due.length"><td colspan="3"><div class="empty">Semua risiko sudah direview periode ini.</div></td></tr></tbody></table></div></div>
    <div class="card" style="grid-column:span 7"><div class="card-h"><h3>Riwayat review</h3><select class="sel sel-inline" :value="filters.period || ''" @change="router.get('/reviews', { period: $event.target.value }, { preserveState: true })"><option value="">Semua periode</option><option v-for="p in periods" :key="p" :value="p">{{ p }}</option></select></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Periode</th><th>Risiko</th><th class="num">Skor</th><th>Tren</th><th>Keputusan</th><th>Reviewer</th><th></th></tr></thead><tbody>
      <tr v-for="v in reviews.data" :key="v.id"><td class="mono">{{ v.period }}</td><td class="wrap"><Link :href="`/risks/${v.risk_id}`" class="code-link">{{ v.risk?.code }}</Link> {{ v.risk?.name }}<div class="t-sub">{{ v.note }}</div></td><td class="num mono">{{ v.previous_score ?? '—' }} → {{ v.current_score }}</td><td><Pill :value="v.trend" :tone="v.trend === 'up' ? 'bad' : v.trend === 'down' ? 'ok' : ''" /></td><td><Pill :value="v.decision" /></td><td class="t-sub">{{ v.reviewer?.name }}<br>{{ fmt.date(v.signed_at) }}</td><td><ConfirmButton v-if="isAdmin" :href="`/reviews/${v.id}`" /></td></tr>
      <tr v-if="!reviews.data.length"><td colspan="7"><div class="empty">Belum ada review.</div></td></tr></tbody></table></div><Pagination :data="reviews" /></div>
  </div>
  <Modal :show="!!target" title="Catat hasil review" @close="target = null">
    <div class="stack">
      <Field v-model="form.risk_id" type="select" label="Risiko" :options="risks.map((r) => ({ id: r.id, name: `${r.code} · ${r.name}` }))" required :error="form.errors.risk_id" />
      <div class="row" style="gap:8px"><Field v-model="form.period_type" type="select" label="Jenis" :options="{ monthly: 'Bulanan', quarterly: 'Triwulanan', semester: 'Semesteran', annual: 'Tahunan', adhoc: 'Ad hoc' }" empty="" style="flex:1" /><Field v-model="form.period" label="Periode" required style="flex:1" :error="form.errors.period" /></div>
      <Field v-model="form.decision" type="select" label="Keputusan" :options="{ continue: 'Lanjutkan treatment', change_treatment: 'Ubah treatment', close: 'Rekomendasi tutup (butuh persetujuan)', escalate: 'Eskalasi ke manajemen' }" empty="" required />
      <Field v-model="form.note" type="textarea" label="Catatan" :rows="4" />
    </div>
    <template #footer><button class="btn ghost c-indigo" @click="target = null">Batal</button><button class="btn c-teal" :disabled="form.processing" @click="save">Simpan review</button></template>
  </Modal>
</template>
