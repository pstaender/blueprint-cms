<script setup>
import { ref, computed } from "vue";
import { options, toList } from "../fields.js";
import StructureField from "./fields/StructureField.vue";
import ObjectField from "./fields/ObjectField.vue";
import PickerField from "./fields/PickerField.vue";
import PageList from "./fields/PageList.vue";
import FileList from "./fields/FileList.vue";
import FilePicker from "./fields/FilePicker.vue";
import BlocksField from "./fields/BlocksField.vue";
import LayoutField from "./fields/LayoutField.vue";
import { FILE_DRAG_TYPE, markdownLink, uploadFiles } from "../files.js";

// One blueprint field as native form element(s).
const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { default: null },
  errors: { type: Object, default: () => ({}) },
  path: { type: String, required: true },
  model: { type: Object, default: null },
});
const emit = defineEmits(["update:modelValue"]);

const id = computed(() => `field-${props.path.replace(/[^\w-]/g, "-")}`);
const error = computed(() => props.errors[props.path]?.join(", "));
const choices = computed(() => options(props.field));
const type = computed(() => props.field.type);

const inputTypes = { text: "text", slug: "text", email: "email", url: "url", tel: "tel", password: "password", link: "url", color: "color" };

function update(value) {
  emit("update:modelValue", value === "" ? null : value);
}

const text = computed({
  get: () => props.modelValue ?? "",
  set: update,
});

const number = computed({
  get: () => props.modelValue ?? "",
  set: (value) => update(value === "" ? null : Number(value)),
});

// date fields: "2026-10-25" or "2026-10-25 18:30" ↔ <input type=date|datetime-local>
const date = computed({
  get: () => {
    const value = String(props.modelValue ?? "");
    return props.field.time ? value.replace(" ", "T").slice(0, 16) : value.slice(0, 10);
  },
  set: (value) => update(props.field.time ? value.replace("T", " ") : value),
});

const time = computed({
  get: () => String(props.modelValue ?? "").slice(0, 5),
  set: update,
});

const toggle = computed({
  get: () => props.modelValue === true || props.modelValue === "true",
  set: (value) => emit("update:modelValue", value),
});

const list = computed({
  get: () => toList(props.modelValue).map(String),
  set: (value) => update(value.length ? value : null),
});

// files dropped on a textarea (from a file list or the computer) are inserted as markdown links
const textarea = ref(null);
const isDropTarget = ref(false);

function onDragover(event) {
  const types = [...event.dataTransfer.types];
  if (type.value !== "textarea" || (!types.includes(FILE_DRAG_TYPE) && !(types.includes("Files") && props.model?.id))) return;
  event.preventDefault();
  isDropTarget.value = true;
}

async function onDrop(event) {
  isDropTarget.value = false;
  const data = event.dataTransfer.getData(FILE_DRAG_TYPE);
  let files = data ? [JSON.parse(data)] : [];

  if (!data && event.dataTransfer.files.length && props.model?.id) {
    event.preventDefault();
    files = (await uploadFiles(props.model.id, event.dataTransfer.files)).uploaded;
  } else if (data) {
    event.preventDefault();
  } else {
    return;
  }

  insert(files.map((file) => markdownLink(file, props.model?.id)).join("\n"));
}

function insert(snippet) {
  const value = String(props.modelValue ?? "");
  const position = textarea.value?.selectionStart ?? value.length;
  const before = value.slice(0, position);
  const after = value.slice(position);
  const separator = before === "" || /\s$/.test(before) ? "" : " ";
  update(before + separator + snippet + after);
}

const tags = computed({
  get: () => toList(props.modelValue).join(", "),
  set: (value) => update(toList(value)),
});

const entries = computed({
  get: () => toList(props.modelValue).join("\n"),
  set: (value) => update(value.split("\n").map((line) => line.trim()).filter(Boolean)),
});

</script>

