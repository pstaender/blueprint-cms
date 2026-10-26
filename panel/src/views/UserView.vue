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
import { passkeysSupported, createPasskey, isCancelled } from "../passkeys.js";

const props = defineProps({ uid: { type: String, required: true } });
const router = useRouter();
const user = ref(null);
const dialog = ref(null);
const busy = ref(false);
const error = ref(null);
const form = ref({});

const { values, errors, message, saving, isDirty, conflict, reset, submit, overwrite, loadLatest } = useEditing(async (body) => {
  load(await api.patch(`/users/${props.uid}`, body));
}, async () => load(await api.get(`/users/${props.uid}`)));

function load(data) {
  user.value = data;
  const fieldValues = {};
  for (const name of Object.keys(data.blueprint.fields)) {
    if (name in data) fieldValues[name] = data[name];
  }
  reset(fieldValues, data.modifiedAt);
}

const isCurrent = computed(() => session.user?.uid === props.uid);
// admins change passwords without confirming them with their own password
const needsCurrentPassword = computed(() => session.user?.role !== "admin");

function start(name, passkey = null) {
  form.value = { email: user.value.email, role: user.value.role, password: "", currentPassword: "", name: "", passkey };
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
    // only the passkeys change, unsaved changes in the form stay
    if (dialog.value === "passkey" || dialog.value === "deletePasskey") {
      const result = dialog.value === "passkey"
        ? await createPasskey(props.uid, form.value.name)
        : await api.delete(`/users/${props.uid}/~passkeys/${encodeURIComponent(form.value.passkey.id)}`);
      user.value.passkeys = result.passkeys ?? [];
      dialog.value = null;
      return;
    }
    const changes = dialog.value === "password"
      ? { password: form.value.password, currentPassword: form.value.currentPassword }
      : { email: form.value.email, ...(session.user?.role === "admin" ? { role: form.value.role } : {}) };
    load(await api.patch(`/users/${props.uid}`, changes));
    if (isCurrent.value) Object.assign(session.user, { email: user.value.email, role: user.value.role });
    dialog.value = null;
  } catch (e) {
    if (!isCancelled(e)) error.value = e.message;
  } finally {
    busy.value = false;
  }
}

const formatDate = (date) => new Date(date).toLocaleDateString();
const passkeyInfo = (passkey) => [
  passkey.createdAt && `added ${formatDate(passkey.createdAt)}`,
  passkey.lastUsedAt ? `last used ${formatDate(passkey.lastUsedAt)}` : "not used yet",
].filter(Boolean).join(" · ");

onMounted(async () => load(await api.get(`/users/${props.uid}`)));
</script>

<template>
  <div v-if="user" class="view">
    <ViewHeader
      :title="user.name || user.email"
      :breadcrumb="session.user?.role === 'admin' ? [{ to: '/users', title: 'Users' }] : []"
      :is-dirty="isDirty"
      :saving="saving"
      :message="message"
      :conflict="conflict"
      @save="submit"
      @discard="load(user)"
      @overwrite="overwrite"
      @reload="loadLatest"
    >
      <template #actions>
        <button type="button" class="button" @click="start('account')">{{ user.email }} · {{ user.role }}</button>
        <button type="button" class="button" @click="start('password')">Password</button>
        <button v-if="!isCurrent && session.user?.role === 'admin'" type="button" class="button" title="Delete" @click="start('delete')"><Icon name="trash" /></button>
      </template>
    </ViewHeader>

    <ModelForm :blueprint="user.blueprint" :values="values" :errors="errors" />

    <section class="section">
      <header class="section-header">
        <h3 class="field-label">Passkeys</h3>
        <button v-if="isCurrent && passkeysSupported" type="button" class="button button-small" @click="start('passkey')">
          <Icon name="plus" /> Add passkey
        </button>
      </header>
      <ul v-if="user.passkeys?.length" class="items">
        <li v-for="passkey in user.passkeys" :key="passkey.id" class="item">
          <div class="item-link">
            <span class="item-icon"><Icon name="key" /></span>
            <span class="item-title">{{ passkey.name }}</span>
            <span class="item-info">{{ passkeyInfo(passkey) }}</span>
            <button type="button" class="icon-button" :title="`Delete ${passkey.name}`" @click="start('deletePasskey', passkey)"><Icon name="trash" /></button>
          </div>
        </li>
      </ul>
      <p v-else class="empty">
        {{ !isCurrent ? "No passkeys" : passkeysSupported ? "No passkeys yet: add one to log in without a password" : "This browser doesn't support passkeys" }}
      </p>
    </section>

    <Dialog
      :open="dialog !== null"
      :title="{ account: 'Account', password: 'Change password', delete: 'Delete user', passkey: 'Add passkey', deletePasskey: 'Delete passkey' }[dialog]"
      :submit-label="{ delete: 'Delete', deletePasskey: 'Delete', passkey: 'Add' }[dialog] ?? 'Save'"
      :danger="dialog === 'delete' || dialog === 'deletePasskey'"
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
          <span class="field-label">Current password</span>
          <input v-model="form.currentPassword" class="input" type="password" autocomplete="current-password" required>
        </label>
        <label class="field">
          <span class="field-label">New password</span>
          <input v-model="form.password" class="input" type="password" minlength="8" autocomplete="new-password" required>
        </label>
      </template>
      <p v-else-if="dialog === 'delete'">Do you really want to delete <strong>{{ user.email }}</strong>?</p>
      <template v-else-if="dialog === 'passkey'">
        <label class="field">
          <span class="field-label">Name</span>
          <input v-model="form.name" class="input" type="text" placeholder="e.g. Laptop, Phone" maxlength="64">
        </label>
        <small class="field-help">Your browser asks for the device (or password manager) that stores the passkey.</small>
      </template>
      <p v-else-if="dialog === 'deletePasskey'">
        Do you really want to delete the passkey <strong>{{ form.passkey.name }}</strong>? It can't be used to log in anymore.
      </p>
    </Dialog>
  </div>
</template>
