<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import LocalPager from '../../Components/LocalPager.vue';
import { usePager } from '../../lib/pager';
import Pill from '../../Components/Pill.vue';
import ScopeFilter from '../../Components/ScopeFilter.vue';
import { fmt } from '../../lib/format';
const LIMIT_PILL = { ok: 'ok', near: 'warn', breach: 'bad', none: '' };
const LIMIT_TXT = { ok: 'Dalam limit', near: 'Mendekati limit', breach: 'Melampaui limit', none: 'Limit belum ditetapkan' };
const props = defineProps({ risks: Array, counts: Object, limits: { type: Array, default: () => [] }, total: Object, filters: { type: Object, default: () => ({}) }, units: Array, categories: Array });
const scope = computed(() => Object.entries(props.filters || {}).filter(([, v]) => v).map(([k, v]) => `&${k}=${v}`).join(''));
const L = usePage().props.labels;
const filter = ref('');
const list = computed(() => props.risks.filter((r) => !filter.value || r.evaluation === filter.value));
const pager = usePager(list, 20);
const limPager = usePager(() => props.limits, 10);
watch(filter, () => pager.go(1));
</script>
<template>
  <Head title="Evaluasi risiko" />
  <PageHead kicker="Analisis Risiko" title="Evaluasi risiko" sub="Perbandingan skor residual dengan risk appetite dan tolerance tiap kategori (ISO 31000 §6.4.4). Status evaluasi menentukan keputusan: terima, pantau, tangani, eskalasi." />
  <ScopeFilter path="/risks/evaluation" :filters="filters" :units="units" :categories="categories" />
  <div class="kpis"><button v-for="(n, k) in counts" :key="k" type="button" class="kpi" :class="{ lvl: true }" :style="`--c:var(--lv-${{ acceptable: 'l', monitor: 'm', treat: 'h', escalate: 'vh', critical: 'vh' }[k]});cursor:pointer;text-align:left;outline:${filter === k ? '2px solid var(--accent)' : 'none'}`" @click="filter = filter === k ? '' : k"><div class="k-l"><i class="sw"></i>{{ L.evaluations[k] }}</div><div class="k-v">{{ n }}</div></button></div>
  <div class="row" style="justify-content:space-between"><span class="hint">{{ filter ? `Menampilkan: ${L.evaluations[filter]}` : 'Klik kartu untuk menyaring; buka di Risk Register untuk aksi lanjutan.' }}</span><Link :href="`/risks?status=active${filter ? '&evaluation=' + filter : ''}${scope}`" class="btn sm c-blue">Buka di Risk Register</Link></div>
  <div class="card"><div class="card-h"><h3>Eksposur risiko vs risk limit</h3><span class="sub">Kuantifikasi residual dalam Rupiah; agregasi T3 → T2 → T1</span></div><div class="card-b stack">
    <div class="kpis" style="margin:0"><div class="kpi"><div class="k-l">Eksposur inheren</div><div class="k-v">{{ fmt.short(total.inherent) }}</div></div><div class="kpi"><div class="k-l">Eksposur residual</div><div class="k-v">{{ fmt.short(total.exposure) }}</div><div class="k-s">{{ total.quantified }}/{{ total.total }} risiko terkuantifikasi</div></div><div class="kpi"><div class="k-l">Target eksposur</div><div class="k-v">{{ fmt.short(total.target) }}</div></div><div class="kpi lvl" :style="`--c:var(--lv-${{ ok: 'l', near: 'm', breach: 'vh', none: 'm' }[total.status]})`"><div class="k-l"><i class="sw"></i>Risk limit organisasi</div><div class="k-v">{{ total.limit ? fmt.short(total.limit) : '—' }}</div><div class="k-s">{{ total.limit ? `${total.pct}% terpakai · ${LIMIT_TXT[total.status]}` : 'Isi di Pengaturan Organisasi' }}</div></div><div v-if="total.capacity" class="kpi"><div class="k-l">Kapasitas risiko</div><div class="k-v">{{ fmt.short(total.capacity) }}</div></div></div>
    <div v-if="limits.length" class="tbl-wrap"><table class="tbl"><thead><tr><th>Taksonomi</th><th>Kode BUMN</th><th class="num">Risiko</th><th class="num">Eksposur inheren</th><th class="num">Eksposur residual</th><th class="num">Risk limit</th><th style="width:160px">Pemakaian</th><th>Status</th></tr></thead><tbody>
      <tr v-for="c in limPager.items" :key="c.id"><td class="t-main wrap"><span :style="`padding-left:${(c.depth - 1) * 16}px`">{{ c.name }}</span></td><td class="mono">{{ c.bumn_code || '—' }}</td><td class="num"><Link :href="`/risks?category_id=${c.id}&status=active`">{{ c.n }}</Link></td><td class="num">{{ fmt.money(c.inherent) }}</td><td class="num"><b>{{ fmt.money(c.exposure) }}</b></td><td class="num">{{ c.limit !== null ? fmt.money(c.limit) : '—' }}</td><td><div v-if="c.pct !== null" class="meter"><i :style="`width:${Math.min(100, c.pct)}%;background:var(--lv-${{ ok: 'l', near: 'm', breach: 'vh' }[c.status]})`"></i></div><span v-if="c.pct !== null" class="t-sub">{{ c.pct }}%</span></td><td><span class="pill" :class="LIMIT_PILL[c.status]">{{ LIMIT_TXT[c.status] }}</span></td></tr>
    </tbody></table></div>
    <p v-else class="hint">Belum ada eksposur atau risk limit. Isi eksposur (Rp) di formulir risiko dan risk limit di menu Kriteria › Taksonomi.</p>
    <LocalPager :p="limPager" />
  </div></div>
  <div class="card"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Risiko</th><th>Kategori</th><th class="num">Residual</th><th class="num">Eksposur (Rp)</th><th class="num">Appetite</th><th class="num">Tolerance</th><th>Level</th><th>Evaluasi</th><th>Treatment</th><th>Status</th></tr></thead><tbody>
    <tr v-for="r in pager.items" :key="r.id"><td><Link :href="`/risks/${r.id}`" class="code-link">{{ r.code }}</Link></td><td class="t-main wrap">{{ r.name }}<div class="t-sub"><Link :href="`/risks?unit_id=${r.unit_id}&status=active`">{{ r.unit?.name }}</Link> · <Link :href="`/risks?owner_id=${r.owner_id}&status=active`">{{ r.owner?.name }}</Link></div></td><td class="t-sub"><Link :href="`/risks?category_id=${r.category_id}&status=active`">{{ r.category?.name }}</Link></td><td class="num mono"><b>{{ r.residual_score }}</b></td><td class="num">{{ r.residual_exposure !== null ? fmt.short(r.residual_exposure) : '—' }}</td><td class="num mono">{{ r.category?.appetite }}</td><td class="num mono">{{ r.category?.tolerance }}</td><td><Pill kind="level" :value="r.residual_level" /></td><td><Pill kind="evaluation" :value="r.evaluation" /></td><td>{{ L.treatments[r.treatment] }}</td><td><Pill :value="r.status" /></td></tr>
  </tbody></table></div><LocalPager :p="pager" /></div>
</template>
