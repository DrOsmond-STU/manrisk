<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pagination from '../../Components/Pagination.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ alerts: Object, filters: Object, stats: Object });
const sev = { critical: 'bad', warning: 'warn', info: 'info' };
const label = { kri_breach: 'KRI', score_up: 'Skor naik', action_overdue: 'AP terlambat', action_due: 'AP jatuh tempo', approval_due: 'SLA persetujuan', doc_expiring: 'Dokumen', control_due: 'Kontrol', incident: 'Insiden', review_escalate: 'Eskalasi review' };
const go = (q) => router.get('/alerts', q, { preserveState: true });
</script>
<template>
  <Head title="Peringatan dini" />
  <PageHead kicker="Early Warning" title="Peringatan dini & notifikasi" sub="KRI melewati ambang, kenaikan skor, action plan terlambat, SLA persetujuan, dokumen kedaluwarsa, kontrol jatuh tempo, dan insiden. Salinan dikirim lewat email sesuai preferensi.">
    <div class="seg"><button type="button" :class="{ on: (filters.open ?? '1') !== '0' }" @click="go({ open: 1 })">Aktif</button><button type="button" :class="{ on: filters.open === '0' }" @click="go({ open: 0 })">Semua</button></div>
    <button type="button" class="btn c-indigo" @click="router.post('/alerts/read-all')">Tandai semua dibaca</button>
  </PageHead>
  <div class="kpis"><div class="kpi lvl" style="--c:var(--lv-vh)"><div class="k-l"><i class="sw"></i>Kritis</div><div class="k-v">{{ stats.critical || 0 }}</div></div><div class="kpi lvl" style="--c:var(--lv-m)"><div class="k-l"><i class="sw"></i>Peringatan</div><div class="k-v">{{ stats.warning || 0 }}</div></div><div class="kpi lvl" style="--c:var(--accent)"><div class="k-l"><i class="sw"></i>Info</div><div class="k-v">{{ stats.info || 0 }}</div></div></div>
  <div class="card"><div class="card-b stack">
    <div v-for="a in alerts.data" :key="a.id" class="alert-box" :class="sev[a.severity]" :style="a.read_at ? 'opacity:.75' : ''"><Icon :name="a.severity === 'critical' ? 'alert' : 'bell'" /><div style="flex:1"><div class="row between"><b>{{ a.title }}</b><span class="pill">{{ label[a.type] || a.type }}</span></div><div v-if="a.message" class="fg2" style="font-size:12.5px">{{ a.message }}</div><div class="hint">{{ fmt.datetime(a.created_at) }}<span v-if="a.handled_at"> · ditangani {{ a.handler?.name }} {{ fmt.date(a.handled_at) }}</span></div>
      <div class="row" style="margin-top:6px"><Link v-if="a.link" :href="a.link" class="btn sm c-blue" @click="router.post(`/alerts/${a.id}/read`, {}, { preserveScroll: true })">Buka</Link><button v-if="!a.read_at" type="button" class="btn sm ghost c-indigo" @click="router.post(`/alerts/${a.id}/read`, {}, { preserveScroll: true })">Tandai dibaca</button><button v-if="!a.handled_at && !$page.props.auth.user.read_only" type="button" class="btn sm c-green" @click="router.post(`/alerts/${a.id}/handle`, {}, { preserveScroll: true })">Selesai ditangani</button></div></div></div>
    <div v-if="!alerts.data.length" class="empty">Tidak ada peringatan.</div>
  </div><Pagination :data="alerts" /></div>
</template>
