<script setup>
defineProps({ label: String, value: [String, Number], sub: String, color: String, level: String });
</script>
<template>
  <div class="kpi" :class="{ lvl: !!level }" :style="level ? `--c:var(--lv-${level})` : ''">
    <div class="k-l"><i v-if="level" class="sw"></i>{{ label }}</div>
    <div class="k-v" :style="color ? `color:${color}` : ''">{{ value }}</div>
    <div v-if="sub" class="k-s">{{ sub }}</div>
  </div>
</template>
