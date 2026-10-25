<script setup>
import { reactive, watch } from "vue";
import FieldList from "../FieldList.vue";
import { clone } from "../../fields.js";

// A single map of nested fields.
const props = defineProps({
  modelValue: { default: null },
  field: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  path: { type: String, required: true },
});
const emit = defineEmits(["update:modelValue"]);

const values = reactive(clone(props.modelValue && typeof props.modelValue === "object" ? props.modelValue : {}));

watch(values, () => emit("update:modelValue", { ...values }), { deep: true });
</script>

<template>
  <fieldset class="structure-row">
    <FieldList :fields="field.fields || {}" :values="values" :errors="errors" :prefix="`${path}.`" />
  </fieldset>
</template>
