---
name: miso-convert
description: >
  Converts an existing static HTML website into a Miso static site generator project.
  Use this skill whenever the user wants to migrate, port, or convert a static HTML site
  to Miso, or when they say things like "convert this site to Miso", "port my HTML site",
  "make this work with the Miso SSG", or "migrate my website to Miso templates".
  Also trigger when the user points at a folder of HTML files and mentions Miso, templates,
  or collections in the same breath — even if they don't say "convert" explicitly.
---

# Miso Convert

You are converting a static HTML website into a **Miso** project — a PHP static site generator that uses Twig templates and Markdown content files.

## Miso essentials

- **Templates** live in `templates/` as `.twig.html` files
- **Content** lives in `content/` as `.md` files with YAML frontmatter
- **Assets** (css, js, images) are copied verbatim to `_site/` on build
- **Config** lives in `_config/site.yaml` (site title, base_url, collections, menus, etc.)
- All templates extend `base.twig.html` via `{% extends "base.twig.html" %}` + `{% block content %}`
- The Markdown parser is **GFM (GitHub Flavored Markdown)** via league/commonmark

### Two content modes

| Situation | Template to use | Content style |
|-----------|----------------|---------------|
| Simple prose page (blog post, doc, article) | `page.twig.html` or a dedicated template | Plain Markdown |
| Rich layout page (hero + grids + sections + cards) | `full-page.twig.html` | Raw HTML in the `.md` file |

`page.twig.html` wraps `{{ content\|raw }}` in `<div class="article-body" style="max-width:800px">` — good for prose, bad for full-width layouts.

`full-page.twig.html` outputs `{{ content\|raw }}` directly — use this for any page with multi-column grids, feature cards, partner tiers, hero sections, etc.

### GFM caveat — textarea tags

`<textarea>` is a GFM type-1 raw HTML block element (like `<script>` and `<pre>`). When it appears **inline** (e.g. after a `<label>` on the same line, or inside another HTML block without a blank line before it), GFM escapes it as `&lt;textarea&gt;`.

**Fix**: always put a blank line before any `<textarea>` tag in Markdown content files:

```
<label class="form-label" for="msg">Message</label>

<textarea id="msg" name="msg" class="form-textarea"></textarea>
```

---

## Conversion process

Work through these phases **in order**. Do the full analysis before writing any output files.

### Phase 0 — Tech stack

Before doing anything else, ask the user:

> "Before I start the conversion, a few quick questions:
> 1. **CSS approach** — are you using a framework (Tailwind, Bootstrap, custom CSS file)? Or keeping the source CSS as-is?
> 2. **JS** — any specific libraries or bundler, or just copying the source JS verbatim?
> 3. **Any conventions** — naming patterns, class naming (BEM, utility-first, etc.), or anything else I should follow throughout?"

If the user has already answered these in the conversation, extract the answers and confirm rather than asking again. Store the answers and apply them consistently across all templates and content files — don't ask again mid-conversion.

### Phase 1 — Inventory

1. List all `.html` files in the source folder
2. For each file, note:
   - Page title and purpose (home, blog list, blog post, doc, pricing, etc.)
   - Whether it has a repeating nav and footer (almost always yes)
   - Whether the `<main>` content is **prose** or **rich layout** (grids, hero, cards, sections)
   - Whether it belongs to a **collection** (multiple similar pages = collection)

Collections to look for: blog posts, documentation pages, case studies, changelog entries, team members, etc.

### Phase 2 — Plan

Before writing files, produce a concise plan:

```
BASE TEMPLATE: base.twig.html
  - nav extracted from: [filename]
  - footer extracted from: [filename]

TEMPLATES:
  - home.twig.html       ← index.html (rich layout)
  - page.twig.html       ← simple prose pages
  - full-page.twig.html  ← partners.html, agency.html (rich layout)
  - blog-post.twig.html  ← blog detail pages
  - blog-list.twig.html  ← blog index
  ... etc

CONTENT FILES:
  content/index.md           layout: home.twig.html
  content/partners.md        layout: full-page.twig.html
  content/blog/2024-01-15-my-post.md   layout: blog-post.twig.html
  ... etc

COLLECTIONS (in _config/site.yaml):
  - blog
  - documentation
  ... etc

ASSETS:
  css/ → css/
  js/  → js/
  img/ → img/  (or images/ depending on source)
```

Show the plan to the user and confirm before proceeding.

### Phase 3 — Extract base template

