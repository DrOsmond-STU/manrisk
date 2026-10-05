<script setup>
import { router } from '@inertiajs/vue3';
const props = defineProps({ href: String, method: { type: String, default: 'delete' }, message: { type: String, default: 'Hapus data ini? Tindakan tercatat di audit trail.' }, label: { type: String, default: 'Hapus' }, cls: { type: String, default: 'btn sm danger' }, data: { type: Object, default: () => ({}) } });
const go = () => { if (window.confirm(props.message)) router[props.method](props.href, props.data, { preserveScroll: true }); };
</script>
<template><button type="button" :class="cls" @click="go"><slot>{{ label }}</slot></button></template>
