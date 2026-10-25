<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";
import ViewHeader from "../components/ViewHeader.vue";
import Dialog from "../components/Dialog.vue";
import Icon from "../components/Icon.vue";
import RoleInput from "../components/RoleInput.vue";

const router = useRouter();
const users = ref([]);
const open = ref(false);
const busy = ref(false);
const error = ref(null);
const form = ref({ email: "", password: "", role: "user" });

async function create() {
  busy.value = true;
  try {
    const user = await api.post("/users", form.value);
    open.value = false;
    router.push(`/users/${user.uid}`);
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}

function start() {
  form.value = { email: "", password: "", role: "user" };
  error.value = null;
  open.value = true;
}

onMounted(async () => {
  users.value = await api.get("/users");
});
</script>

<template>
  <div class="view">
    <ViewHeader title="Users">
      <template #actions>
        <button type="button" class="button" @click="start"><Icon name="plus" /> Add</button>
      </template>
    </ViewHeader>

    <ul class="items items-list">
      <li v-for="user in users" :key="user.uid" class="item">
        <RouterLink :to="`/users/${user.uid}`" class="item-link">
          <span class="item-icon"><Icon name="user" /></span>
          <span class="item-title">{{ user.name || user.email }}</span>
          <span class="item-info">{{ user.email }}</span>
          <span class="item-info">{{ user.role || "user" }}</span>
        </RouterLink>
      </li>
    </ul>

    <Dialog v-model:open="open" title="New user" submit-label="Create" :busy="busy" @submit="create">
      <p v-if="error" class="notice notice-negative">{{ error }}</p>
      <label class="field">
        <span class="field-label">Email</span>
        <input v-model="form.email" class="input" type="text" required>
      </label>
      <label class="field">
        <span class="field-label">Password</span>
        <input v-model="form.password" class="input" type="password" minlength="8" autocomplete="new-password" required>
      </label>
      <RoleInput v-model="form.role" />
    </Dialog>
  </div>
</template>
