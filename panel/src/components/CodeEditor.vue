<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from "vue";

// PHP editor (CodeMirror, loaded on demand): highlighting, Tab/Shift+Tab indents, ⌘/Ctrl + F search.
// `errorLine` marks a syntax error.
const props = defineProps({
  modelValue: { type: String, default: "" },
  errorLine: { type: Number, default: null },
  errorMessage: { type: String, default: "" },
  // id of the field label
  labelledby: { type: String, default: null },
});
const emit = defineEmits(["update:modelValue"]);

const element = ref(null);
const loading = ref(true);
let view = null;
let setDiagnostics = null;

onMounted(async () => {
  const [{ basicSetup }, { EditorView, keymap }, { indentWithTab }, { indentUnit, HighlightStyle, syntaxHighlighting }, lint, { php }, { tags }] = await Promise.all([
    import("codemirror"),
    import("@codemirror/view"),
    import("@codemirror/commands"),
    import("@codemirror/language"),
    import("@codemirror/lint"),
    import("@codemirror/lang-php"),
    import("@lezer/highlight"),
  ]);
  if (!element.value) return;

  // the colors are css variables (light and dark mode), see styles.css
  const highlightStyle = HighlightStyle.define([
    { tag: [tags.keyword, tags.modifier, tags.controlKeyword, tags.operatorKeyword], color: "var(--code-keyword)" },
    { tag: [tags.string, tags.special(tags.string)], color: "var(--code-string)" },
    { tag: [tags.number, tags.bool, tags.null, tags.atom], color: "var(--code-number)" },
    { tag: [tags.comment, tags.meta], color: "var(--code-comment)", fontStyle: "italic" },
    { tag: [tags.variableName, tags.special(tags.variableName)], color: "var(--code-variable)" },
    { tag: [tags.function(tags.variableName), tags.function(tags.propertyName)], color: "var(--code-function)" },
    { tag: [tags.className, tags.typeName, tags.namespace], color: "var(--code-class)" },
    { tag: [tags.processingInstruction, tags.angleBracket, tags.tagName], color: "var(--code-tag)" },
    { tag: tags.invalid, color: "var(--color-negative)" },
  ]);

  setDiagnostics = lint.setDiagnostics;
  view = new EditorView({
    parent: element.value,
    doc: props.modelValue,
    extensions: [
      basicSetup,
      keymap.of([indentWithTab]),
      indentUnit.of("    "),
      php(),
      syntaxHighlighting(highlightStyle),
      // CodeMirror's dark selection and tooltip colors
      EditorView.darkTheme.of(matchMedia("(prefers-color-scheme: dark)").matches),
      lint.lintGutter(),
      EditorView.contentAttributes.of(props.labelledby ? { "aria-labelledby": props.labelledby } : {}),
      EditorView.updateListener.of((update) => {
        if (update.docChanged) emit("update:modelValue", update.state.doc.toString());
      }),
    ],
  });
  loading.value = false;
  markError();
});

onBeforeUnmount(() => view?.destroy());

// e.g. discard or reload
watch(() => props.modelValue, (value) => {
  if (view && value !== view.state.doc.toString()) {
    view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: value } });
  }
});

watch(() => [props.errorLine, props.errorMessage], markError);

function markError() {
  if (!view) return;
  const { doc } = view.state;
  const line = props.errorLine ? doc.line(Math.min(Math.max(props.errorLine, 1), doc.lines)) : null;
  const diagnostics = line ? [{ from: line.from, to: line.to, severity: "error", message: props.errorMessage || "Syntax error" }] : [];
  view.dispatch(setDiagnostics(view.state, diagnostics));
}
</script>

<template>
  <div ref="element" class="code-editor">
    <p v-if="loading" class="code-editor-loading">Loading editor…</p>
  </div>
</template>
