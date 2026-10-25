<script setup>
import { ref, computed } from "vue";
import FieldList from "../FieldList.vue";
import Icon from "../Icon.vue";
import { clone } from "../../fields.js";
import { useSortable, move } from "../../sortable.js";

// A list of blocks ({id, type, isHidden, content}), each block edits the fields of its fieldset.
const props = defineProps({
  modelValue: { default: null },
  field: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  path: { type: String, required: true },
  model: { type: Object, default: null },
});
const emit = defineEmits(["update:modelValue"]);

const fieldsets = computed(() => props.field.fieldsets ?? {});
const blocks = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));
const canAdd = computed(() => !props.field.max || blocks.value.length < props.field.max);
const newType = ref(Object.keys(fieldsets.value)[0] ?? "");
const collapsed = ref({});

// fieldsets grouped for the <select>
const groups = computed(() => {
  const groups = {};
  for (const fieldset of Object.values(fieldsets.value)) {
    (groups[fieldset.group ?? ""] ??= []).push(fieldset);
  }
  return groups;
});

function change(callback) {
  const copy = clone(blocks.value);
  callback(copy);
  emit("update:modelValue", copy.length ? copy : null);
}

function defaults(type) {
  const content = {};
  for (const field of Object.values(fieldsets.value[type]?.fields ?? {})) {
    if (field.default !== undefined) content[field.name] = field.default;
  }
  return content;
}

const add = () => change((copy) => copy.push({ id: crypto.randomUUID(), type: newType.value, isHidden: false, content: defaults(newType.value) }));
const remove = (index) => change((copy) => copy.splice(index, 1));
const duplicate = (index) => change((copy) => copy.splice(index + 1, 0, { ...clone(copy[index]), id: crypto.randomUUID() }));
const toggleHidden = (index) => change((copy) => (copy[index].isHidden = !copy[index].isHidden));

const { bind, handle } = useSortable((from, to) => emit("update:modelValue", move(blocks.value, from, to)), { useHandle: true });

// FieldList edits the content in place (it is part of the view's reactive values)
function contentOf(block) {
  if (!block.content || typeof block.content !== "object" || Array.isArray(block.content)) block.content = {};
  return block.content;
}
</script>

<template>
  <div class="blocks">
    <article v-for="(block, index) in blocks" :key="block.id || index" class="block" :class="{ 'block-hidden': block.isHidden }" v-bind="bind(index)">
      <header class="block-header">
        <span class="drag-handle" v-bind="handle(index)" title="Drag to sort"><Icon name="grip" /></span>
        <button type="button" class="block-title" @click="collapsed[block.id] = !collapsed[block.id]">
          <span>{{ fieldsets[block.type]?.label ?? block.type }}</span>
          <Icon name="chevron" :class="{ 'icon-open': !collapsed[block.id] }" />
        </button>
        <span class="block-actions">
          <button type="button" class="icon-button" :title="block.isHidden ? 'Show' : 'Hide'" @click="toggleHidden(index)">
            <Icon :name="block.isHidden ? 'hidden' : 'eye'" />
          </button>
          <button v-if="canAdd" type="button" class="icon-button" title="Duplicate" @click="duplicate(index)"><Icon name="copy" /></button>
          <button type="button" class="icon-button" title="Remove" @click="remove(index)"><Icon name="trash" /></button>
        </span>
      </header>
      <div v-if="!collapsed[block.id] && Object.keys(fieldsets[block.type]?.fields ?? {}).length" class="block-body">
        <FieldList
          v-if="fieldsets[block.type]"
          :fields="fieldsets[block.type].fields"
          :values="contentOf(block)"
          :errors="errors"
          :prefix="`${path}.${index}.`"
          :model="model"
        />
        <p v-else class="notice notice-negative">Unknown block type "{{ block.type }}"</p>
      </div>
    </article>

    <p v-if="!blocks.length" class="empty">No blocks yet</p>

    <div v-if="canAdd && Object.keys(fieldsets).length" class="blocks-add">
      <select v-model="newType" class="input input-small" aria-label="Block type">
        <template v-for="(items, group) in groups" :key="group">
          <optgroup v-if="group" :label="group">
            <option v-for="fieldset in items" :key="fieldset.type" :value="fieldset.type">{{ fieldset.label }}</option>
          </optgroup>
          <option v-for="fieldset in group ? [] : items" v-else :key="fieldset.type" :value="fieldset.type">{{ fieldset.label }}</option>
        </template>
      </select>
      <button type="button" class="button button-small" @click="add"><Icon name="plus" /> Add block</button>
    </div>
  </div>
</template>
