## Miso Static Site Generator

This is a PHP-based static site generator inspired by HydePHP and Jekyll. It compiles Markdown sources into HTML using Twig templates, understands directory-driven collections (with pagination), and copies asset folders like your legacy Jekyll `css/` directory.

### Features

- Markdown → HTML via `league/commonmark`
- YAML front matter with slug/date inference
- Twig layouts and partials (drop-in replacements for Liquid templates)
- Collection support with configurable pagination and permalinks
- Asset directory copying (defaults to `css/`)
- Single PHP CLI (`miso`) that you can install locally or globally

### Installation

**Prerequisites**

- PHP 8.2+ with the `intl` extension enabled
- Composer

**Project-local install**

```bash
# Clone or copy this repository, then from the project root:
composer install

# Build the demo site
php bin/miso build
```

The generated HTML will appear in `_site/`. Open `_site/index.html` in your browser to preview.

**Global CLI install (macOS-friendly)**

1. From the project root, run:

   ```bash
   composer global config repositories.miso path "$(pwd)"
   composer global require miso/static-site-generator:dev-main
   ```

   This tells Composer to install the local project into your global Composer directories, exposing the `miso` executable.

2. Ensure Composer’s global bin directory is on your `PATH` (typically `~/.composer/vendor/bin` or `~/Library/Application Support/composer/vendor/bin` on macOS):

   ```bash
   export PATH="$HOME/.composer/vendor/bin:$PATH"
   ```

   Add the line above to your shell profile (for example `~/.zshrc`) so it persists.

3. You can now run `miso build` from any project that follows the expected layout. Use `miso --help` to see available options.

To uninstall later:

```bash
composer global remove miso/static-site-generator
composer global config --unset repositories.miso
```

### Project Layout

```
content/           # Markdown sources (subdirectories become collections)
css/               # Legacy CSS or other assets copied verbatim
templates/         # Twig templates (override defaults as needed)
_config/site.yaml  # Site metadata, SEO defaults, and collection settings
_config/menu.yaml  # Optional navigation structure
_site/             # Build output (generated)
```

### Configuration

`_config/site.yaml` controls site metadata, SEO defaults, paths, and collection behaviour:

```yaml
site:
  title: "My Site"
  description: "Static site powered by Miso"
  base_url: "https://example.com"
  seo:
    author: "Jane Doe"
    default_keywords:
      - "docs"
      - "product"
    canonical: "https://example.com"
    social_image: "/media/social-card.png"
    open_graph:
      title: "Docs Overview"
      description: "Learn everything about our product"
      image: "https://example.com/media/og-card.png"
      url: "https://example.com/docs/"
      type: "website"
      locale: "en_US"
      site_name: "Example Docs"
    twitter:
      card: "summary_large_image"
      site: "@example"
      creator: "@janedoe"
      title: "Example Docs"
      description: "Documentation & tutorials"
      image: "https://example.com/media/twitter-card.png"
paths:
  content: "content"
  templates: "templates"
  output: "_site"
  assets:
    - "css"
collections:
  blog:
    path: "content/blog"
    pagination:
      per_page: 5
    layout: "collection-item.twig.html"
    list_layout: "collection.twig.html"
    permalink: "/blog/{slug}/"
    list_permalink: "/blog/"
```

Add a new collection by creating another subdirectory in `content/` (for example `content/projects/`) and mirroring it in the `collections:` section if you need custom layouts or pagination settings. Provide a `_config/menu.yaml` to manage navigation; by default `menus.primary` is rendered as the main menu.

### Usage

Create a new project anywhere with:

```bash
miso new my-site
```

If you run `miso new` inside an empty directory, scaffolding is placed in the current folder. Add `--force` to overwrite non-empty directories.

Then run the build from your project root:

```bash
miso build
```

This clears `_site/`, renders pages into HTML, generates paginated collection listings, and copies asset directories.

Pass optional flags to generate additional output files:

```bash
miso build --sitemap   # generate sitemap.xml
miso build --robots    # generate robots.txt
miso build --llms      # generate llms.txt
miso build --sitemap --robots --llms  # all three together
```

Override defaults when needed:

```bash
miso build --root=/path/to/project --config=/path/to/custom-site.yaml
```

For a quick preview server (auto-build + PHP’s built-in server):

```bash
miso run
# or specify port/host
miso run --port=9000 --host=0.0.0.0
```

Add `--watch` to automatically rebuild whenever source files change:

```bash
miso run --watch
# combine with port/host as needed
miso run --watch --port=9000 --host=0.0.0.0
```

With `--watch` enabled, Miso polls for changes every 0.5 seconds across the following directories:

| Directory | File types watched |
| --- | --- |
| `content/` | `.md`, `.markdown` |
| `templates/` | `.html`, `.twig.html` |
| `css/` | `.css` |
| `_config/` | `.yaml` |

