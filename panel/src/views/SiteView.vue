<script setup>
import { ref, computed, onMounted } from "vue";
import { api, session } from "../api.js";
import { useEditing } from "../editing.js";
import ModelForm from "../components/ModelForm.vue";
import ViewHeader from "../components/ViewHeader.vue";
import PageItems from "../components/PageItems.vue";
import Icon from "../components/Icon.vue";

const site = ref(null);
const { values, errors, message, saving, isDirty, reset, submit } = useEditing(async (data) => {
  site.value = await api.patch("/site", { data });
  reset(site.value.data);
  if (session.site) session.site.title = site.value.title;
});

const model = computed(() => site.value && { id: null, children: site.value.children, canHaveChildren: true });
const hasPageList = computed(() => Object.values(site.value?.blueprint.fields ?? {}).some((field) => field.type === "pagelist"));

onMounted(async () => {
  site.value = await api.get("/site");
  reset(site.value.data);
});
</script>

<template>
  <div v-if="site" class="view">
    <ViewHeader :title="site.title" :is-dirty="isDirty" :saving="saving" :message="message" @save="submit" @discard="reset(site.data)">
      <template #actions>
        <a class="button" href="/" target="_blank"><Icon name="open" /> Open</a>
      </template>
    </ViewHeader>
    <ModelForm :blueprint="site.blueprint" :values="values" :errors="errors" :model="model" />
    <PageItems v-if="!hasPageList" :pages="site.children" :parent="model" />
  </div>
</template>
