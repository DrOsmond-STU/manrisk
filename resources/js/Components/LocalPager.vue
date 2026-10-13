<script setup>
/* Navigasi halaman untuk usePager (paginasi sisi klien); tampilannya sama dengan Pagination server. */
import { computed } from 'vue';
const props = defineProps({ p: Object });
const nums = computed(() => {
  const { page, pages } = props.p; const out = [];
  for (let i = 1; i <= pages; i++) if (i === 1 || i === pages || Math.abs(i - page) <= 1) out.push(i); else if (out[out.length - 1] !== '…') out.push('…');
  return out;
});
</script>
<template>
  <div v-if="p && p.pages > 1" class="pager row between">
    <span class="hint">{{ p.from }}–{{ p.to }} dari {{ p.total }}</span>
    <div class="row">
      <button type="button" class="btn sm ghost c-indigo" :disabled="p.page === 1" aria-label="Halaman sebelumnya" @click="p.go(p.page - 1)">‹</button>
      <template v-for="(n, i) in nums" :key="i"><span v-if="n === '…'" class="hint">…</span><button v-else type="button" class="btn sm" :class="n === p.page ? 'c-blue' : 'ghost c-indigo'" :aria-current="n === p.page ? 'page' : null" @click="p.go(n)">{{ n }}</button></template>
      <button type="button" class="btn sm ghost c-indigo" :disabled="p.page === p.pages" aria-label="Halaman berikutnya" @click="p.go(p.page + 1)">›</button>
    </div>
  </div>
</template>
