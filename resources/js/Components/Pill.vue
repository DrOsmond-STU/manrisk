<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { STATUS_PILL, EVAL_PILL, lvKey } from '../lib/format';
/* kind: status | evaluation | level | custom */
const props = defineProps({ value: [String, Number], kind: { type: String, default: 'status' }, map: { type: String, default: null }, tone: { type: String, default: null } });
const page = usePage();
const label = computed(() => {
  const L = page.props.labels || {};
  if (props.kind === 'level') return (L.levels || {})[props.value] || props.value;
  if (props.kind === 'evaluation') return (L.evaluations || {})[props.value] || props.value;
  if (props.map && L[props.map]) return L[props.map][props.value] || props.value;
  const all = { ...(L.risk_statuses || {}), ...(L.incident_statuses || {}), open: 'Terbuka', in_progress: 'Berjalan', done: 'Selesai', todo: 'Belum Mulai', verify: 'Menunggu Verifikasi', running: 'Berjalan', overdue: 'Terlambat', cancelled: 'Dibatalkan', normal: 'Normal', warning: 'Waspada', critical: 'Kritis', approved: 'Disetujui', rejected: 'Ditolak', revision: 'Revisi', pending: 'Menunggu', met: 'Terpenuhi', partial: 'Sebagian', unmet: 'Belum', review: 'Review', expired: 'Kedaluwarsa', draft: 'Draft', active: 'Aktif', inactive: 'Nonaktif', planned: 'Terjadwal', up: 'Naik', down: 'Turun', flat: 'Tetap', internal: 'Internal', external: 'Eksternal', preventive: 'Preventif', detective: 'Detektif', corrective: 'Korektif', manual: 'Manual', automated: 'Otomatis', strength: 'Kekuatan', weakness: 'Kelemahan', opportunity: 'Peluang', threat: 'Ancaman', continue: 'Lanjutkan', change_treatment: 'Ubah Treatment', close: 'Tutup', escalate: 'Eskalasi' };
  return all[props.value] || props.value;
});
const cls = computed(() => props.tone || (props.kind === 'evaluation' ? EVAL_PILL[props.value] : (props.kind === 'level' ? '' : (STATUS_PILL[props.value] ?? ''))));
</script>
<template>
  <span v-if="kind === 'level'" class="lv" :class="'lv-' + lvKey(value)"><i></i>{{ label }}</span>
  <span v-else class="pill" :class="cls">{{ label }}</span>
</template>
