<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Kpi from '../../Components/Kpi.vue';
const props = defineProps({ risks: Array, counts: Object });
const L = usePage().props.labels;
const filter = ref('');
const list = computed(() => props.risks.filter((r) => !filter.value || r.evaluation === filter.value));
</script>
<template>
  <Head title="Evaluasi risiko" />
  <PageHead kicker="Analisis Risiko" title="Evaluasi risiko" sub="Perbandingan skor residual dengan risk appetite dan tolerance tiap kategori (ISO 31000 §6.4.4). Status evaluasi menentukan keputusan: terima, pantau, tangani, eskalasi." />
  <div class="kpis"><button v-for="(n, k) in counts" :key="k" type="button" class="kpi" :class="{ lvl: true }" :style="`--c:var(--lv-${{ acceptable: 'l', monitor: 'm', treat: 'h', escalate: 'vh', critical: 'vh' }[k]});cursor:pointer;text-align:left;outline:${filter === k ? '2px solid var(--accent)' : 'none'}`" @click="filter = filter === k ? '' : k"><div class="k-l"><i class="sw"></i>{{ L.evaluations[k] }}</div><div class="k-v">{{ n }}</div></button></div>
  <div class="card"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Risiko</th><th>Kategori</th><th class="num">Residual</th><th class="num">Appetite</th><th class="num">Tolerance</th><th>Level</th><th>Evaluasi</th><th>Treatment</th><th>Status</th></tr></thead><tbody>
    <tr v-for="r in list" :key="r.id"><td><Link :href="`/risks/${r.id}`" class="code-link">{{ r.code }}</Link></td><td class="t-main wrap">{{ r.name }}<div class="t-sub">{{ r.unit?.name }} · {{ r.owner?.name }}</div></td><td class="t-sub">{{ r.category?.name }}</td><td class="num mono"><b>{{ r.residual_score }}</b></td><td class="num mono">{{ r.category?.appetite }}</td><td class="num mono">{{ r.category?.tolerance }}</td><td><Pill kind="level" :value="r.residual_level" /></td><td><Pill kind="evaluation" :value="r.evaluation" /></td><td>{{ L.treatments[r.treatment] }}</td><td><Pill :value="r.status" /></td></tr>
  </tbody></table></div></div>
</template>
