<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Pill from '../../Components/Pill.vue';
import Kpi from '../../Components/Kpi.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import Chart from '../../Components/Chart.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ kris: Array, filters: Object, filter_labels: Object, stats: Object, risks: Array, users: Array, can: Object });
const modal = ref(false); const item = ref(null); const val = ref(null); const detail = ref(null);
const fields = computed(() => [
  { key: 'name', label: 'Nama indikator', required: true, span: true }, { key: 'unit', label: 'Satuan', placeholder: '%, jam, kejadian' },
  { key: 'risk_id', label: 'Risiko yang dipantau', type: 'select', options: props.risks.map((r) => ({ id: r.id, name: `${r.code} · ${r.name}` })) }, { key: 'owner_id', label: 'Penanggung jawab', type: 'select', options: props.users },
  { key: 'direction', label: 'Arah buruk', type: 'select', options: { up_bad: 'Nilai naik = memburuk', down_bad: 'Nilai turun = memburuk' }, empty: '', required: true, default: 'up_bad' },
  { key: 'threshold_warn', label: 'Ambang waspada', type: 'number', step: 'any', required: true }, { key: 'threshold_crit', label: 'Ambang kritis', type: 'number', step: 'any', required: true },
  { key: 'frequency', label: 'Frekuensi', type: 'select', options: { daily: 'Harian', weekly: 'Mingguan', monthly: 'Bulanan', quarterly: 'Triwulanan' }, empty: '', required: true, default: 'monthly' },
  { key: 'source', label: 'Sumber data', type: 'select', options: { manual: 'Input manual', api: 'API', import: 'Impor' }, empty: '', required: true, default: 'manual' },
  { key: 'decimals', label: 'Desimal', type: 'number', min: 0, max: 4, default: 0 }, { key: 'active', label: 'Aktif', type: 'checkbox', default: true },
]);
const statusLabel = { breach: 'Melewati ambang (waspada + kritis)', critical: 'Kritis', warning: 'Waspada', normal: 'Normal' };
const setStatus = (s) => router.get('/kris', Object.fromEntries(Object.entries({ ...props.filters, status: props.filters.status === s ? '' : s }).filter(([, v]) => v)), { preserveState: true, replace: true });
const vf = useForm({ period: new Date().toISOString().slice(0, 7), value: null, source_ref: '' });
const saveVal = () => vf.post(`/kris/${val.value.id}/values`, { preserveScroll: true, onSuccess: () => (val.value = null) });
const chart = (k) => ({ xAxis: { type: 'category', data: k.series.map((s) => fmt.period(s.period)) }, yAxis: { type: 'value' }, series: [{ type: 'line', smooth: true, data: k.series.map((s) => s.value), areaStyle: { opacity: .1 }, markLine: { silent: true, symbol: 'none', data: [{ yAxis: k.threshold_warn, lineStyle: { color: '#fab219' }, label: { formatter: 'Waspada' } }, { yAxis: k.threshold_crit, lineStyle: { color: '#d03b3b' }, label: { formatter: 'Kritis' } }] } }] });
</script>
<template>
  <Head title="KRI & early warning" />
  <PageHead kicker="Pemantauan" title="Key Risk Indicator & early warning" sub="Indikator kuantitatif dengan ambang waspada/kritis. Nilai yang melewati ambang memicu peringatan dini otomatis ke pemilik risiko dan Risk Manager.">
    <button v-if="can.write" type="button" class="btn c-green" @click="item = null; modal = true"><Icon name="plus" />KRI baru</button>
    <Link v-if="can.write" href="/import/kri" class="btn c-teal"><Icon name="upload" />Impor nilai (Excel)</Link>
  </PageHead>
  <div class="kpis"><Kpi label="Total KRI" :value="stats.total" /><Kpi label="Kritis" :value="stats.critical" level="vh" sub="melewati ambang kritis" /><Kpi label="Waspada" :value="stats.warning" level="m" sub="zona peringatan" /><Kpi label="Normal" :value="stats.normal" level="l" /></div>
  <div class="row" style="gap:6px;flex-wrap:wrap;margin-bottom:12px"><span class="hint">Status:</span><button v-for="(l, s) in statusLabel" :key="s" type="button" class="btn sm" :class="filters.status === s ? 'c-indigo' : 'ghost c-indigo'" @click="setStatus(s)">{{ s === 'breach' ? 'Melewati ambang' : l }}</button>
    <template v-if="filters.risk_id || filters.unit_id || filters.status"><span v-if="filter_labels.risk" class="pill run">Risiko {{ filter_labels.risk.code }} · {{ filter_labels.risk.name }}</span><span v-if="filter_labels.unit" class="pill run">Unit {{ filter_labels.unit }} (termasuk sub-unit)</span><span v-if="filters.status" class="pill run">Status {{ statusLabel[filters.status] }}</span><Link href="/kris" class="btn sm ghost c-indigo">hapus filter</Link></template></div>
  <div class="kri-grid">
    <div v-for="k in kris" :key="k.id" class="kri" :style="k.status === 'critical' ? 'border-color:var(--lv-vh)' : k.status === 'warning' ? 'border-color:var(--lv-m)' : ''">
      <div class="kh"><div><div class="mono muted" style="font-size:11px">{{ k.code }} · <Link v-if="k.risk" :href="`/risks/${k.risk.id}`" class="code-link">{{ k.risk.code }}</Link></div><div class="kn">{{ k.name }}</div><Link v-if="k.risk" :href="`/risks/${k.risk.id}`" class="hint plain">Risiko: {{ k.risk.name }} →</Link></div><Pill :value="k.status" /></div>
      <div class="kval">{{ fmt.num(k.last_value, k.decimals) }} <small>{{ k.unit }}</small></div>
      <div class="th"><span>Waspada {{ k.direction === 'down_bad' ? '<' : '>' }} {{ fmt.num(k.threshold_warn, k.decimals) }}</span><span>Kritis {{ k.direction === 'down_bad' ? '<' : '>' }} {{ fmt.num(k.threshold_crit, k.decimals) }}</span></div>
      <Chart v-if="k.series.length" :option="chart(k)" height="110px" />
      <div class="hint">{{ k.owner?.name || '—' }} · {{ k.frequency }} · {{ k.series.length }} data</div>
      <Link v-if="k.improvement" :href="`/improvements?subject_type=kri&subject_id=${k.id}`" class="alert-box warn plain" style="margin-top:4px;font-size:12px"><Icon name="up" /><span>Improvement {{ k.improvement.code }} · {{ k.improvement.title }} <Pill :value="k.improvement.status" /></span></Link>
      <div class="row" style="margin-top:4px"><button v-if="can.write" type="button" class="btn sm c-teal" @click="val = k; vf.value = null">Input nilai</button><button v-if="can.write" type="button" class="btn sm c-blue" @click="item = k; modal = true">Ubah</button><button type="button" class="btn sm ghost c-indigo" @click="detail = k">Riwayat</button><ConfirmButton v-if="can.delete" :href="`/kris/${k.id}`" /></div>
    </div>
    <div v-if="!kris.length" class="empty" style="grid-column:1/-1">{{ filters.risk_id || filters.unit_id || filters.status ? 'Tidak ada KRI sesuai filter.' : 'Belum ada KRI.' }}</div>
  </div>
  <CrudModal :show="modal" :title="item ? 'Ubah KRI' : 'KRI baru'" :fields="fields" :item="item" :url="item ? `/kris/${item.id}` : '/kris'" :method="item ? 'put' : 'post'" wide @close="modal = false" />
  <Modal :show="!!val" :title="`Input nilai · ${val?.name}`" @close="val = null">
    <div class="form-grid"><Field v-model="vf.period" type="month" label="Periode" required :error="vf.errors.period" /><Field v-model="vf.value" type="number" step="any" :label="`Nilai (${val?.unit || ''})`" required :error="vf.errors.value" /><Field v-model="vf.source_ref" label="Referensi sumber" span /></div>
    <template #footer><button class="btn ghost c-indigo" @click="val = null">Batal</button><button class="btn c-green" :disabled="vf.processing" @click="saveVal">Simpan</button></template>
  </Modal>
  <Modal :show="!!detail" :title="`Riwayat · ${detail?.name}`" @close="detail = null">
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Periode</th><th class="num">Nilai</th><th>Status</th></tr></thead><tbody><tr v-for="s in (detail?.series || []).slice().reverse()" :key="s.period"><td>{{ fmt.period(s.period) }}</td><td class="num mono">{{ fmt.num(s.value, detail.decimals) }}</td><td><Pill :value="detail.direction === 'down_bad' ? (s.value < detail.threshold_crit ? 'critical' : s.value < detail.threshold_warn ? 'warning' : 'normal') : (s.value > detail.threshold_crit ? 'critical' : s.value > detail.threshold_warn ? 'warning' : 'normal')" /></td></tr></tbody></table></div>
  </Modal>
</template>