From the most complete HTML file (usually the homepage):

1. Extract everything from `<!DOCTYPE html>` to `<main>` (exclusive) → top of `base.twig.html`
2. Extract everything from `</main>` to `</html>` → bottom of `base.twig.html`
3. Replace the `<title>` with `{{ page.title ?? site.title }}`
4. Replace hardcoded `<meta name="description">` with `{% if page.meta_description is defined %}<meta name="description" content="{{ page.meta_description }}">{% endif %}`
5. Replace the `<main>...</main>` with:
   ```twig
   <main>
     {% block content %}{% endblock %}
   </main>
   ```
6. Fix asset paths — change relative `css/main.css` → `/css/main.css`, `js/main.js` → `/js/main.js`
7. Fix nav href links — change `index.html` → `/`, `blog.html` → `/blog/`, etc.

### Phase 4 — Create page templates

For each unique page type, create a `.twig.html` template:

**Rich layout pages** (full-page.twig.html):
```twig
{% extends "base.twig.html" %}
{% block content %}
{{ content|raw }}
{% endblock %}
```

**Prose pages** (page.twig.html):
```twig
{% extends "base.twig.html" %}
{% block content %}
<section class="page-hero" aria-labelledby="page-title">
  <div class="container">
    <div class="page-hero-content">
      <h1 id="page-title">{{ page.title }}</h1>
      {% if page.subtitle %}<p>{{ page.subtitle }}</p>{% endif %}
    </div>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="article-body" style="max-width:800px;margin:0 auto;">
      {{ content|raw }}
    </div>
  </div>
</section>
{% endblock %}
```

**Collection list pages** (blog-list.twig.html, etc.):
```twig
{% extends "base.twig.html" %}
{% block content %}
{# hero section #}
{% for item in collections.blog %}
  {# card using item.title, item.slug, item.date, item.excerpt #}
{% endfor %}
{% endblock %}
```

**Collection detail pages** (blog-post.twig.html, etc.) — use `{{ page.title }}`, `{{ page.date }}`, `{{ content|raw }}`.

### Phase 5 — Convert content to Markdown

For each page:

1. Create the `.md` file at the right path
2. Write YAML frontmatter:
   ```yaml
   ---
   title: "Page Title"
   layout: "appropriate-template.twig.html"
   meta_description: "..."
   slug: page-slug          # only if different from filename
   # collection-specific fields as needed
   ---
   ```
3. **Rich layout pages**: paste the full `<main>` inner HTML directly as the body. Remove any `<main>` wrapper tags. Keep all section/div structure intact.
4. **Prose pages**: convert the main content to Markdown where straightforward, or keep as HTML if the structure is complex.
5. **Blog/doc posts with dates**: prefix filename with date — `2024-01-15-my-post.md`
6. **Remember the textarea rule**: blank line before any `<textarea>` tag.

### Phase 6 — Copy assets

Copy all asset files verbatim:
- `source/css/` → `dest/css/`
- `source/js/` → `dest/js/`
- `source/img/` or `source/images/` → `dest/img/` (keep consistent)
- Any fonts, icons, favicons → same relative path

### Phase 7 — Config

Create or update `_config/site.yaml`:

```yaml
site:
  title: "Site Name"
  base_url: "https://example.com"

collections:
  blog:
    path: content/blog
    template: blog-post.twig.html
    listing_template: blog-list.twig.html
    listing_slug: blog
    sort: date_desc
  documentation:
    path: content/documentation
    template: doc-page.twig.html
    listing_template: documentation.twig.html
    listing_slug: documentation
    sort: title_asc
```

Only define collections that actually exist in the source site.

### Phase 8 — Summary

Report:
- Templates created (list)
- Content files created (list with layout used)
- Collections defined
- Assets copied
- Any issues or manual fixes needed (broken links, missing images, JS that references old paths)

---

## Tips

- When in doubt about whether a page needs `full-page.twig.html` vs `page.twig.html`, ask: does the `<main>` contain multiple `<section>` tags with their own `<div class="container">`? If yes → `full-page.twig.html`.
- Shared HTML fragments (cookie banners, modals, interstitial CTAs) that appear on every page belong in `base.twig.html`.
- Page-specific schema/OG meta can go in frontmatter and be output by `base.twig.html` via Twig conditionals.
- If the source site has i18n (`data-i18n` attributes, a `translations.js`), copy those JS files as-is — Miso doesn't handle i18n natively, so the client-side approach carries over.
