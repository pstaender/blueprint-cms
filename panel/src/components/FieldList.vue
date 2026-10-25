<script setup>
import FieldInput from "./FieldInput.vue";
import { span, isVisible } from "../fields.js";

// Renders a map of fields in a 12 column grid, `values` is changed in place.
defineProps({
  fields: { type: Object, required: true },
  values: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  prefix: { type: String, default: "" },
  model: { type: Object, default: null },
});
</script>

<template>
  <div class="field-grid">
    <template v-for="field in fields" :key="field.name">
      <div v-if="field.type !== 'hidden' && isVisible(field, values)" class="field-cell" :style="{ gridColumn: `span ${span(field.width)}` }">
        <FieldInput
          v-model="values[field.name]"
          :field="field"
          :errors="errors"
          :path="prefix + field.name"
          :model="model"
        />
      </div>
    </template>
  </div>
</template>
