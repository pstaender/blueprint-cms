// Blueprint CMS docs: heading anchors, "On this page", copy buttons for code and the mobile menu.

const text = document.querySelector(".docs-text");

// "Field methods" → "field-methods", like the anchors in the docs' links
function slugify(value) {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^\p{L}\p{N}\s_-]/gu, "")
    .replace(/\s+/g, "-");
}

function headingAnchors() {
  const headings = [...text.querySelectorAll("h2, h3")];
  const used = new Set();

  for (const heading of headings) {
    let id = heading.id || slugify(heading.textContent);
    for (let i = 2; used.has(id); i++) id = `${slugify(heading.textContent)}-${i}`;
    used.add(id);
    heading.id = id;

    const anchor = document.createElement("a");
    anchor.className = "anchor";
    anchor.href = `#${id}`;
    anchor.textContent = "#";
    anchor.setAttribute("aria-label", "Link to this section");
    heading.append(anchor);
  }

  return headings;
}

function tableOfContents(headings) {
  const toc = document.querySelector(".docs-toc");
  if (!toc || headings.length < 2) return;

  const list = toc.querySelector("ul");
  const links = new Map();

  for (const heading of headings) {
    const item = document.createElement("li");
    item.className = `toc-${heading.tagName.toLowerCase()}`;
    const link = document.createElement("a");
    link.href = `#${heading.id}`;
    link.textContent = heading.firstChild.textContent.trim();
    item.append(link);
    list.append(item);
    links.set(heading, link);
  }

  toc.hidden = false;

  // highlights the last heading above the upper third of the screen
  const update = () => {
    let current = headings[0];
    for (const heading of headings) {
      if (heading.getBoundingClientRect().top < window.innerHeight / 3) current = heading;
    }
    for (const [heading, link] of links) link.classList.toggle("is-active", heading === current);
  };
  document.addEventListener("scroll", update, { passive: true });
  update();
}

function copyButtons() {
  for (const pre of text.querySelectorAll("pre")) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "docs-copy";
    button.textContent = "Copy";
    button.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(pre.querySelector("code")?.textContent ?? pre.textContent);
        button.textContent = "Copied";
      } catch {
        button.textContent = "Failed";
      }
      setTimeout(() => (button.textContent = "Copy"), 1500);
    });
    pre.append(button);
  }
}

function mobileMenu() {
  const toggle = document.querySelector(".docs-menu-toggle");
  if (!toggle) return;

  toggle.addEventListener("click", () => {
    const open = document.body.classList.toggle("menu-open");
    toggle.setAttribute("aria-expanded", String(open));
  });

  // the current page is visible when the menu opens
  document.querySelector(".docs-menu [aria-current=page]")?.scrollIntoView({ block: "center" });
}

if (text) {
  tableOfContents(headingAnchors());
  copyButtons();

  // the ids only exist now, so jump to the anchor of the url again
  if (location.hash) document.getElementById(decodeURIComponent(location.hash.slice(1)))?.scrollIntoView();
}
mobileMenu();
