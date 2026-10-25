<script setup>
import { computed } from "vue";
import BlocksField from "./BlocksField.vue";
import Icon from "../Icon.vue";
import { clone, span } from "../../fields.js";
import { useSortable, move } from "../../sortable.js";

// Rows of columns ({id, columns: [{id, width, blocks}]}), every column is a blocks editor.
const props = defineProps({
  modelValue: { default: null },
  field: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  path: { type: String, required: true },
  model: { type: Object, default: null },
});
const emit = defineEmits(["update:modelValue"]);

const rows = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));
const layouts = computed(() => props.field.layouts ?? [["1/1"]]);
const blocksField = computed(() => ({ ...props.field, type: "blocks", max: undefined }));

function change(callback) {
  const copy = clone(rows.value);
  callback(copy);
  emit("update:modelValue", copy.length ? copy : null);
}

const add = (widths) => change((copy) => copy.push({
  id: crypto.randomUUID(),
  attrs: {},
  columns: widths.map((width) => ({ id: crypto.randomUUID(), width, blocks: [] })),
}));
const remove = (index) => change((copy) => copy.splice(index, 1));
const setBlocks = (row, column, blocks) => change((copy) => (copy[row].columns[column].blocks = blocks ?? []));

const { bind, handle } = useSortable((from, to) => emit("update:modelValue", move(rows.value, from, to)), { useHandle: true });
</script>

<template>
  <div class="layouts">
    <section v-for="(row, index) in rows" :key="row.id || index" class="layout-row" v-bind="bind(index)">
      <header class="block-header">
        <span class="drag-handle" v-bind="handle(index)" title="Drag to sort"><Icon name="grip" /></span>
        <span class="block-title">{{ row.columns.map((column) => column.width).join(" · ") }}</span>
        <span class="block-actions">
          <button type="button" class="icon-button" title="Remove row" @click="remove(index)"><Icon name="trash" /></button>
        </span>
      </header>
      <div class="layout-columns">
        <div v-for="(column, columnIndex) in row.columns" :key="column.id || columnIndex" class="layout-column" :style="{ gridColumn: `span ${span(column.width)}` }">
          <BlocksField
            :model-value="column.blocks"
            :field="blocksField"
            :errors="errors"
            :path="`${path}.${index}.${columnIndex}`"
            :model="model"
            @update:model-value="setBlocks(index, columnIndex, $event)"
          />
        </div>
      </div>
    </section>

    <p v-if="!rows.length" class="empty">No layouts yet</p>

    <div class="layouts-add">
      <span class="field-help">Add a row:</span>
      <button v-for="widths in layouts" :key="widths.join()" type="button" class="layout-preset" :title="widths.join(', ')" @click="add(widths)">
        <span v-for="(width, i) in widths" :key="i" :style="{ flex: span(width) }"></span>
      </button>
    </div>
  </div>
</template>
