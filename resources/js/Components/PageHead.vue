<script setup>
defineProps({ title: String, sub: String, kicker: String });
</script>
<template>
  <div class="page-h row between">
    <div><div v-if="kicker" class="kicker">{{ kicker }}</div><h1>{{ title }}</h1><p v-if="sub" class="muted">{{ sub }}</p></div>
    <div v-if="$slots.default" class="row"><slot /></div>
  </div>
</template>
