<script setup>
/* Field form generik: input / select / textarea / checkbox dengan label & pesan galat. */
const props = defineProps({
  label: String, modelValue: [String, Number, Boolean, Array, Object], type: { type: String, default: 'text' }, options: { type: [Array, Object], default: null },
  error: String, hint: String, placeholder: String, required: Boolean, disabled: Boolean, rows: { type: Number, default: 3 }, min: [Number, String], max: [Number, String], step: [Number, String], span: Boolean, optionLabel: { type: String, default: 'name' }, optionValue: { type: String, default: 'id' }, empty: { type: String, default: '— pilih —' }, maxlength: [Number, String],
});
const emit = defineEmits(['update:modelValue']);
const list = () => { if (!props.options) return []; if (Array.isArray(props.options)) return props.options.map((o) => (typeof o === 'object' ? { v: o[props.optionValue], l: o[props.optionLabel], code: o.code } : { v: o, l: o })); return Object.entries(props.options).map(([v, l]) => ({ v, l })); };
const toggleMulti = (v) => { const cur = [...(props.modelValue || [])]; const i = cur.indexOf(v); if (i >= 0) cur.splice(i, 1); else cur.push(v); emit('update:modelValue', cur); };
const update = (e) => { let v = e.target.value; if (props.type === 'number') v = v === '' ? null : Number(v); if (props.type === 'checkbox') v = e.target.checked; emit('update:modelValue', v); };
</script>
<template>
  <div class="field" :class="{ span, 'has-err': !!error }">
    <label v-if="label && type !== 'checkbox'">{{ label }}<span v-if="required" class="req">*</span></label>
    <select v-if="type === 'select'" :value="modelValue ?? ''" :disabled="disabled" :required="required" @change="update">
      <option value="">{{ empty }}</option>
      <option v-for="o in list()" :key="o.v" :value="o.v">{{ o.l }}</option>
    </select>
    <textarea v-else-if="type === 'textarea'" :value="modelValue ?? ''" :rows="rows" :placeholder="placeholder" :disabled="disabled" :required="required" :maxlength="maxlength" @input="update"></textarea>
    <div v-else-if="type === 'multi'" class="chips-sel"><label v-for="o in list()" :key="o.v" :class="{ on: (modelValue || []).includes(o.v) }"><input type="checkbox" style="display:none" :checked="(modelValue || []).includes(o.v)" @change="toggleMulti(o.v)"><span v-if="o.code" class="mono">{{ o.code }}</span> {{ o.l }}</label><span v-if="!list().length" class="hint">Tidak ada pilihan.</span></div>
    <label v-else-if="type === 'checkbox'" class="row chk"><input type="checkbox" :checked="!!modelValue" :disabled="disabled" @change="update"><span>{{ label }}</span></label>
    <input v-else class="inp" :type="type" :value="modelValue ?? ''" :placeholder="placeholder" :disabled="disabled" :required="required" :min="min" :max="max" :step="step" :maxlength="maxlength" @input="update">
    <span v-if="error" class="err-msg" role="alert">{{ error }}</span>
    <span v-else-if="hint" class="hint">{{ hint }}</span>
  </div>
</template>
