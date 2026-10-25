<script setup>
import { computed } from "vue";
import FieldList from "../FieldList.vue";
import { clone } from "../../fields.js";
import Icon from "../Icon.vue";

// A list of rows, each row edits the nested fields.
const props = defineProps({
  modelValue: { default: null },
  field: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  path: { type: String, required: true },
});
const emit = defineEmits(["update:modelValue"]);

const rows = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));
const canAdd = computed(() => !props.field.max || rows.value.length < props.field.max);

function change(callback) {
  const copy = clone(rows.value);
  callback(copy);
  emit("update:modelValue", copy);
}

const add = () => change((copy) => copy.push({}));
const remove = (index) => change((copy) => copy.splice(index, 1));
const move = (index, offset) => change((copy) => copy.splice(index + offset, 0, ...copy.splice(index, 1)));
</script>

<template>
  <div class="structure">
    <fieldset v-for="(row, index) in rows" :key="index" class="structure-row">
      <legend class="structure-legend">
        {{ index + 1 }}
        <span class="structure-actions">
          <button type="button" class="icon-button" title="Move up" :disabled="index === 0" @click="move(index, -1)"><Icon name="up" /></button>
          <button type="button" class="icon-button" title="Move down" :disabled="index === rows.length - 1" @click="move(index, 1)"><Icon name="down" /></button>
          <button type="button" class="icon-button" title="Remove" @click="remove(index)"><Icon name="trash" /></button>
        </span>
      </legend>
      <FieldList :fields="field.fields || {}" :values="row" :errors="errors" :prefix="`${path}.${index}.`" />
    </fieldset>
    <p v-if="!rows.length" class="empty">No entries yet</p>
    <button v-if="canAdd" type="button" class="button button-small" @click="add"><Icon name="plus" /> Add</button>
  </div>
</template>
