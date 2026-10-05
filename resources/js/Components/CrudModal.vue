<script setup>
/* Modal form generik untuk CRUD master: fields = [{key,label,type,options,required,span,hint,...}] */
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import Field from './Field.vue';
const props = defineProps({ show: Boolean, title: String, fields: Array, item: Object, url: String, method: { type: String, default: 'post' }, wide: Boolean, submitLabel: { type: String, default: 'Simpan' } });
const emit = defineEmits(['close', 'saved']);
const blank = () => Object.fromEntries(props.fields.map((f) => [f.key, f.default ?? (f.type === 'checkbox' ? true : f.type === 'multi' ? [] : '')]));
const form = useForm(blank());
watch(() => [props.show, props.item], () => { if (props.show) { form.clearErrors(); const b = blank(); for (const k of Object.keys(b)) { let v = props.item?.[k]; if (v === undefined || v === null) v = b[k]; if (props.fields.find((f) => f.key === k)?.type === 'date' && v) v = String(v).slice(0, 10); if (props.fields.find((f) => f.key === k)?.type === 'datetime-local' && v) v = String(v).replace(' ', 'T').slice(0, 16); form[k] = v; } } }, { immediate: true });
const submit = () => form.submit(props.method, props.url, { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } });
</script>
<template>
  <Modal :show="show" :title="title" :wide="wide" @close="emit('close')">
    <form class="form-grid" :class="{ 'cols-3': wide }" @submit.prevent="submit">
      <Field v-for="f in fields" :key="f.key" v-model="form[f.key]" v-bind="f" :error="form.errors[f.key]" />
      <button type="submit" hidden></button>
    </form>
    <template #footer><button type="button" class="btn ghost c-indigo" @click="emit('close')">Batal</button><button type="button" class="btn c-green" :disabled="form.processing" @click="submit">{{ submitLabel }}</button></template>
  </Modal>
</template>
