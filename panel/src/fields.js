/**
 * Helpers for blueprint fields.
 */

// "2/3" → grid column span of 12
export function span(width) {
  const [a, b] = String(width || "1/1").split("/").map(Number);
  return Math.max(1, Math.min(12, Math.round((12 * a) / (b || 1)))) || 12;
}

// `when: {category: news}` shows a field only if all values match
export function isVisible(field, values) {
  if (!field.when || typeof field.when !== "object") return true;
  return Object.entries(field.when).every(([key, expected]) => {
    const value = values?.[key];
    return Array.isArray(expected) ? expected.includes(value) : String(value ?? "") === String(expected ?? "");
  });
}

// static options as [{value, text}], null for dynamic options (e.g. queries)
export function options(field) {
  const options = field.options;
  if (!options || typeof options !== "object" || options.type) return null;
  if (Array.isArray(options)) {
    return options.map((option) =>
      option && typeof option === "object"
        ? { value: String(option.value ?? option.text), text: String(option.text ?? option.value) }
        : { value: String(option), text: String(option) },
    );
  }
  return Object.entries(options).map(([value, text]) => ({ value, text: String(text?.text ?? text) }));
}

// lists may be stored as arrays or comma separated strings
export function toList(value) {
  if (Array.isArray(value)) return value;
  if (value === null || value === undefined || value === "") return [];
  return String(value).split(",").map((item) => item.trim()).filter(Boolean);
}

export const displayTypes = ["buttons", "filelist", "gap", "headline", "info", "line", "pagelist", "stats"];

// deep copy of (reactive) JSON data
export function clone(value) {
  return JSON.parse(JSON.stringify(value ?? null));
}
