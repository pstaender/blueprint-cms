<script setup>
import { ref, watch, onMounted } from "vue";
import { api, session, logout } from "../api.js";
import Icon from "./Icon.vue";
import StatusDot from "./StatusDot.vue";

const pages = ref([]);

async function load() {
  pages.value = (await api.get("/site")).children;
}

onMounted(load);
watch(() => session.changes, load);
</script>

<template>
  <aside class="sidebar">
    <a class="sidebar-brand" href="/" target="_blank" title="Open the website">
      {{ session.site?.title || "Site" }}
    </a>

    <nav class="sidebar-nav">
      <RouterLink to="/site" class="sidebar-link"><Icon name="site" /> Site</RouterLink>
      <RouterLink to="/users" class="sidebar-link"><Icon name="users" /> Users</RouterLink>
    </nav>

    <nav v-if="pages.length" class="sidebar-nav">
      <h3 class="sidebar-heading">Pages</h3>
      <RouterLink v-for="page in pages" :key="page.id" :to="`/pages/${page.id}`" class="sidebar-link">
        <StatusDot :status="page.status" /> {{ page.title }}
      </RouterLink>
    </nav>

    <footer class="sidebar-footer">
      <RouterLink :to="`/users/${session.user.uid}`" class="sidebar-link"><Icon name="user" /> {{ session.user.email }}</RouterLink>
      <button type="button" class="sidebar-link" @click="logout"><Icon name="logout" /> Log out</button>
    </footer>
  </aside>
</template>