When a change is detected, the full site is rebuilt in place and the running PHP server picks up the new files immediately. A timestamp is printed for each rebuild cycle:

```
[14:23:01] Changes detected. Rebuilding...
[14:23:01] Build complete.
```

> **Note:** `--watch` uses polling, not filesystem events, so it works across all platforms and network file systems without any additional dependencies. There is no browser live-reload; refresh the page manually after a rebuild.

The `--sitemap`, `--robots`, and `--llms` flags also work with `miso run` and `miso run --watch`:

```bash
miso run --watch --sitemap --robots --llms
```

### Generated Files

| Flag | Output file | Description |
| --- | --- | --- |
| `--sitemap` | `_site/sitemap.xml` | Standard XML sitemap (sitemaps.org spec). Includes all rendered pages with `<loc>` and, when available, `<lastmod>` from front matter `date`. Requires `site.base_url` in `_config/site.yaml`. |
| `--robots` | `_site/robots.txt` | Robots exclusion file (`User-agent: * / Allow: /`). Appends a `Sitemap:` directive when `site.base_url` is set. |
| `--llms` | `_site/llms.txt` | Plain-text site index in [llmstxt.org](https://llmstxt.org) format. Lists all pages grouped by collection with titles, URLs, and descriptions. |

### Liquid → Twig Quick Guide

| Liquid Pattern | Twig Equivalent |
| --- | --- |
| `{{ page.title }}` | `{{ page.title }}` (same output braces; filters use `|`, e.g., `{{ page.date \| date("F j, Y") }}`) |
| `{% if page.draft %}...{% endif %}` | `{% if page.draft %}...{% endif %}` (control structures share the `{% %}` delimiters) |
| `{% assign foo = "bar" %}` | `{% set foo = 'bar' %}` (capture blocks become `{% set foo %}...{% endset %}`) |
| `{% include 'header.html' foo='bar' %}` | `{% include "partials/header.twig.html" with { foo: 'bar' } %}` (omit `with` if no variables are passed) |
| `{{ site.time \| date: "%Y" }}` | `{{ "now" \| date("Y") }}` or `{{ page.date \| date("Y") }}` |
| Inside `{% for post in site.posts %}` use `{{ forloop.index }}` | Inside `{% for post in collection %}` use `{{ loop.index }}`, `loop.first`, `loop.last`, etc. |
| `{% capture %}...{% endcapture %}` | `{% set foo %}...{% endset %}` |
| `page.title`, `page.slug`, `page.collection`, `site.title` | same property names in Twig; site-level data accessible via `site.*` |

> Twig uses `{% ... %}` for statements (loops, conditionals, includes, `set`, macros) and `{{ ... }}` for output—consistent with Liquid’s delimiter style, so most templates feel familiar after swapping syntax.

### Migrating a Jekyll Theme

1. Copy your Liquid layouts/partials into `templates/` and translate Liquid syntax to Twig (`{{ ... }}` carries over, `{% ... %}` blocks are similar).
2. Move your `_sass`, `assets`, or theme CSS into the `css/` directory (or list additional asset folders in `_config/site.yaml`).
3. Adjust template asset paths (for example `<link rel="stylesheet" href="/css/theme.css">`).
4. Jekyll’s `_layouts/` → `templates/` (rename to `.twig.html`), and `_includes/` → `templates/partials/` (reference via `{% include "partials/name.twig.html" %}`).

### Migrating a Hugo Theme

1. Inventory the theme’s `layouts/`, `assets/` (or `static/`), and any custom shortcodes/partials. Note navigation/menu definitions (usually in `config.toml`) and content types.
2. Copy key Hugo layouts into `templates/` and convert Go template syntax (`{{ .Title }}`, `{{ partial }}`) to Twig (`{{ page.title }}`, `{% include %}`). Use `.twig.html` filenames to match Miso’s convention.
3. Drop compiled CSS/JS/images from Hugo’s `static/` (or the output of its asset pipeline) into `css/` or other asset folders listed in `_config/site.yaml`.
4. Translate Hugo menu definitions into `_config/menu.yaml` (the new `menus.primary` renderer handles nested menus automatically).
5. Map Hugo variables to Miso equivalents when rewriting layouts:
   - `.Site.Title` → `site.title`
   - `.Content` → `content|raw`
   - `.Params.foo` → `page.foo`
   - `.Paginator` → `pagination`
6. Replace shortcodes/partials with Twig includes or custom filters. Any Hugo-specific Markdown shortcodes will need manual conversion.
7. Run `miso build` and `miso run` iteratively to confirm the pages match the original Hugo output.

### Next steps

- Register Twig filters/functions to mimic Liquid tags you depend on.
- Implement extra build tasks (RSS feeds, sitemaps, search indexes).
- Extend the CLI with additional commands or build hooks as your project grows.
