<script setup>
import { ref, watch } from "vue";
import { session, logout } from "../api.js";
import Icon from "./Icon.vue";
import PageTree from "./PageTree.vue";

// The page tree scrolls on its own (brand, site/users and the footer stay visible) and can be collapsed.
const STORAGE_KEY = "blueprint.sidebar.pagesCollapsed";
const pagesCollapsed = ref(readCollapsed());

function readCollapsed() {
  try {
    return localStorage.getItem(STORAGE_KEY) === "1";
  } catch {
    return false;
  }
}

watch(pagesCollapsed, (collapsed) => {
  try {
    localStorage.setItem(STORAGE_KEY, collapsed ? "1" : "0");
  } catch {
    // the sidebar still works without storage
  }
});
</script>

<template>
  <aside class="sidebar">
    <a class="sidebar-brand" href="/" target="_blank" title="Open the website">
      {{ session.site?.title || "Site" }}
    </a>

    <nav class="sidebar-nav">
      <RouterLink to="/site" class="sidebar-link"><Icon name="site" /> Site</RouterLink>
      <RouterLink v-if="session.user?.role === 'admin'" to="/users" class="sidebar-link"><Icon name="users" /> Users</RouterLink>
    </nav>

    <nav class="sidebar-nav sidebar-pages">
      <h3 class="sidebar-heading">
        <button type="button" class="sidebar-heading-toggle" :aria-expanded="!pagesCollapsed" @click="pagesCollapsed = !pagesCollapsed">
          <Icon name="chevron" :class="{ 'icon-open': !pagesCollapsed }" /> Pages
        </button>
      </h3>
      <PageTree v-show="!pagesCollapsed" />
    </nav>

    <footer class="sidebar-footer">
      <RouterLink :to="`/users/${session.user.uid}`" class="sidebar-link"><Icon name="user" /> {{ session.user.email }}</RouterLink>
      <button type="button" class="sidebar-link" @click="logout"><Icon name="logout" /> Log out</button>
    </footer>
  </aside>
</template>
