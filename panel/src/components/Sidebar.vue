<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from "vue";
import { useRoute } from "vue-router";
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

// Keeps the open page visible in the scrolling tree. The subtrees load one after another and can push it out of
// view again, so for a moment after a navigation every change of the tree's size scrolls it back. Afterwards the
// tree is left alone, e.g. when pages are expanded by hand.
const FOLLOW_MS = 1500;
const route = useRoute();
const tree = ref(null);
let followUntil = 0;
let observer = null;

function showOpenPage() {
  tree.value?.querySelector(".tree-link-open")?.scrollIntoView({ block: "nearest" });
}

function followOpenPage() {
  followUntil = Date.now() + FOLLOW_MS;
  nextTick(showOpenPage);
}

watch(() => route.params.id, followOpenPage);

onMounted(() => {
  observer = new ResizeObserver(() => {
    if (Date.now() < followUntil) showOpenPage();
  });
  observer.observe(tree.value);
  followOpenPage();
});
onBeforeUnmount(() => observer?.disconnect());

watch(pagesCollapsed, (collapsed) => {
  if (!collapsed) followOpenPage();
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
      <div ref="tree">
        <PageTree v-show="!pagesCollapsed" />
      </div>
    </nav>

    <footer class="sidebar-footer">
      <RouterLink :to="`/users/${session.user.uid}`" class="sidebar-link"><Icon name="user" /> {{ session.user.email }}</RouterLink>
      <button type="button" class="sidebar-link" @click="logout"><Icon name="logout" /> Log out</button>
    </footer>
  </aside>
</template>
