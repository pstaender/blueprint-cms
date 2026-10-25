<script setup>
import { ref } from "vue";
import { login } from "../api.js";

const email = ref("");
const password = ref("");
const error = ref(null);
const busy = ref(false);

async function submit() {
  busy.value = true;
  error.value = null;
  try {
    await login(email.value, password.value);
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="login">
    <form class="login-form" @submit.prevent="submit">
      <h1 class="login-title">Log in</h1>
      <p v-if="error" class="notice notice-negative">{{ error }}</p>
      <label class="field">
        <span class="field-label">Email</span>
        <input v-model="email" class="input" type="text" autocomplete="username" required autofocus>
      </label>
      <label class="field">
        <span class="field-label">Password</span>
        <input v-model="password" class="input" type="password" autocomplete="current-password" required>
      </label>
      <button class="button button-primary" type="submit" :disabled="busy">Log in</button>
    </form>
  </div>
</template>
