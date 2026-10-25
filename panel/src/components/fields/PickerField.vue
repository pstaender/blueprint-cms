<script setup>
import { ref, computed, onMounted } from "vue";
import { api } from "../../api.js";
import { toList } from "../../fields.js";

// pagepicker and userpicker as native select, values are references (page://uid, user://uid).
// Older values (page paths, emails) are still recognized and replaced by references on change.
const props = defineProps({
  id: String,
  modelValue: { default: null },
  field: { type: Object, required: true },
});
const emit = defineEmits(["update:modelValue"]);

const items = ref([]);
const isMultiple = computed(() => props.field.multiple !== false && props.field.max !== 1);

onMounted(async () => {
  items.value = props.field.type === "userpicker"
    ? (await api.get("/users")).map((user) => ({ value: user.ref, aliases: [user.email, user.uid], text: user.name || user.email }))
    : (await api.get("/pages")).map((page) => ({ value: page.ref, aliases: [page.path], text: `${page.title} (${page.path})` }));
});

function normalize(value) {
  return items.value.find((item) => item.value === value || item.aliases.includes(value))?.value ?? value;
}

const selected = computed({
  get: () => {
    const values = toList(props.modelValue).map(normalize);
    return isMultiple.value ? values : values[0] ?? "";
  },
  set: (value) => {
    const list = Array.isArray(value) ? value : value ? [value] : [];
    emit("update:modelValue", list.length ? list : null);
  },
});
</script>

<template>
  <select :id="id" v-model="selected" class="input" :multiple="isMultiple" :disabled="field.disabled">
    <option v-if="!isMultiple" value="">—</option>
    <option v-for="item in items" :key="item.value" :value="item.value">{{ item.text }}</option>
  </select>
</template>
