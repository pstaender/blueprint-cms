<script setup>
import { ref, computed, watch, provide, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, session } from "../api.js";
import Icon from "../components/Icon.vue";
import Dialog from "../components/Dialog.vue";
import Pagination from "../components/Pagination.vue";

// The records of a model (collection) next to the open record (child route): search, sort and, with
// `softDelete`, the trash as an expandable item below the records.
const props = defineProps({ name: { type: String, required: true } });
const route = useRoute();
const router = useRouter();

const collection = ref(null);
const records = ref([]);
const trashed = ref([]);
const trashTotal = ref(0);
const trashOpen = ref(false);
const loadError = ref(null);
const search = ref("");
const sort = ref("createdAt");
const direction = ref("desc");
// pages start at 1, the page size is the blueprint's `perPage` (default 10)
const page = ref(1);
const trashPage = ref(1);
const perPage = computed(() => collection.value?.perPage ?? 10);

// the open record needs the blueprint for new records
provide("collection", collection);

function query(params = {}) {
  return new URLSearchParams({ search: search.value, sort: sort.value, direction: direction.value, ...params });
}

async function load() {
  try {
    collection.value = await api.get(`/models/${props.name}?${query(pageParams(page.value))}`);
    // e.g. the last record of the last page was deleted
    if (page.value > 1 && !collection.value.records.length) return (page.value -= 1);
    records.value = collection.value.records;
    loadError.value = null;
    if (trashOpen.value) await loadTrash();
  } catch (e) {
    loadError.value = e.message;
  }
}

async function loadTrash() {
  const trash = await api.get(`/models/${props.name}?${query({ trash: 1, ...pageParams(trashPage.value) })}`);
  if (trashPage.value > 1 && !trash.records.length) return (trashPage.value -= 1);
  trashed.value = trash.records;
  trashTotal.value = trash.total;
}

function pageParams(number) {
  return { offset: (number - 1) * perPage.value, limit: perPage.value };
}

let searchTimeout = null;
watch(search, () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(firstPage, 200);
});
watch([sort, direction], firstPage);
watch(page, load);
watch(trashPage, loadTrash);
watch(trashOpen, (open) => open && loadTrash());
// saved, deleted or restored records
watch(() => session.changes, load);

// restore or delete permanently right in the trash
const deleting = ref(null);
const busy = ref(false);
const actionError = ref(null);

async function closeIfOpen(record) {
  if (route.params.uid === record.uid) await router.push(`/models/${props.name}`);
}

async function restore(record) {
  actionError.value = null;
  try {
    await api.post(`/models/${props.name}/${record.uid}/~restore`);
  } catch (e) {
    actionError.value = e.message;
  }
}

// a record or "all" (empty the trash)
async function deletePermanently() {
  busy.value = true;
  actionError.value = null;
  try {
    if (deleting.value === "all") {
      await api.delete(`/models/${props.name}/~trash`);
      if (route.params.uid && trashed.value.some((record) => record.uid === route.params.uid)) await router.push(`/models/${props.name}`);
    } else {
      await api.delete(`/models/${props.name}/${deleting.value.uid}`);
      await closeIfOpen(deleting.value);
    }
    deleting.value = null;
  } catch (e) {
    actionError.value = e.message;
  } finally {
    busy.value = false;
  }
}

function firstPage() {
  if (page.value === 1 && trashPage.value === 1) return load();
  page.value = trashPage.value = 1;
}

const formatDate = (date) => (date ? new Date(date).toLocaleString() : "");

onMounted(load);
</script>

<template>
  <p v-if="loadError" class="notice notice-negative">{{ loadError }}</p>
  <div v-else-if="collection" class="collection-view">
    <section class="record-list">
      <header class="record-list-header">
        <h1 class="view-title">{{ collection.title }}</h1>
        <RouterLink :to="`/models/${name}/new`" class="button button-small"><Icon name="plus" /> Add</RouterLink>
      </header>
      <input v-model="search" type="search" class="input" placeholder="Search" aria-label="Search">
      <div class="record-list-controls">
        <select v-model="sort" class="input" aria-label="Sort by">
          <option value="createdAt">Created</option>
          <option value="modifiedAt">Modified</option>
        </select>
        <select v-model="direction" class="input" aria-label="Direction">
          <option value="desc">Newest first</option>
          <option value="asc">Oldest first</option>
        </select>
      </div>
      <small class="field-help">{{ collection.total }} {{ collection.total === 1 ? "record" : "records" }}</small>
      <p v-if="actionError" class="notice notice-negative">{{ actionError }}</p>

      <div class="record-scroll">
        <ul v-if="records.length" class="items">
          <li v-for="record in records" :key="record.uid" class="item">
            <RouterLink :to="`/models/${name}/${record.uid}`" class="item-link record-link">
              <span class="item-title">{{ record.title }}</span>
              <span class="item-info">{{ formatDate(record[sort]) }}</span>
            </RouterLink>
          </li>
        </ul>
        <p v-else class="empty">{{ search ? "No matching records" : "No records yet" }}</p>
        <Pagination v-model:page="page" :total="collection.total" :per-page="perPage" />

        <details v-if="collection.softDelete" class="record-trash" :open="trashOpen" @toggle="trashOpen = $event.target.open">
          <summary class="item-link record-trash-summary">
            <Icon name="chevron" class="record-trash-chevron" />
            <span class="item-title">Trash</span>
            <span class="item-info">{{ collection.trashCount }}</span>
          </summary>
          <ul v-if="trashed.length" class="items">
            <li v-for="record in trashed" :key="record.uid" class="item record-item">
              <RouterLink :to="`/models/${name}/${record.uid}`" class="item-link record-link">
                <span class="item-title">{{ record.title }}</span>
                <span class="item-info">{{ formatDate(record.modifiedAt) }}</span>
              </RouterLink>
              <button type="button" class="icon-button" :title="`Restore ${record.title}`" @click="restore(record)"><Icon name="undo" /></button>
              <button type="button" class="icon-button" :title="`Delete ${record.title} permanently`" @click="deleting = record"><Icon name="trash" /></button>
            </li>
          </ul>
          <p v-else class="empty">{{ search ? "No matching records in the trash" : "The trash is empty" }}</p>
          <Pagination v-model:page="trashPage" :total="trashTotal" :per-page="perPage" />
          <button v-if="trashTotal" type="button" class="button button-small record-trash-empty" @click="deleting = 'all'"><Icon name="trash" /> Empty trash</button>
        </details>
      </div>
    </section>

    <Dialog
      :open="deleting !== null"
      :title="deleting === 'all' ? 'Empty trash' : 'Delete permanently'"
      submit-label="Delete"
      danger
      :busy="busy"
      @update:open="deleting = $event ? deleting : null"
      @submit="deletePermanently"
    >
      <p v-if="deleting === 'all'">Do you really want to delete all {{ collection.trashCount }} records in the trash and their files permanently? This can't be undone.</p>
      <p v-else>Do you really want to delete <strong>{{ deleting?.title }}</strong> and its files permanently? This can't be undone.</p>
    </Dialog>

    <div class="record-detail">
      <RouterView v-slot="{ Component, route: childRoute }">
        <component :is="Component" v-if="Component" :key="childRoute.fullPath" />
        <p v-else class="empty">Select a record</p>
      </RouterView>
    </div>
  </div>
</template>
