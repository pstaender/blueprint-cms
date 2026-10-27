// Blueprint CMS docs: heading anchors, "On this page", copy buttons for code, the mobile menu and the search.

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

// Fuzzy search (Fuse.js) over the pages of the menu, "/" focuses it
function search() {
  const box = document.querySelector(".docs-search");
  if (!box) return;

  const input = box.querySelector("input");
  const results = box.querySelector(".docs-search-results");
  const pages = [...document.querySelectorAll("#docs-menu a")].map((link) => ({
    title: link.textContent.trim(),
    url: link.getAttribute("href"),
    // the titles of the sections above, e.g. "Reference › Fields"
    section: [...ancestors(link)].map((summary) => summary.textContent.trim()).reverse().join(" › "),
  }));
  const fuse = window.Fuse ? new Fuse(pages, { keys: [{ name: "title", weight: 3 }, "section"], threshold: 0.4, ignoreLocation: true }) : null;
  let active = 0;

  function* ancestors(link) {
    for (let item = link.closest("li")?.parentElement.closest("li"); item; item = item.parentElement.closest("li")) {
      const summary = item.querySelector(":scope > details > summary");
      if (summary) yield summary;
    }
  }

  function find(query) {
    if (fuse) return fuse.search(query, { limit: 8 }).map((result) => result.item);
    // without Fuse (e.g. offline) a plain substring search
    return pages.filter((page) => `${page.title} ${page.section}`.toLowerCase().includes(query.toLowerCase())).slice(0, 8);
  }

  function render() {
    const query = input.value.trim();
    const found = query ? find(query) : [];
    active = 0;
    results.replaceChildren(...(query && !found.length ? [empty()] : found.map(option)));
    results.hidden = !query;
    input.setAttribute("aria-expanded", String(!results.hidden));
    highlight();
  }

  function option(page, index) {
    const item = document.createElement("li");
    item.role = "option";
    const link = document.createElement("a");
    link.href = page.url;
    link.textContent = page.title;
    if (page.section) {
      const section = document.createElement("small");
      section.textContent = page.section;
      link.append(section);
    }
    link.addEventListener("mousemove", () => {
      active = index;
      highlight();
    });
    item.append(link);
    return item;
  }

  function empty() {
    const item = document.createElement("li");
    item.className = "docs-search-empty";
    item.textContent = "No pages found";
    return item;
  }

  function highlight() {
    [...results.querySelectorAll("a")].forEach((link, index) => {
      link.classList.toggle("is-active", index === active);
      link.parentElement.setAttribute("aria-selected", String(index === active));
    });
  }

  function close() {
    results.hidden = true;
    input.setAttribute("aria-expanded", "false");
  }

  input.addEventListener("input", render);
  input.addEventListener("focus", () => input.value.trim() && render());
  input.addEventListener("keydown", (event) => {
    const links = results.querySelectorAll("a");
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      if (!links.length) return;
      active = (active + (event.key === "ArrowDown" ? 1 : -1) + links.length) % links.length;
      highlight();
      links[active].scrollIntoView({ block: "nearest" });
    } else if (event.key === "Enter" && links[active]) {
      event.preventDefault();
      location.href = links[active].href;
    } else if (event.key === "Escape") {
      if (results.hidden) input.blur();
      close();
    }
  });
  document.addEventListener("pointerdown", (event) => box.contains(event.target) || close());
  document.addEventListener("keydown", (event) => {
    const typing = event.target.closest("input, textarea, select, [contenteditable]");
    if (event.key === "/" && !typing) {
      event.preventDefault();
      input.focus();
    }
  });
}

// "Was this page helpful?", stored by docs/rating/page.php
function rating() {
  const form = document.querySelector(".docs-rating");
  if (!form) return;

  const message = form.querySelector(".docs-rating-message");
  form.hidden = false;
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = new FormData(form);
    data.set("ratingValue", event.submitter.value);
    form.querySelectorAll("button").forEach((button) => (button.disabled = true));
    try {
      const response = await fetch(form.action, { method: "POST", body: data });
      const result = JSON.parse(await response.text());
      message.textContent = result.ok ? "Thanks for your feedback!" : result.error;
    } catch {
      message.textContent = "The rating couldn't be saved.";
    }
  });
}

if (text) {
  tableOfContents(headingAnchors());
  copyButtons();

  // the ids only exist now, so jump to the anchor of the url again
  if (location.hash) document.getElementById(decodeURIComponent(location.hash.slice(1)))?.scrollIntoView();
}
mobileMenu();
search();
rating();
