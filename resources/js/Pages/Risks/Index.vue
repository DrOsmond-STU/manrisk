<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Pagination from '../../Components/Pagination.vue';
import Field from '../../Components/Field.vue';
const props = defineProps({ risks: Object, filters: Object, units: Array, categories: Array, owners: Array, can: Object });
const L = usePage().props.labels;
const f = reactive({ q: props.filters.q || '', unit_id: props.filters.unit_id || '', category_id: props.filters.category_id || '', level: props.filters.level || '', status: props.filters.status || '', evaluation: props.filters.evaluation || '', owner_id: props.filters.owner_id || '', sort: props.filters.sort || 'residual_score', dir: props.filters.dir || 'desc' });
let t;
const apply = () => router.get('/risks', Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null)), { preserveState: true, replace: true });
watch(f, () => { clearTimeout(t); t = setTimeout(apply, 350); });
const sortBy = (c) => { if (f.sort === c) f.dir = f.dir === 'asc' ? 'desc' : 'asc'; else { f.sort = c; f.dir = c === 'name' || c === 'code' ? 'asc' : 'desc'; } };
const reset = () => Object.assign(f, { q: '', unit_id: '', category_id: '', level: '', status: '', evaluation: '', owner_id: '' });
</script>
<template>
  <Head title="Risk Register" />
  <PageHead kicker="Manajemen Risiko" title="Risk Register" :sub="`${risks.total} risiko terdaftar · identifikasi, analisis, evaluasi, dan treatment dalam satu register.`">
    <Link v-if="can.create" href="/risks/create" class="btn c-green"><Icon name="plus" />Identifikasi risiko baru</Link>
    <Link href="/risks/matrix" class="btn c-violet"><Icon name="target" />Matriks</Link>
  </PageHead>
  <div class="card"><div class="card-b filters">
    <Field v-model="f.q" placeholder="Cari kode / nama / peristiwa" label="Cari" style="flex:1;min-width:200px" />
    <Field v-model="f.unit_id" type="select" label="Unit" :options="units" empty="Semua unit" />
    <Field v-model="f.category_id" type="select" label="Kategori" :options="categories" empty="Semua" />
    <Field v-model="f.level" type="select" label="Level" :options="L.levels" empty="Semua" />
    <Field v-model="f.evaluation" type="select" label="Evaluasi" :options="L.evaluations" empty="Semua" />
    <Field v-model="f.status" type="select" label="Status" :options="L.risk_statuses" empty="Semua" />
    <Field v-model="f.owner_id" type="select" label="Pemilik" :options="owners" empty="Semua" />
    <button type="button" class="btn ghost c-indigo" @click="reset">Reset</button>
  </div></div>
  <div class="card"><div class="card-b flush tbl-wrap"><table class="tbl">
    <thead><tr><th class="sortable" :class="{ on: f.sort === 'code' }" @click="sortBy('code')">Kode</th><th class="sortable" :class="{ on: f.sort === 'name' }" @click="sortBy('name')">Risiko</th><th>Unit / Pemilik</th><th>Kategori</th><th class="num sortable" :class="{ on: f.sort === 'inherent_score' }" @click="sortBy('inherent_score')">Inheren</th><th class="num sortable" :class="{ on: f.sort === 'residual_score' }" @click="sortBy('residual_score')">Residual</th><th>Level</th><th>Evaluasi</th><th>Status</th><th class="num">AP / Ktrl / KRI</th></tr></thead>
    <tbody>
      <tr v-for="r in risks.data" :key="r.id" class="click" @click="router.visit(`/risks/${r.id}`)"><td><span class="code-link">{{ r.code }}</span></td><td class="wrap"><div class="t-main">{{ r.name }}</div><div class="t-sub clamp">{{ r.event }}</div></td><td class="t-sub">{{ r.unit?.name }}<br>{{ r.owner?.name }}</td><td class="t-sub">{{ r.category?.name }}</td><td class="num mono">{{ r.inherent_score }}</td><td class="num mono"><b>{{ r.residual_score }}</b> <span class="muted" style="font-size:11px">({{ r.residual_l }}×{{ r.residual_i }})</span></td><td><Pill kind="level" :value="r.residual_level" /></td><td><Pill kind="evaluation" :value="r.evaluation" /></td><td><Pill :value="r.status" /></td><td class="num mono">{{ r.action_plans_count }} / {{ r.controls_count }} / {{ r.kris_count }}</td></tr>
      <tr v-if="!risks.data.length"><td colspan="10"><div class="empty">Tidak ada risiko yang cocok dengan filter.</div></td></tr>
    </tbody></table></div><Pagination :data="risks" /></div>
</template>