<template>
  <!-- display fields -->
  <h3 v-if="type === 'headline'" class="field-headline">{{ field.label }}</h3>
  <hr v-else-if="type === 'line'" class="field-line">
  <div v-else-if="type === 'gap'" class="field-gap"></div>
  <div v-else-if="type === 'info'" class="notice" :class="`notice-${field.theme || 'info'}`">
    <strong v-if="field.label && field.label !== 'Info'">{{ field.label }}</strong>
    <p>{{ field.text }}</p>
  </div>
  <PageList v-else-if="type === 'pagelist'" :field="field" :model="model" />
  <FileList v-else-if="type === 'filelist'" :field="field" :model="model" />
  <div v-else-if="type === 'stats'" class="field">
    <span class="field-label">{{ field.label }}</span>
    <dl class="stats">
      <div v-for="(report, index) in field.reports || []" :key="index" class="stat">
        <dt>{{ report.label }}</dt><dd>{{ report.value }}</dd>
      </div>
    </dl>
  </div>
  <template v-else-if="type === 'buttons'"></template>

  <!-- input fields -->
  <div v-else class="field" :class="{ 'field-invalid': error }">
    <label class="field-label" :for="id">
      {{ field.label }}<abbr v-if="field.required" title="required">*</abbr>
    </label>

    <input v-if="inputTypes[type]" :id="id" v-model="text" class="input" :type="inputTypes[type]"
      :placeholder="field.placeholder" :minlength="field.minlength" :maxlength="field.maxlength"
      :pattern="field.pattern" :required="field.required" :disabled="field.disabled"
      :autocomplete="type === 'password' ? 'new-password' : undefined">

    <input v-else-if="type === 'number' || type === 'range'" :id="id" v-model="number" class="input"
      :type="type" :min="field.min" :max="field.max" :step="field.step ?? 'any'" :required="field.required" :disabled="field.disabled">

    <input v-else-if="type === 'date'" :id="id" v-model="date" class="input" :type="field.time ? 'datetime-local' : 'date'"
      :required="field.required" :disabled="field.disabled">

    <input v-else-if="type === 'time'" :id="id" v-model="time" class="input" type="time" :required="field.required" :disabled="field.disabled">

    <textarea v-else-if="['textarea', 'writer', 'list'].includes(type)" :id="id" ref="textarea" v-model="text" class="input textarea"
      :class="[`textarea-${field.size || 'medium'}`, { 'is-file-drop': isDropTarget }]" @dragover="onDragover"
      @dragleave="isDropTarget = false" @drop="onDrop" :placeholder="field.placeholder" :minlength="field.minlength"
      :maxlength="field.maxlength" :required="field.required" :disabled="field.disabled"></textarea>

    <label v-else-if="type === 'toggle'" class="choice">
      <input :id="id" v-model="toggle" type="checkbox" role="switch" class="switch" :disabled="field.disabled">
      {{ Array.isArray(field.text) ? field.text[toggle ? 1 : 0] : field.text || (toggle ? "Yes" : "No") }}
    </label>

    <select v-else-if="type === 'select' && choices" :id="id" v-model="text" class="input" :required="field.required" :disabled="field.disabled">
      <option value="">{{ field.placeholder || "—" }}</option>
      <option v-for="option in choices" :key="option.value" :value="option.value">{{ option.text }}</option>
    </select>

    <div v-else-if="(type === 'radio' || type === 'toggles') && choices" class="choices" :class="{ 'choices-inline': type === 'toggles' }">
      <label v-for="option in choices" :key="option.value" class="choice">
        <input v-model="text" type="radio" :name="id" :value="option.value" :disabled="field.disabled"> {{ option.text }}
      </label>
    </div>

    <div v-else-if="type === 'checkboxes' && choices" class="choices">
      <label v-for="option in choices" :key="option.value" class="choice">
        <input v-model="list" type="checkbox" :value="option.value" :disabled="field.disabled"> {{ option.text }}
      </label>
    </div>

    <select v-else-if="type === 'multiselect' && choices" :id="id" v-model="list" class="input" multiple :disabled="field.disabled">
      <option v-for="option in choices" :key="option.value" :value="option.value">{{ option.text }}</option>
    </select>

    <template v-else-if="type === 'tags' || ['checkboxes', 'multiselect'].includes(type)">
      <input :id="id" v-model.lazy="tags" class="input" type="text" :list="choices ? `${id}-options` : undefined" :disabled="field.disabled">
      <datalist v-if="choices" :id="`${id}-options`">
        <option v-for="option in choices" :key="option.value" :value="option.value">{{ option.text }}</option>
      </datalist>
      <small class="field-help">Separate entries with commas</small>
    </template>

    <textarea v-else-if="type === 'entries'" :id="id" v-model.lazy="entries" class="input textarea" :disabled="field.disabled"></textarea>

    <StructureField v-else-if="type === 'structure'" :model-value="modelValue" :field="field" :errors="errors" :path="path" :model="model" @update:model-value="update" />

    <ObjectField v-else-if="type === 'object'" :model-value="modelValue" :field="field" :errors="errors" :path="path" :model="model" @update:model-value="update" />

    <PickerField v-else-if="type === 'pagepicker' || type === 'userpicker'" :id="id" :model-value="modelValue" :field="field" @update:model-value="update" />

    <BlocksField v-else-if="type === 'blocks'" :model-value="modelValue" :field="field" :errors="errors" :path="path" :model="model" @update:model-value="update" />

    <LayoutField v-else-if="type === 'layout'" :model-value="modelValue" :field="field" :errors="errors" :path="path" :model="model" @update:model-value="update" />

    <FilePicker v-else-if="type === 'filepicker'" :model-value="modelValue" :field="field" :model="model" @update:model-value="update" />

    <!-- unknown types and dynamic options -->
    <input v-else :id="id" v-model="text" class="input" type="text" :disabled="field.disabled">

    <small v-if="field.help" class="field-help">{{ field.help }}</small>
    <small v-if="error" class="field-error">{{ error }}</small>
  </div>
</template>
