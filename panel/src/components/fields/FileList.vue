<script setup>
import { ref, computed } from "vue";
import { filesOf, uploadFiles, sortFiles, uploadTemplate, acceptOf, FILE_DRAG_TYPE } from "../../files.js";
import { useSortable, move } from "../../sortable.js";
import FileThumb from "../FileThumb.vue";
import Icon from "../Icon.vue";

// The files of the model (`filelist` field), optionally filtered by `template`, with uploads and drag & drop sorting.
const props = defineProps({
  field: { type: Object, required: true },
  model: { type: Object, default: null },
});

const errors = ref([]);
const busy = ref(false);
const store = props.model?.id ? filesOf(props.model.id) : null;
const files = computed(() => {
  const list = store?.list ?? [];
  const templates = [props.field.template ?? []].flat();
  return templates.length ? list.filter((file) => templates.includes(file.template)) : list;
});

// a filtered list moves the file to its target's position in the full list
const { bind } = useSortable(async (from, to) => {
  const all = store.list.map((file) => file.filename);
  const order = move(all, all.indexOf(files.value[from].filename), all.indexOf(files.value[to].filename));
  try {
    await sortFiles(props.model.id, order);
  } catch (e) {
    errors.value = [e.message];
  }
}, { dragData: (index) => ({ [FILE_DRAG_TYPE]: JSON.stringify(files.value[index]) }) });

async function upload(event) {
  busy.value = true;
  ({ errors: errors.value } = await uploadFiles(props.model.id, event.target.files, uploadTemplate(props.field)));
  event.target.value = "";
  busy.value = false;
}
</script>

<template>
  <section class="section">
    <header class="section-header">
      <h3 class="field-label">{{ field.label }}</h3>
      <label v-if="model?.id" class="button button-small" :class="{ 'is-busy': busy }">
        <Icon name="upload" /> Upload
        <input type="file" multiple hidden :accept="acceptOf(field)" @change="upload">
      </label>
    </header>
    <p v-if="!model?.id" class="field-help">Files are only available for pages.</p>
    <p v-for="error in errors" :key="error" class="notice notice-negative">{{ error }}</p>

    <ul v-if="files.length" class="items" :class="`items-${field.layout || 'list'}`">
      <li v-for="(file, index) in files" :key="file.filename" class="item" v-bind="bind(index)" title="Drag to sort or into a textarea">
        <RouterLink :to="`/pages/${file.page}/files/${encodeURIComponent(file.filename)}`" class="item-link" draggable="false">
          <FileThumb :file="file" />
          <span class="item-title">{{ file.filename }}</span>
          <span class="item-info">{{ file.template !== "default" ? file.template : "" }}</span>
          <span v-if="!file.isUsed" class="item-info" title="Only used files are public">not used</span>
        </RouterLink>
      </li>
    </ul>
    <p v-else-if="model?.id" class="empty">No files yet</p>
  </section>
</template>
