<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
import Kpi from '../../Components/Kpi.vue';
import Chart from '../../Components/Chart.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ assessment: Object, result: Object, dimensions: Object, phases: Object, previous: Object, can: Object });
const a = props.assessment;
const form = useForm({ assessor: a.assessor || '', assessed_at: a.assessed_at ? String(a.assessed_at).slice(0, 10) : '', note: a.note || '', scores: a.scores.map((s) => ({ id: s.id, score: s.score, evidence: s.evidence || '' })) });
const scoreOf = (id) => form.scores.find((s) => s.id === id);
const groups = computed(() => Object.keys(props.dimensions).map((k) => ({ key: k, label: props.dimensions[k], items: a.scores.filter((s) => s.parameter?.dimension === k).sort((x, y) => (x.parameter.sort - y.parameter.sort) || x.parameter.code.localeCompare(y.parameter.code)) })).filter((g) => g.items.length));
const filled = computed(() => form.scores.filter((s) => s.score).length);
const save = () => form.put(`/rmi/${a.id}/scores`, { preserveScroll: true });
const finalize = () => { if (confirm('Finalkan penilaian? Skor tidak dapat diubah lagi.')) router.post(`/rmi/${a.id}/finalize`, {}, { preserveScroll: true }); };
const phaseLabel = (k) => props.phases[k]?.label || '—';
const radar = computed(() => ({ tooltip: {}, legend: { bottom: 0 }, radar: { indicator: Object.values(props.dimensions).map((n) => ({ name: n.replace(' dan ', ' & '), max: 5 })), radius: '60%', axisName: { fontSize: 10 } },
  series: [{ type: 'radar', data: [{ value: Object.keys(props.dimensions).map((k) => props.result.dimensions[k] ?? 0), name: `Penilaian ${a.year}`, areaStyle: { opacity: 0.2 } }, ...(props.previous ? [{ value: Object.keys(props.dimensions).map((k) => props.previous.dimensions[k] ?? 0), name: 'Penilaian final sebelumnya', lineStyle: { type: 'dashed' } }] : [])] }] }));
</script>
<template>
  <Head :title="`RMI ${a.year}`" />
  <PageHead kicker="Risk Maturity Index" :title="`Penilaian ${a.type === 'internal' ? 'internal' : 'independen'} ${a.year}`" :sub="a.status === 'final' ? `Difinalkan ${fmt.datetime(a.finalized_at)}` : 'Draf — isi skor 1–5 setiap parameter beserta bukti pendukungnya, lalu finalkan.'">
    <Link href="/rmi" class="btn ghost c-indigo">Kembali</Link>
    <a :href="`/rmi/${a.id}/pdf`" class="btn c-orange"><Icon name="file" />PDF</a>
    <button v-if="can.edit" type="button" class="btn c-blue" :disabled="form.processing" @click="save">Simpan skor</button>
    <button v-if="can.edit" type="button" class="btn c-green" @click="finalize">Finalkan</button>
  </PageHead>
  <div class="kpis">
    <Kpi label="Indeks RMI" :value="result.index ?? '—'" :sub="phaseLabel(result.phase)" />
    <Kpi label="Parameter dinilai" :value="`${can.edit ? filled : result.scored}/${result.total}`" :level="(can.edit ? filled : result.scored) === result.total ? 'l' : 'm'" />
    <Kpi v-for="(n, k) in dimensions" :key="k" :label="n" :value="result.dimensions[k] ?? '—'" />
    <Kpi label="Status" :value="a.status === 'final' ? 'Final' : 'Draf'" :level="a.status === 'final' ? 'l' : 'm'" />
  </div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>Profil dimensi</h3></div><div class="card-b"><Chart :option="radar" height="280px" /></div></div>
    <div class="card" style="grid-column:span 7"><div class="card-h"><h3>Informasi penilaian</h3></div><div class="card-b form-grid">
      <Field v-model="form.assessor" label="Penilai" :disabled="!can.edit" /><Field v-model="form.assessed_at" type="date" label="Tanggal penilaian" :disabled="!can.edit" />
      <Field v-model="form.note" type="textarea" label="Catatan / rekomendasi peningkatan" span :disabled="!can.edit" />
    </div></div>
  </div>
  <div v-for="g in groups" :key="g.key" class="card"><div class="card-h"><h3>{{ g.label }}</h3><span class="sub">skor dimensi {{ result.dimensions[g.key] ?? '—' }}</span></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th style="width:80px">Kode</th><th>Parameter</th><th class="num" style="width:60px">Bobot</th><th style="width:110px">Skor (1–5)</th><th style="width:34%">Bukti pendukung</th></tr></thead><tbody>
    <tr v-for="s in g.items" :key="s.id"><td class="mono">{{ s.parameter.code }}</td><td class="t-main wrap">{{ s.parameter.name }}<div v-if="s.parameter.guidance" class="t-sub" style="white-space:pre-line">{{ s.parameter.guidance }}</div></td><td class="num mono">{{ s.parameter.weight }}</td>
      <td><select v-if="can.edit" v-model.number="scoreOf(s.id).score" class="inp" :aria-label="`Skor ${s.parameter.code}`"><option :value="null">—</option><option v-for="v in 5" :key="v" :value="v">{{ v }}</option></select><b v-else class="mono">{{ s.score ?? '—' }}</b></td>
      <td><textarea v-if="can.edit" v-model="scoreOf(s.id).evidence" rows="2" class="inp" maxlength="2000" :aria-label="`Bukti ${s.parameter.code}`"></textarea><span v-else class="fg2 wrap">{{ s.evidence || '—' }}</span></td></tr>
  </tbody></table></div></div>
  <div v-if="form.errors && Object.keys(form.errors).length" class="alert-box bad"><Icon name="alert" /><span>{{ Object.values(form.errors)[0] }}</span></div>
</template>
