<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api, session } from "../api.js";
import { useEditing } from "../editing.js";
import ModelForm from "../components/ModelForm.vue";
import ViewHeader from "../components/ViewHeader.vue";
import Dialog from "../components/Dialog.vue";
import Icon from "../components/Icon.vue";
import RoleInput from "../components/RoleInput.vue";

const props = defineProps({ uid: { type: String, required: true } });
const router = useRouter();
const user = ref(null);
const dialog = ref(null);
const busy = ref(false);
const error = ref(null);
const form = ref({});

const { values, errors, message, saving, isDirty, reset, submit } = useEditing(async (data) => {
  load(await api.patch(`/users/${props.uid}`, { data }));
});

function load(data) {
  user.value = data;
  const fieldValues = {};
  for (const name of Object.keys(data.blueprint.fields)) {
    if (name in data) fieldValues[name] = data[name];
  }
  reset(fieldValues);
}

const isCurrent = computed(() => session.user?.uid === props.uid);
// admins change passwords without confirming them with their own password
const needsCurrentPassword = computed(() => session.user?.role !== "admin");

function start(name) {
  form.value = { email: user.value.email, role: user.value.role, password: "", currentPassword: "" };
  error.value = null;
  dialog.value = name;
}

async function saveDialog() {
  busy.value = true;
  try {
    if (dialog.value === "delete") {
      await api.delete(`/users/${props.uid}`);
      dialog.value = null;
      return router.push("/users");
    }
    const changes = dialog.value === "password"
      ? { password: form.value.password, currentPassword: form.value.currentPassword }
      : { email: form.value.email, ...(session.user?.role === "admin" ? { role: form.value.role } : {}) };
    load(await api.patch(`/users/${props.uid}`, changes));
    if (isCurrent.value) Object.assign(session.user, { email: user.value.email, role: user.value.role });
    dialog.value = null;
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}

onMounted(async () => load(await api.get(`/users/${props.uid}`)));
</script>

<template>
  <div v-if="user" class="view">
    <ViewHeader
      :title="user.name || user.email"
      :breadcrumb="[{ to: '/users', title: 'Users' }]"
      :is-dirty="isDirty"
      :saving="saving"
      :message="message"
      @save="submit"
      @discard="load(user)"
    >
      <template #actions>
        <button type="button" class="button" @click="start('account')">{{ user.email }} · {{ user.role }}</button>
        <button type="button" class="button" @click="start('password')">Password</button>
        <button v-if="!isCurrent" type="button" class="button" title="Delete" @click="start('delete')"><Icon name="trash" /></button>
      </template>
    </ViewHeader>

    <ModelForm :blueprint="user.blueprint" :values="values" :errors="errors" />

    <Dialog
      :open="dialog !== null"
      :title="{ account: 'Account', password: 'Change password', delete: 'Delete user' }[dialog]"
      :submit-label="dialog === 'delete' ? 'Delete' : 'Save'"
      :danger="dialog === 'delete'"
      :busy="busy"
      @update:open="dialog = $event ? dialog : null"
      @submit="saveDialog"
    >
      <p v-if="error" class="notice notice-negative">{{ error }}</p>
      <template v-if="dialog === 'account'">
        <label class="field">
          <span class="field-label">Email</span>
          <input v-model="form.email" class="input" type="text" required>
        </label>
        <RoleInput v-if="session.user?.role === 'admin'" v-model="form.role" />
      </template>
      <template v-else-if="dialog === 'password'">
        <label v-if="needsCurrentPassword" class="field">
          <span class="field-label">{{ isCurrent ? "Current password" : "Your password" }}</span>
          <input v-model="form.currentPassword" class="input" type="password" autocomplete="current-password" required>
        </label>
        <label class="field">
          <span class="field-label">New password</span>
          <input v-model="form.password" class="input" type="password" minlength="8" autocomplete="new-password" required>
        </label>
      </template>
      <p v-else-if="dialog === 'delete'">Do you really want to delete <strong>{{ user.email }}</strong>?</p>
    </Dialog>
  </div>
</template>
