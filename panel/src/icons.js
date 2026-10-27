import icons from "./icons.json";

// The panel's line icons (24×24 viewBox, stroke based, drawn with currentColor). The path data was written by hand
// for this project (by Claude, in the style of Feather/Lucide, but not copied from them), so there is no third
// party license. Except `php`: the PHP logo of Skill Icons (MIT, https://github.com/tandpfun/skill-icons), scaled to
// the grid with its letters cut out of the filled square (evenodd). The names follow Kirby's icon set where it has one.
// An icon is a stroke path or `{d, fill}` with a filled path. icons.json is also read by the docs (Panel icons).
export { icons };

// looks like an icon name (known or not), as opposed to an emoji or other text
export const isIconName = (value) => /^[a-z0-9]+(-[a-z0-9]+)*$/i.test(value);
