<script setup>
/* Matriks 5×5: kemungkinan (baris, 5 di atas) × dampak (kolom). counts: {"l-i": n}; matrix: {"l-i": level} */
import { computed } from 'vue';
import { lvKey, lvFromScore } from '../lib/format';
const props = defineProps({ counts: { type: Object, default: () => ({}) }, matrix: { type: Object, default: null }, selected: String, pick: Boolean, likelihood: Array, impact: Array, compact: Boolean });
const emit = defineEmits(['select']);
const level = (l, i) => (props.matrix && props.matrix[`${l}-${i}`]) || lvFromScore(l * i);
const max = computed(() => Math.max(1, ...Object.values(props.counts || {})));
</script>
<template>
  <div class="hm" :class="{ compact }">
    <span class="yt">Kemungkinan →</span>
    <template v-for="l in [5, 4, 3, 2, 1]" :key="l">
      <span class="ax y"><b>{{ l }}</b><span v-if="likelihood && !compact" class="axl">{{ likelihood[l - 1]?.label }}</span></span>
      <button v-for="i in 5" :key="i" type="button" class="cell" :class="['lv-' + lvKey(level(l, i)), { zero: !counts[`${l}-${i}`], hot: (counts[`${l}-${i}`] || 0) >= max * 0.6, sel: selected === `${l}-${i}` }]" :title="`Kemungkinan ${l} × Dampak ${i} = ${l * i}`" @click="emit('select', `${l}-${i}`, l, i)">
        <b>{{ pick ? l * i : (counts[`${l}-${i}`] || '·') }}</b><small v-if="!pick">{{ l * i }}</small>
      </button>
    </template>
    <span></span>
    <span class="ax x"><b>&nbsp;</b></span>
    <span v-for="i in 5" :key="'x' + i" class="ax x"><b>{{ i }}</b><span v-if="impact && !compact" class="axl">{{ impact[i - 1]?.label }}</span></span>
    <span class="xt">Dampak →</span>
  </div>
</template>
