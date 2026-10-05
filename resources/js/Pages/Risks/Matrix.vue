<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Heatmap from '../../Components/Heatmap.vue';
import Pill from '../../Components/Pill.vue';
const props = defineProps({ risks: Array, matrix: Object, criteria: Object });
const L = usePage().props.labels;
const mode = ref('residual');
const sel = ref(null);
const counts = computed(() => { const c = {}; for (const r of props.risks) { const k = `${r[mode.value + '_l']}-${r[mode.value + '_i']}`; c[k] = (c[k] || 0) + 1; } return c; });
const list = computed(() => (sel.value ? props.risks.filter((r) => `${r[mode.value + '_l']}-${r[mode.value + '_i']}` === sel.value) : props.risks).slice().sort((a, b) => b[mode.value + '_score'] - a[mode.value + '_score']));
const byLevel = computed(() => { const o = { low: 0, medium: 0, high: 0, very_high: 0 }; for (const r of props.risks) { const k = `${r[mode.value + '_l']}-${r[mode.value + '_i']}`; const lv = props.matrix[k] || 'medium'; o[lv]++; } return o; });
</script>
<template>
  <Head title="Peta risiko" />
  <PageHead kicker="Analisis Risiko" title="Peta risiko (matriks 5×5)" sub="Distribusi risiko aktif pada matriks kemungkinan × dampak. Klik sel untuk melihat daftar risikonya.">
    <div class="seg"><button v-for="m in [['inherent', 'Inheren'], ['residual', 'Residual'], ['target', 'Target']]" :key="m[0]" type="button" :class="{ on: mode === m[0] }" @click="mode = m[0]; sel = null">{{ m[1] }}</button></div>
  </PageHead>
  <div class="s-grid">
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Matriks {{ mode }}</h3><span class="sub">{{ risks.length }} risiko aktif</span></div><div class="card-b"><Heatmap :counts="counts" :matrix="matrix" :selected="sel" :likelihood="criteria?.likelihood" :impact="criteria?.impact" @select="(k) => (sel = sel === k ? null : k)" />
      <div class="row" style="margin-top:12px;gap:14px"><span v-for="(n, k) in byLevel" :key="k" class="lv" :class="'lv-' + { low: 'l', medium: 'm', high: 'h', very_high: 'vh' }[k]"><i></i>{{ L.levels[k] }} <b>{{ n }}</b></span></div></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>{{ sel ? `Sel ${sel.replace('-', ' × ')}` : 'Seluruh risiko' }}</h3><button v-if="sel" type="button" class="btn sm ghost c-indigo" @click="sel = null">Tampilkan semua</button></div><div class="card-b flush tbl-wrap" style="max-height:620px;overflow:auto"><table class="tbl"><thead><tr><th>Kode</th><th>Risiko</th><th>Unit</th><th class="num">Skor</th><th>Level</th><th>Evaluasi</th></tr></thead><tbody>
      <tr v-for="r in list" :key="r.id"><td><Link :href="`/risks/${r.id}`" class="code-link">{{ r.code }}</Link></td><td class="t-main wrap">{{ r.name }}</td><td class="t-sub">{{ r.unit?.name }}</td><td class="num mono">{{ r[mode + '_score'] }}</td><td><Pill kind="level" :value="matrix[`${r[mode + '_l']}-${r[mode + '_i']}`] || 'medium'" /></td><td><Pill kind="evaluation" :value="r.evaluation" /></td></tr>
      <tr v-if="!list.length"><td colspan="6"><div class="empty">Tidak ada risiko pada sel ini.</div></td></tr></tbody></table></div></div>
  </div>
</template>
