<script setup>
import { ref, computed, onMounted } from "vue";
import { api } from "../../api.js";
import { toList } from "../../fields.js";

// pagepicker (values: page paths) and userpicker (values: emails) as native select.
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
    ? (await api.get("/users")).map((user) => ({ value: user.email, text: user.name || user.email }))
    : (await api.get("/pages")).map((page) => ({ value: page.path, text: `${page.title} (${page.path})` }));
});

const selected = computed({
  get: () => (isMultiple.value ? toList(props.modelValue) : toList(props.modelValue)[0] ?? ""),
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
