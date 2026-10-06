<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Pill from '../../Components/Pill.vue';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import { fmt } from '../../lib/format';
const props = defineProps({ inbox: Array, requested: Array, history: Array, pending_all: Array, focus: { type: Object, default: null } });
const L = usePage().props.labels;
const tab = ref(props.focus ? 'focus' : props.inbox.length ? 'inbox' : 'requested');
const target = ref(null); const action = ref('approve');
const form = useForm({ action: 'approve', note: '' });
const open = (a, act) => { target.value = a; action.value = act; form.action = act; form.note = ''; form.clearErrors(); };
const decide = () => form.post(`/approvals/${target.value.id}/decide`, { onSuccess: () => (target.value = null) });
const lists = { focus: props.focus?.items || [], inbox: props.inbox, requested: props.requested, pending_all: props.pending_all, history: props.history };
const tabs = [...(props.focus ? [['focus', props.focus.label, props.focus.items.length]] : []), ['inbox', 'Menunggu saya', props.inbox.length], ['requested', 'Pengajuan saya', props.requested.length], ['pending_all', 'Semua yang berjalan', props.pending_all.length], ['history', 'Riwayat', props.history.length]];
</script>
<template>
  <Head title="Persetujuan" />
  <PageHead kicker="Alur Kerja" title="Persetujuan berjenjang" sub="Pengajuan risiko baru, perubahan skor, treatment, penerimaan, dan penutupan risiko mengikuti rantai Risk Owner → Risk Manager → Management. Pengaju tidak dapat memutus pengajuannya sendiri." />
  <div class="tabs"><button v-for="t in tabs" :key="t[0]" type="button" :class="{ on: tab === t[0] }" @click="tab = t[0]">{{ t[1] }}<span class="c">{{ t[2] }}</span></button></div>
  <div v-if="tab === 'focus'" class="row" style="justify-content:flex-end"><Link href="/approvals" class="btn sm ghost c-indigo">Hapus penelusuran</Link></div>
  <div class="stack">
    <div v-for="a in lists[tab]" :key="a.id" class="card"><div class="card-b">
      <div class="row between"><div><Link :href="`/approvals?id=${a.id}`" class="code-link">{{ a.code }}</Link> · <b>{{ a.type_label }}</b> <Pill :value="a.status" /><div class="hint">Diajukan {{ a.requester }} · {{ fmt.datetime(a.created_at) }}<span v-if="a.decided_at"> · diputus {{ fmt.datetime(a.decided_at) }}</span></div></div>
        <div v-if="a.subject" class="row"><Link v-if="a.subject.url" :href="a.subject.url" class="btn sm c-blue">{{ a.subject.code }} · {{ a.subject.name }}</Link><span v-if="a.subject.score" class="mono">skor {{ a.subject.score }}</span><Pill v-if="a.subject.level" kind="level" :value="a.subject.level" /></div></div>
      <p v-if="a.note" class="fg2" style="margin:8px 0 6px;font-size:13px">{{ a.note }}</p>
      <div class="steps"><div v-for="s in a.steps" :key="s.step_no" class="st" :class="{ done: s.action === 'approve', bad: s.action && s.action !== 'approve', cur: !s.action && s.step_no === a.current_step && a.status === 'pending' }"><b>{{ s.step_no }}. {{ s.role }}</b><br><span v-if="s.action">{{ { approve: 'Disetujui', revise: 'Revisi', reject: 'Ditolak' }[s.action] }} · {{ s.approver }} · {{ fmt.date(s.acted_at) }}</span><span v-else-if="s.step_no === a.current_step && a.status === 'pending'">Menunggu · SLA {{ fmt.date(s.due_at) }}</span><span v-else>—</span><div v-if="s.note" class="hint">“{{ s.note }}”</div></div></div>
      <div v-if="a.can_decide" class="row" style="margin-top:10px;justify-content:flex-end"><button type="button" class="btn c-amber" @click="open(a, 'revise')">Minta revisi</button><button type="button" class="btn c-red" @click="open(a, 'reject')">Tolak</button><button type="button" class="btn c-green" @click="open(a, 'approve')"><Icon name="send" />Setujui</button></div>
    </div></div>
    <div v-if="!lists[tab].length" class="card"><div class="empty">Tidak ada pengajuan.</div></div>
  </div>
  <Modal :show="!!target" :title="{ approve: 'Setujui pengajuan', revise: 'Minta revisi', reject: 'Tolak pengajuan' }[action]" @close="target = null">
    <p v-if="target">{{ target.code }} · {{ target.type_label }} — {{ target.subject?.code }} {{ target.subject?.name }}</p>
    <Field v-model="form.note" type="textarea" :label="action === 'approve' ? 'Catatan (opsional)' : 'Alasan (wajib)'" :required="action !== 'approve'" :error="form.errors.note || form.errors.approval || form.errors.action" :rows="4" />
    <template #footer><button class="btn ghost c-indigo" @click="target = null">Batal</button><button class="btn" :class="{ 'c-green': action === 'approve', 'c-amber': action === 'revise', 'c-red': action === 'reject' }" :disabled="form.processing" @click="decide">{{ { approve: 'Setujui', revise: 'Kembalikan', reject: 'Tolak' }[action] }}</button></template>
  </Modal>
</template>
