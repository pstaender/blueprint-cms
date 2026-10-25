<script setup>
import { ref, computed } from "vue";
import { filesOf, uploadFiles, uploadTemplate, acceptOf } from "../../files.js";
import { toList } from "../../fields.js";
import { useSortable, move } from "../../sortable.js";
import Dialog from "../Dialog.vue";
import FileThumb from "../FileThumb.vue";
import Icon from "../Icon.vue";

// Selected files of the model's page as references (`file://uid`), sortable, with a select dialog and uploads.
const props = defineProps({
  modelValue: { default: null },
  field: { type: Object, required: true },
  model: { type: Object, default: null },
});
const emit = defineEmits(["update:modelValue"]);

const open = ref(false);
const choice = ref([]);
const errors = ref([]);
const busy = ref(false);

const pageId = computed(() => props.model?.id ?? null);
const store = pageId.value ? filesOf(pageId.value) : null;
const max = computed(() => (props.field.multiple === false ? 1 : props.field.max ?? null));
const available = computed(() => {
  const list = store?.list ?? [];
  const accept = acceptOf(props.field);
  if (!accept) return list;
  return list.filter((file) => accept.split(",").some((pattern) => {
    pattern = pattern.trim();
    return pattern.endsWith("/*") ? file.mime.startsWith(pattern.slice(0, -1)) : file.mime === pattern;
  }));
});

// stored values may be references, filenames or paths
function find(value) {
  return (store?.list ?? []).find((file) => file.ref === value || file.filename === value || file.id === value);
}

const selected = computed(() => toList(props.modelValue).map((value) => ({ value, file: find(value) })));
const { bind, handle } = useSortable((from, to) => emit("update:modelValue", move(toList(props.modelValue), from, to)), { useHandle: true });

function remove(index) {
  const list = toList(props.modelValue).filter((_, i) => i !== index);
  emit("update:modelValue", list.length ? list : null);
}

function start() {
  choice.value = selected.value.map((item) => item.file?.ref ?? item.value);
  errors.value = [];
  open.value = true;
}

function toggle(ref) {
  if (max.value === 1) {
    choice.value = [ref];
  } else if (choice.value.includes(ref)) {
    choice.value = choice.value.filter((value) => value !== ref);
  } else if (!max.value || choice.value.length < max.value) {
    choice.value = [...choice.value, ref];
  }
}

function apply() {
  emit("update:modelValue", choice.value.length ? choice.value : null);
  open.value = false;
}

async function upload(event) {
  busy.value = true;
  const result = await uploadFiles(pageId.value, event.target.files, uploadTemplate(props.field));
  errors.value = result.errors;
  for (const file of result.uploaded) toggle(file.ref);
  event.target.value = "";
  busy.value = false;
}
</script>

<template>
  <div class="picker">
    <p v-if="!pageId" class="field-help">Files are only available for pages.</p>
    <template v-else>
      <ul v-if="selected.length" class="items">
        <li v-for="(item, index) in selected" :key="item.value" class="item" v-bind="bind(index)">
          <div class="item-link">
            <span class="drag-handle" v-bind="handle(index)" title="Drag to sort"><Icon name="grip" /></span>
            <FileThumb v-if="item.file" :file="item.file" />
            <span class="item-title">{{ item.file?.filename ?? `${item.value} (missing)` }}</span>
            <button type="button" class="icon-button" title="Remove" @click="remove(index)"><Icon name="close" /></button>
          </div>
        </li>
      </ul>
      <button type="button" class="button button-small" @click="start"><Icon name="image" /> Select</button>
    </template>

    <Dialog v-model:open="open" :title="field.label" submit-label="Select" @submit="apply">
      <p v-for="error in errors" :key="error" class="notice notice-negative">{{ error }}</p>
      <ul v-if="available.length" class="choices file-choices">
        <li v-for="file in available" :key="file.ref">
          <label class="choice">
            <input :type="max === 1 ? 'radio' : 'checkbox'" :checked="choice.includes(file.ref)" name="file" @change="toggle(file.ref)">
            <FileThumb :file="file" /> {{ file.filename }}
          </label>
        </li>
      </ul>
      <p v-else class="empty">No files yet</p>
      <label class="button button-small" :class="{ 'is-busy': busy }">
        <Icon name="upload" /> Upload
        <input type="file" :multiple="max !== 1" hidden :accept="acceptOf(field)" @change="upload">
      </label>
    </Dialog>
  </div>
</template>
