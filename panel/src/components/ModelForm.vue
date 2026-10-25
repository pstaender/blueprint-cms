<script setup>
import { ref, computed } from "vue";
import FieldList from "./FieldList.vue";
import { span } from "../fields.js";

// The panel view of a blueprint: tabs → columns → fields.
const props = defineProps({
  blueprint: { type: Object, required: true },
  values: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  model: { type: Object, default: null },
});

const tabs = computed(() => Object.values(props.blueprint.tabs ?? {}));
const active = ref(tabs.value[0]?.name);
const tab = computed(() => tabs.value.find((tab) => tab.name === active.value) ?? tabs.value[0]);

// tabs with errors are highlighted
function hasErrors(tab) {
  const names = tab.columns.flatMap((column) => Object.keys(column.fields));
  return Object.keys(props.errors).some((path) => names.includes(path.split(".")[0]));
}
</script>

<template>
  <nav v-if="tabs.length > 1" class="tabs">
    <button
      v-for="item in tabs"
      :key="item.name"
      type="button"
      class="tab"
      :class="{ 'tab-active': item.name === tab.name, 'tab-invalid': hasErrors(item) }"
      @click="active = item.name"
    >
      {{ item.label }}
    </button>
  </nav>

  <div v-if="tab" class="columns">
    <div v-for="(column, index) in tab.columns" :key="index" class="column" :style="{ gridColumn: `span ${span(column.width)}` }">
      <FieldList :fields="column.fields" :values="values" :errors="errors" :model="model" />
    </div>
  </div>
</template>
