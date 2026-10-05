<script setup>
import { onMounted, onUnmounted, watch } from 'vue';
const props = defineProps({ show: Boolean, title: String, wide: Boolean, closeable: { type: Boolean, default: true } });
const emit = defineEmits(['close']);
const onKey = (e) => { if (e.key === 'Escape' && props.show && props.closeable) emit('close'); };
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
watch(() => props.show, (v) => { document.body.style.overflow = v ? 'hidden' : ''; });
</script>
<template>
  <Teleport to="body">
    <div v-if="show">
      <div class="scrim" @click="closeable && emit('close')"></div>
      <div class="modal" :class="{ wide }" role="dialog" aria-modal="true" :aria-label="title">
        <div class="drawer-h"><h3>{{ title }}</h3><button v-if="closeable" type="button" class="icon-btn c-indigo" aria-label="Tutup" @click="emit('close')"><Icon name="x" /></button></div>
        <div class="modal-b"><slot /></div>
        <div v-if="$slots.footer" class="row modal-f"><slot name="footer" /></div>
      </div>
    </div>
  </Teleport>
</template>
