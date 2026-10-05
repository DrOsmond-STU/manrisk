<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ data: Object });
</script>
<template>
  <div v-if="data && data.last_page > 1" class="pager row between">
    <span class="hint">{{ data.from }}–{{ data.to }} dari {{ data.total }}</span>
    <div class="row">
      <template v-for="(l, i) in data.links" :key="i">
        <Link v-if="l.url" :href="l.url" class="btn sm" :class="l.active ? 'c-blue' : 'ghost c-indigo'" preserve-scroll v-html="l.label.replace('&laquo; Previous', '‹').replace('Next &raquo;', '›')" />
      </template>
    </div>
  </div>
</template>
