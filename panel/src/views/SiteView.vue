<script setup>
import { ref, computed, onMounted } from "vue";
import { api, session } from "../api.js";
import { useEditing } from "../editing.js";
import ModelForm from "../components/ModelForm.vue";
import ViewHeader from "../components/ViewHeader.vue";
import Icon from "../components/Icon.vue";

const site = ref(null);
const { values, errors, message, saving, isDirty, conflict, reset, submit, overwrite, loadLatest } = useEditing(async (body) => {
  site.value = await api.patch("/site", body);
  reset(site.value.data, site.value.data.modifiedAt);
  if (session.site) session.site.title = site.value.title;
}, load);

async function reloadChildren() {
  site.value.children = (await api.get("/site")).children;
}

const model = computed(() => site.value && { id: null, children: site.value.children, canHaveChildren: true, reload: reloadChildren });

async function load() {
  site.value = await api.get("/site");
  reset(site.value.data, site.value.data.modifiedAt);
}

onMounted(load);
</script>

<template>
  <div v-if="site" class="view">
    <ViewHeader :title="site.title" :is-dirty="isDirty" :saving="saving" :message="message" :conflict="conflict" @save="submit" @discard="reset(site.data, site.data.modifiedAt)" @overwrite="overwrite" @reload="loadLatest">
      <template #actions>
        <a class="button" href="/" target="_blank"><Icon name="open" /> Open</a>
      </template>
    </ViewHeader>
    <ModelForm :blueprint="site.blueprint" :values="values" :errors="errors" :model="model" />
  </div>
</template>
