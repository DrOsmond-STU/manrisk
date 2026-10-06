<script setup>
/* Filter unit & kategori untuk halaman analisis (matriks, evaluasi, residual) — sama dengan Risk Register. */
import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Field from './Field.vue';
const props = defineProps({ path: { type: String, required: true }, filters: { type: Object, default: () => ({}) }, units: { type: Array, default: () => [] }, categories: { type: Array, default: () => [] } });
const f = reactive({ unit_id: props.filters.unit_id || '', category_id: props.filters.category_id || '', objective_id: props.filters.objective_id || '', owner_id: props.filters.owner_id || '', process_id: props.filters.process_id || '' });
const clean = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null));
watch(f, () => router.get(props.path, clean(), { preserveState: true, replace: true }));
defineExpose({ query: () => new URLSearchParams(clean()).toString() });
</script>
<template>
  <div class="card"><div class="card-b filters">
    <Field v-model="f.unit_id" type="select" label="Unit (termasuk sub-unit)" :options="units" empty="Semua unit" />
    <Field v-model="f.category_id" type="select" label="Kategori" :options="categories" empty="Semua kategori" />
    <span v-if="f.objective_id || f.owner_id || f.process_id" class="pill run">Filter tambahan dari halaman sebelumnya</span>
    <button v-if="Object.values(f).some((v) => v)" type="button" class="btn ghost c-indigo" @click="Object.assign(f, { unit_id: '', category_id: '', objective_id: '', owner_id: '', process_id: '' })">Reset</button>
  </div></div>
</template>
