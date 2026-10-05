<script setup>
import { IC } from '../lib/icons';
const props = defineProps({ name: { type: String, required: true }, size: { type: [Number, String], default: 18 } });
</script>
<template>
  <svg class="ic" viewBox="0 0 24 24" aria-hidden="true" :width="size" :height="size" v-html="IC[name] || ''"></svg>
</template>
