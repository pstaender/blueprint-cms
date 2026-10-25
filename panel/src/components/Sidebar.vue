<script setup>
import { session, logout } from "../api.js";
import Icon from "./Icon.vue";
import PageTree from "./PageTree.vue";
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

    <nav class="sidebar-nav">
      <h3 class="sidebar-heading">Pages</h3>
      <PageTree />
    </nav>

    <footer class="sidebar-footer">
      <RouterLink :to="`/users/${session.user.uid}`" class="sidebar-link"><Icon name="user" /> {{ session.user.email }}</RouterLink>
      <button type="button" class="sidebar-link" @click="logout"><Icon name="logout" /> Log out</button>
    </footer>
  </aside>
</template>
