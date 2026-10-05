<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ plan: Object, can: Object });
const L = usePage().props.labels; const p = props.plan;
</script>
<template>
  <Head :title="p.code" />
  <PageHead :kicker="`Action plan · ${p.risk?.code}`" :title="`${p.code} · ${p.title}`"><Link href="/action-plans" class="btn ghost c-indigo">‹ Daftar</Link><Link :href="`/risks/${p.risk_id}`" class="btn c-blue">Risiko {{ p.risk?.code }}</Link></PageHead>
  <div class="kpis"><div class="kpi"><div class="k-l">Status</div><div class="k-v" style="font-size:18px"><Pill :value="p.status" /></div></div><div class="kpi"><div class="k-l">Progres</div><div class="k-v">{{ p.progress }}%</div><div class="meter"><i :style="`width:${p.progress}%`"></i></div></div><div class="kpi"><div class="k-l">Tenggat</div><div class="k-v" style="font-size:20px">{{ fmt.date(p.due_date) }}</div><div class="k-s">mulai {{ fmt.date(p.start_date) }}</div></div><div class="kpi"><div class="k-l">Anggaran</div><div class="k-v" style="font-size:20px">{{ fmt.short(p.budget) }}</div></div><div class="kpi"><div class="k-l">Perkiraan penurunan</div><div class="k-v" style="font-size:20px">L −{{ p.expected_dl }} · I −{{ p.expected_di }}</div></div></div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Rincian</h3></div><div class="card-b"><dl class="kv"><dt>Uraian</dt><dd>{{ p.description || '—' }}</dd><dt>PIC</dt><dd>{{ p.pic?.name || '—' }}</dd><dt>Unit</dt><dd>{{ p.unit?.name || '—' }}</dd><dt>Prioritas</dt><dd><Pill :value="p.priority" map="priorities" /></dd><dt v-if="p.completed_at">Selesai</dt><dd v-if="p.completed_at">{{ fmt.datetime(p.completed_at) }}</dd><dt v-if="p.cancelled_at">Dibatalkan</dt><dd v-if="p.cancelled_at">{{ fmt.datetime(p.cancelled_at) }} — {{ p.cancel_reason }}</dd></dl></div></div>
    <div class="card" style="grid-column:span 6"><div class="card-h"><h3>Riwayat progres</h3></div><div class="card-b"><div class="timeline"><div v-for="l in p.progress_log" :key="l.id" class="tl"><i :class="{ ok: l.to_pct >= 100 }"></i><div><b>{{ l.from_pct }}% → {{ l.to_pct }}%</b> <span class="hint">{{ l.user?.name }} · {{ fmt.datetime(l.created_at) }}</span><div class="fg2">{{ l.note }}</div></div></div><div v-if="!p.progress_log.length" class="empty">Belum ada catatan progres.</div></div></div></div>
    <div class="card" style="grid-column:1/-1"><div class="card-h"><h3>Bukti pelaksanaan</h3></div><div class="card-b flush tbl-wrap"><table class="tbl"><thead><tr><th>Dokumen</th><th>Tipe</th><th>Status</th><th>Diunggah</th><th></th></tr></thead><tbody><tr v-for="d in p.documents" :key="d.id"><td class="t-main">{{ d.title }}</td><td>{{ L.document_types[d.type] }}</td><td><Pill :value="d.status" /></td><td class="t-sub">{{ d.uploader?.name }} · {{ fmt.date(d.created_at) }}</td><td><a :href="`/documents/${d.id}/download`" class="btn sm c-blue">Unduh</a></td></tr><tr v-if="!p.documents.length"><td colspan="5"><div class="empty">Belum ada bukti. Unggah dari menu Dokumen & Bukti dengan subjek action plan ini.</div></td></tr></tbody></table></div></div>
  </div>
</template>
