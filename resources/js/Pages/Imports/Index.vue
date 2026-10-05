<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
const props = defineProps({ type: String, columns: Object, max: Number, preview: Object });
const form = useForm({ file: null });
const label = props.type === 'kri' ? 'nilai KRI' : 'risiko';
const upload = () => form.post(`/import/${props.type}/preview`, { forceFormData: true });
const commit = () => router.post(`/import/${props.type}/commit`, { token: props.preview.token });
</script>
<template>
  <Head :title="`Impor ${label}`" />
  <PageHead kicker="Impor Excel" :title="`Impor ${label} dari Excel`" :sub="`Unggah berkas sesuai templat (maks. ${max} baris). Sistem memeriksa setiap baris; hanya baris valid yang disimpan setelah Anda konfirmasi.${type === 'risk' ? ' Risiko hasil impor berstatus draft.' : ' Nilai periode yang sudah ada akan diperbarui.'}`">
    <a :href="`/import/${type}/template`" class="btn c-green"><Icon name="file" />Unduh templat</a>
    <Link :href="type === 'kri' ? '/kris' : '/risks'" class="btn ghost c-indigo">Kembali</Link>
  </PageHead>
  <div class="s-grid">
    <div class="card" style="grid-column:span 5"><div class="card-h"><h3>1. Unggah berkas</h3></div><div class="card-b stack">
      <input type="file" class="inp" accept=".xlsx,.xls,.csv" @change="form.file = $event.target.files[0]">
      <span v-if="form.errors.file" class="err-msg">{{ form.errors.file }}</span>
      <button type="button" class="btn c-blue" :disabled="!form.file || form.processing" @click="upload"><Icon name="upload" />{{ form.processing ? 'Memeriksa…' : 'Periksa berkas' }}</button>
      <details><summary class="hint" style="cursor:pointer">Kolom yang diharapkan</summary><ul style="font-size:12.5px;margin:6px 0 0 18px"><li v-for="(l, k) in columns" :key="k">{{ l }}</li></ul></details>
    </div></div>
    <div class="card" style="grid-column:span 7"><div class="card-h"><h3>2. Pratinjau & konfirmasi</h3></div><div class="card-b stack">
      <div v-if="!preview" class="empty">Unggah berkas untuk melihat pratinjau.</div>
      <template v-else>
        <div class="stat-row"><span>Total baris <b>{{ preview.total }}</b></span><span style="color:var(--ok-ink)">Valid <b>{{ preview.valid.length }}</b></span><span style="color:var(--bad-ink)">Galat <b>{{ preview.errors.length }}</b></span></div>
        <div v-if="preview.errors.length" class="tbl-wrap"><table class="tbl"><thead><tr><th>Baris</th><th>Data</th><th>Kesalahan</th></tr></thead><tbody><tr v-for="e in preview.errors" :key="e.line"><td class="mono">{{ e.line }}</td><td class="t-main">{{ e.name }}</td><td><div v-for="(m, i) in e.errors" :key="i" class="err-msg">• {{ m }}</div></td></tr></tbody></table></div>
        <div v-if="preview.valid.length" class="tbl-wrap"><table class="tbl"><thead><tr><th>Baris</th><th>Akan disimpan</th><th>Rincian</th></tr></thead><tbody><tr v-for="v in preview.valid" :key="v.line"><td class="mono">{{ v.line }}</td><td class="t-main">{{ v.name }}</td><td class="t-sub">{{ Object.values(v.preview).join(' · ') }}</td></tr></tbody></table></div>
        <button type="button" class="btn c-green" :disabled="!preview.valid.length" @click="commit"><Icon name="send" />Simpan {{ preview.valid.length }} baris valid</button>
      </template>
    </div></div>
  </div>
</template>
