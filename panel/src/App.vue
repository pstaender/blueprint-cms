<script setup>
import { onMounted } from "vue";
import { session, loadSession } from "./api.js";
import LoginView from "./views/LoginView.vue";
import Sidebar from "./components/Sidebar.vue";

onMounted(loadSession);
</script>

<template>
  <template v-if="session.checked">
    <LoginView v-if="!session.user" />
    <div v-else class="layout">
      <Sidebar />
      <main class="main">
        <RouterView :key="$route.fullPath" />
      </main>
    </div>
  </template>
</template>
