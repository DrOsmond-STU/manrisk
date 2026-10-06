<script setup>
/* Satu simpul pohon unit kerja (rekursif, kedalaman tak terbatas). */
import { Link, usePage } from '@inertiajs/vue3';
import ConfirmButton from './ConfirmButton.vue';
import Pill from './Pill.vue';
defineOptions({ name: 'UnitNode' });
defineProps({ node: Object, canWrite: Boolean });
const emit = defineEmits(['edit']);
const L = usePage().props.labels;
</script>
<template>
  <li>
    <div class="tnode"><Icon name="users" /><b>{{ node.name }}</b><span class="mono muted">{{ node.code }}</span><span class="pill">{{ L.unit_types[node.type] }}</span><span v-if="node.head" class="hint">Kepala: {{ node.head }}</span>
      <Link :href="`/risks?unit_id=${node.id}&status=active`" class="pill run" :title="`${node.risks_count} risiko langsung di unit ini`">{{ node.risks_total }} risiko{{ node.children.length ? ' (termasuk sub-unit)' : '' }}</Link>
      <Link :href="`/action-plans?unit_id=${node.id}`" class="pill">Action plan</Link><span v-if="node.users_count" class="hint">{{ node.users_count }} pengguna</span><Pill v-if="!node.active" value="inactive" />
      <span style="margin-left:auto" class="row"><Link v-if="canWrite" :href="`/risks/create?unit_id=${node.id}`" class="btn sm c-green">+ Risiko</Link><button v-if="canWrite" type="button" class="btn sm c-blue" @click="emit('edit', node)">Ubah</button><ConfirmButton v-if="canWrite" :href="`/organization/units/${node.id}`" message="Hapus unit ini?" /></span></div>
    <ul v-if="node.children.length"><UnitNode v-for="c in node.children" :key="c.id" :node="c" :can-write="canWrite" @edit="(n) => emit('edit', n)" /></ul>
  </li>
</template>
