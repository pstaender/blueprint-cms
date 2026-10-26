<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue";
import { Editor } from "tiny-markdown-editor";
import { isFileDrag, droppedFileLinks, markdownLink } from "../../files.js";
import Icon from "../Icon.vue";
import PageSelectDialog from "../PageSelectDialog.vue";
import FileSelectDialog from "../FileSelectDialog.vue";

// Markdown editor (TinyMDE) with a toolbar. Links to pages (`page://uid`) and to files of the page are picked in
// dialogs, files dropped on it are inserted as links (files from the computer are uploaded first).
const props = defineProps({
  id: String,
  modelValue: { default: null },
  field: { type: Object, required: true },
  model: { type: Object, default: null },
});
const emit = defineEmits(["update:modelValue"]);

// TinyMDE's command names, `key` is the shortcut with ⌘/Ctrl
const COMMANDS = [
  { command: "bold", icon: "bold", title: "Bold", key: "b" },
  { command: "italic", icon: "italic", title: "Italic", key: "i" },
  { command: "strikethrough", icon: "strikethrough", title: "Strikethrough" },
  { command: "code", icon: "code", title: "Code" },
  { command: "h1", icon: "h1", title: "Heading 1" },
  { command: "h2", icon: "h2", title: "Heading 2" },
  { command: "ul", icon: "list-bullet", title: "Bulleted list" },
  { command: "ol", icon: "list-numbers", title: "Numbered list" },
  { command: "blockquote", icon: "quote", title: "Quote" },
];

const element = ref(null);
const state = ref({});
const pageDialog = ref(false);
const fileDialog = ref(false);
const isDropTarget = ref(false);
const pageId = computed(() => props.model?.id ?? null);
const isMac = /Mac|iPhone|iPad/.test(navigator.platform);
let editor = null;

onMounted(() => {
  editor = new Editor({ element: element.value, content: props.modelValue ?? "", placeholder: props.field.placeholder });
  editor.e.id = props.id;
  editor.e.contentEditable = String(!props.field.disabled);
  editor.addEventListener("change", ({ content }) => {
    if (content !== (props.modelValue ?? "")) emit("update:modelValue", content === "" ? null : content);
  });
  editor.addEventListener("selection", ({ commandState }) => (state.value = commandState ?? {}));
  editor.addEventListener("drop", ({ dataTransfer }) => drop(dataTransfer));
});

onBeforeUnmount(() => editor?.destroy());

// values loaded from outside (reverted changes, a reload)
watch(() => props.modelValue, (value) => {
  if (editor && (value ?? "") !== editor.getContent()) editor.setContent(value ?? "");
});

// toolbar buttons and dialogs take the focus, the editor gets it back with its last selection
function focus() {
  if (editor.e.contains(document.activeElement)) return;
  editor.e.focus();
  editor.restoreLastSelection();
}

// the caret without a selected text
function caret() {
  const focusPosition = editor.getSelection(false);
  const anchor = editor.getSelection(true);
  const collapsed = focusPosition && (!anchor || (anchor.row === focusPosition.row && anchor.col === focusPosition.col));
  return collapsed ? focusPosition : null;
}

// with the caret right before the closing marker, toggling steps over it (instead of removing the formatting)
const CLOSING_MARKERS = { bold: /^(\*\*|__)/, italic: /^[*_]/, strikethrough: /^~~/, code: /^`/ };

// state null: not possible at the caret (e.g. bold in a code block)
function run(command) {
  focus();
  const active = editor.getCommandState()[command];
  if (active === null || active === undefined) return;
  const position = caret();
  const marker = active && position && CLOSING_MARKERS[command]?.exec(editor.lines[position.row].slice(position.col));
  if (marker) editor.setSelection({ row: position.row, col: position.col + marker[0].length });
  else editor.setCommandState(command, !active);
}

// inserts text at the caret, separated by a space from a word before it
function insert(text) {
  focus();
  const position = caret();
  const before = position ? editor.lines[position.row][position.col - 1] : undefined;
  editor.paste(before !== undefined && /\S/.test(before) ? ` ${text}` : text);
}

// a link around the selected text, otherwise `[label](target)`
function insertLink(target, label = "") {
  focus();
  if (!caret() || !label) editor.wrapSelection("[", `](${target})`);
  else insert(`[${label}](${target})`);
}

function insertPage(page) {
  insertLink(page.uid ? page.ref : page.url, page.title);
}

function insertFile(file) {
  if (file.type === "image") {
    insert(markdownLink(file, pageId.value));
  } else {
    insertLink(file.filename, file.filename.replace(/\.[^.]+$/, ""));
  }
}

function onKeydown(event) {
  if (!(isMac ? event.metaKey : event.ctrlKey) || event.altKey || event.shiftKey) return;
  const key = event.key.toLowerCase();
  const command = COMMANDS.find((item) => item.key === key);
  if (command) {
    event.preventDefault();
    run(command.command);
  } else if (key === "k") {
    event.preventDefault();
    insertLink("");
  }
}

function onDragover(event) {
  if (props.field.disabled || !isFileDrag(event.dataTransfer, pageId.value)) return;
  event.preventDefault();
  isDropTarget.value = true;
}

async function drop(dataTransfer) {
  isDropTarget.value = false;
  if (props.field.disabled || !isFileDrag(dataTransfer, pageId.value)) return;
  insert(await droppedFileLinks(dataTransfer, pageId.value));
}

const shortcut = (key) => (key ? ` (${isMac ? "⌘" : "Ctrl+"}${key.toUpperCase()})` : "");
</script>

<template>
  <div class="writer" :class="{ 'is-file-drop': isDropTarget, 'is-disabled': field.disabled }"
    @dragover="onDragover" @dragleave="isDropTarget = false">
    <div v-if="!field.disabled" class="writer-toolbar" role="toolbar" :aria-label="`${field.label} formatting`" @mousedown.prevent>
      <button v-for="item in COMMANDS" :key="item.command" type="button" class="icon-button"
        :class="{ 'is-active': state[item.command] === true }" :aria-pressed="state[item.command] === true"
        :title="item.title + shortcut(item.key)" @click="run(item.command)">
        <Icon :name="item.icon" />
      </button>
      <span class="writer-divider"></span>
      <button type="button" class="icon-button" :title="`Link${shortcut('k')}`" @click="insertLink('')"><Icon name="url" /></button>
      <button type="button" class="icon-button" title="Link to a page" @click="pageDialog = true"><Icon name="page" /></button>
      <button v-if="pageId" type="button" class="icon-button" title="Link to a file of this page" @click="fileDialog = true"><Icon name="attachment" /></button>
    </div>
    <div ref="element" class="writer-editor" :class="`textarea-${field.size || 'medium'}`" @keydown="onKeydown"></div>

    <PageSelectDialog v-model:open="pageDialog" title="Link to a page" @select="insertPage" />
    <FileSelectDialog v-if="pageId" v-model:open="fileDialog" :page-id="pageId" title="Link to a file" @select="insertFile" />
  </div>
</template>
