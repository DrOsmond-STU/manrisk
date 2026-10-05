<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ objectives: Array, programs: Array, processes: Array, units: Array, can: Object });
const tab = ref('objectives'); const modal = ref(null); const item = ref(null);
const open = (kind, it) => { modal.value = kind; item.value = it; };
const F = computed(() => ({
  objective: [{ key: 'code', label: 'Kode', required: true, maxlength: 20 }, { key: 'period', label: 'Periode', maxlength: 20, placeholder: '2026' }, { key: 'name', label: 'Sasaran strategis', required: true, span: true }, { key: 'kpi', label: 'Indikator kinerja', span: true }, { key: 'sort', label: 'Urutan', type: 'number', default: 0 }, { key: 'active', label: 'Aktif', type: 'checkbox', default: true }],
  program: [{ key: 'name', label: 'Nama program', required: true, span: true }, { key: 'objective_id', label: 'Sasaran', type: 'select', options: props.objectives.map((o) => ({ id: o.id, name: `${o.code} · ${o.name}` })) }, { key: 'unit_id', label: 'Unit', type: 'select', options: props.units }, { key: 'budget', label: 'Anggaran (Rp)', type: 'number', min: 0 }],
  process: [{ key: 'name', label: 'Nama proses bisnis', required: true, span: true }, { key: 'program_id', label: 'Program', type: 'select', options: props.programs }, { key: 'unit_id', label: 'Unit', type: 'select', options: props.units }, { key: 'description', label: 'Deskripsi', type: 'textarea', span: true }],
}));
const url = { objective: '/organization/objectives', program: '/organization/programs', process: '/organization/processes' };
</script>
<template>
  <Head title="Pemetaan sasaran" />
  <PageHead kicker="Tata Kelola" title="Pemetaan sasaran, program & proses" sub="Setiap risiko dipetakan ke sasaran strategis dan proses bisnis agar profil risiko dapat dibaca per tujuan organisasi (ISO 31000 §5.3).">
    <button v-if="can.write" type="button" class="btn c-green" @click="open(tab === 'objectives' ? 'objective' : tab === 'programs' ? 'program' : 'process', null)"><Icon name="plus" />Tambah</button>
  </PageHead>
  <div class="tabs"><button v-for="t in [['objectives', 'Sasaran strategis', objectives.length], ['programs', 'Program', programs.length], ['processes', 'Proses bisnis', processes.length]]" :key="t[0]" type="button" :class="{ on: tab === t[0] }" @click="tab = t[0]">{{ t[1] }}<span class="c">{{ t[2] }}</span></button></div>
  <div v-if="tab === 'objectives'" class="card"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Sasaran</th><th>Indikator</th><th class="num">Risiko</th><th class="num">Tinggi+</th><th class="num">Skor maks</th><th>Cakupan</th><th></th></tr></thead><tbody>
    <tr v-for="o in objectives" :key="o.id"><td class="mono">{{ o.code }}</td><td class="t-main wrap">{{ o.name }}<span v-if="!o.active" class="pill off" style="margin-left:6px">nonaktif</span></td><td class="fg2 wrap">{{ o.kpi }}</td><td class="num"><Link :href="`/risks?objective_id=${o.id}`" class="code-link">{{ o.risks_count }}</Link></td><td class="num"><b :style="o.high ? 'color:var(--bad-ink)' : ''">{{ o.high }}</b></td><td class="num mono">{{ o.max_score }}</td><td><div class="meter" style="width:90px"><i :style="`width:${Math.min(100, o.risks_count * 10)}%`"></i></div></td><td><span class="row" style="justify-content:flex-end"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open('objective', o)">Ubah</button><ConfirmButton v-if="can.delete" :href="`/organization/objectives/${o.id}`" /></span></td></tr>
  </tbody></table></div></div>
  <div v-if="tab === 'programs'" class="card"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Program</th><th>Sasaran</th><th>Unit</th><th class="num">Anggaran</th><th class="num">Proses</th><th></th></tr></thead><tbody>
    <tr v-for="p in programs" :key="p.id"><td class="t-main wrap">{{ p.name }}</td><td class="t-sub">{{ p.objective ? `${p.objective.code} · ${p.objective.name}` : '—' }}</td><td class="t-sub">{{ p.unit?.name }}</td><td class="num">{{ fmt.short(p.budget) }}</td><td class="num">{{ p.processes_count }}</td><td><span class="row" style="justify-content:flex-end"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open('program', p)">Ubah</button><ConfirmButton v-if="can.delete" :href="`/organization/programs/${p.id}`" /></span></td></tr>
  </tbody></table></div></div>
  <div v-if="tab === 'processes'" class="card"><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Proses bisnis</th><th>Program</th><th>Unit</th><th class="num">Risiko</th><th></th></tr></thead><tbody>
    <tr v-for="p in processes" :key="p.id"><td class="t-main wrap">{{ p.name }}<div class="t-sub">{{ p.description }}</div></td><td class="t-sub">{{ p.program?.name }}</td><td class="t-sub">{{ p.unit?.name }}</td><td class="num">{{ p.risks_count }}</td><td><span class="row" style="justify-content:flex-end"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open('process', p)">Ubah</button><ConfirmButton v-if="can.delete" :href="`/organization/processes/${p.id}`" /></span></td></tr>
  </tbody></table></div></div>
  <CrudModal v-for="k in ['objective', 'program', 'process']" :key="k" :show="modal === k" :title="(item ? 'Ubah ' : 'Tambah ') + { objective: 'sasaran', program: 'program', process: 'proses' }[k]" :fields="F[k]" :item="item" :url="item ? `${url[k]}/${item.id}` : url[k]" :method="item ? 'put' : 'post'" @close="modal = null" />
</template>
