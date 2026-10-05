<script setup>
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import CrudModal from '../../Components/CrudModal.vue';
import ConfirmButton from '../../Components/ConfirmButton.vue';
import Pill from '../../Components/Pill.vue';
const props = defineProps({ units: Array, users: Array, can: Object });
const L = usePage().props.labels;
const modal = ref(false); const item = ref(null);
const tree = computed(() => { const by = {}; props.units.forEach((u) => (by[u.id] = { ...u, children: [] })); const roots = []; props.units.forEach((u) => (u.parent_id && by[u.parent_id] ? by[u.parent_id].children.push(by[u.id]) : roots.push(by[u.id]))); return roots; });
const fields = computed(() => [
  { key: 'name', label: 'Nama unit', required: true, span: true, maxlength: 160 }, { key: 'code', label: 'Kode', required: true, maxlength: 20, hint: 'Huruf/angka/titik/strip' },
  { key: 'type', label: 'Jenis', type: 'select', options: L.unit_types, empty: '', required: true, default: 'unit' },
  { key: 'parent_id', label: 'Induk', type: 'select', options: props.units.filter((u) => u.id !== item.value?.id).map((u) => ({ id: u.id, name: `${'— '.repeat(u.level)}${u.name}` })), empty: '(tingkat teratas)' },
  { key: 'head_user_id', label: 'Kepala unit', type: 'select', options: props.users }, { key: 'sort', label: 'Urutan', type: 'number', min: 0, default: 0 }, { key: 'active', label: 'Aktif', type: 'checkbox', default: true },
]);
const open = (u) => { item.value = u; modal.value = true; };
</script>
<template>
  <Head title="Struktur organisasi" />
  <PageHead kicker="Tata Kelola" title="Struktur organisasi" sub="Hierarki unit kerja menentukan cakupan akses Risk Officer/Risk Owner dan agregasi profil risiko.">
    <button v-if="can.write" type="button" class="btn c-green" @click="open(null)"><Icon name="plus" />Tambah unit</button>
  </PageHead>
  <div class="card"><div class="card-b">
    <ul class="tree">
      <template v-for="n in tree" :key="n.id"><li>
        <div class="tnode"><Icon name="users" /><b>{{ n.name }}</b><span class="mono muted">{{ n.code }}</span><span class="pill">{{ L.unit_types[n.type] }}</span><span v-if="n.head" class="hint">Kepala: {{ n.head }}</span><span class="pill run">{{ n.risks_count }} risiko</span><Pill v-if="!n.active" value="inactive" /><span style="margin-left:auto" class="row"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open(n)">Ubah</button><ConfirmButton v-if="can.write" :href="`/organization/units/${n.id}`" message="Hapus unit ini?" /></span></div>
        <ul v-if="n.children.length"><template v-for="c in n.children" :key="c.id"><li>
          <div class="tnode"><Icon name="users" /><b>{{ c.name }}</b><span class="mono muted">{{ c.code }}</span><span class="pill">{{ L.unit_types[c.type] }}</span><span v-if="c.head" class="hint">Kepala: {{ c.head }}</span><span class="pill run">{{ c.risks_count }} risiko</span><Pill v-if="!c.active" value="inactive" /><span style="margin-left:auto" class="row"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open(c)">Ubah</button><ConfirmButton v-if="can.write" :href="`/organization/units/${c.id}`" message="Hapus unit ini?" /></span></div>
          <ul v-if="c.children.length"><li v-for="g in c.children" :key="g.id"><div class="tnode"><Icon name="users" /><b>{{ g.name }}</b><span class="mono muted">{{ g.code }}</span><span class="pill">{{ L.unit_types[g.type] }}</span><span class="pill run">{{ g.risks_count }} risiko</span><span style="margin-left:auto" class="row"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open(g)">Ubah</button><ConfirmButton v-if="can.write" :href="`/organization/units/${g.id}`" message="Hapus unit ini?" /></span></div>
            <ul v-if="g.children.length"><li v-for="h in g.children" :key="h.id"><div class="tnode"><Icon name="users" /><b>{{ h.name }}</b><span class="mono muted">{{ h.code }}</span><span class="pill run">{{ h.risks_count }} risiko</span><span style="margin-left:auto" class="row"><button v-if="can.write" type="button" class="btn sm c-blue" @click="open(h)">Ubah</button><ConfirmButton v-if="can.write" :href="`/organization/units/${h.id}`" message="Hapus unit ini?" /></span></div></li></ul></li></ul>
        </li></template></ul>
      </li></template>
    </ul>
    <div v-if="!tree.length" class="empty">Belum ada unit kerja.</div>
  </div></div>
  <CrudModal :show="modal" :title="item ? 'Ubah unit' : 'Tambah unit'" :fields="fields" :item="item" :url="item ? `/organization/units/${item.id}` : '/organization/units'" :method="item ? 'put' : 'post'" @close="modal = false" />
</template>
