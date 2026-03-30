## Miso Static Site Generator

This is a PHP-based static site generator inspired by HydePHP and Jekyll. It compiles Markdown sources into HTML using Twig templates, understands directory-driven collections (with pagination), and copies asset folders like your legacy Jekyll `css/` directory.

### Features

- Markdown → HTML via `league/commonmark`
- YAML front matter with slug/date inference
- Twig layouts and partials (drop-in replacements for Liquid templates)
- Collection support with configurable pagination and permalinks
- Asset directory copying (defaults to `css/`)
- Built-in preview server (`miso run`) with optional `--watch` flag that polls for changes and rebuilds automatically — no extra dependencies required
- `--sitemap` — generates a `sitemap.xml` following the sitemaps.org spec, with `<loc>` and `<lastmod>` for every rendered page
- `--robots` — generates a `robots.txt` with a `Sitemap:` directive pointing to your sitemap
- `--llms` — generates a `llms.txt` in the [llmstxt.org](https://llmstxt.org) format, a plain-text index of all pages grouped by collection for LLM consumption
- Schema.org JSON-LD injection — drop a `*-schema.yml` file in `_config/` to activate structured data for any page or collection
- Single PHP CLI (`miso`) that you can install locally or globally

### Installation

**Prerequisites**

- PHP 8.2+ with the `intl` extension enabled
- Composer

**Global CLI install via Packagist (recommended)**

Install Miso globally so the `miso` command is available everywhere on your machine:

```bash
composer global require funky-monkey/static-site-miso
```

Ensure Composer’s global bin directory is on your `PATH` (typically `~/.composer/vendor/bin` or `~/Library/Application Support/composer/vendor/bin` on macOS):

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

Add the line above to your shell profile (for example `~/.zshrc`) so it persists.

You can now run `miso build` from any project. Use `miso --help` to see all available options.

To update to the latest version:

```bash
composer global update funky-monkey/static-site-miso
```

To uninstall:

```bash
composer global remove funky-monkey/static-site-miso
```

**Project-local install (from source)**

```bash
# Clone the repository, then from the project root:
composer install

# Build the demo site
php bin/miso build
```

The generated HTML will appear in `_site/`. Open `_site/index.html` in your browser to preview.

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
miso build --rss       # generate feed.xml (RSS 2.0)
miso build --sitemap --robots --llms --rss  # all four together
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
miso run --watch --sitemap --robots --llms --rss
```

### Generated Files

| Flag | Output file | Description |
| --- | --- | --- |
| `--sitemap` | `_site/sitemap.xml` | Standard XML sitemap (sitemaps.org spec). Includes all rendered pages with `<loc>` and, when available, `<lastmod>` from front matter `date`. Requires `site.base_url` in `_config/site.yaml`. |
| `--robots` | `_site/robots.txt` | Robots exclusion file (`User-agent: * / Allow: /`). Appends a `Sitemap:` directive when `site.base_url` is set. |
| `--llms` | `_site/llms.txt` | Plain-text site index in [llmstxt.org](https://llmstxt.org) format. Lists all pages grouped by collection with titles, URLs, and descriptions. |
| `--rss` | `_site/feed.xml` | RSS 2.0 feed of all dated collection items, newest first. Validates against the W3C Feed Validator. Requires `site.base_url` in `_config/site.yaml`. |
| `--search` | `_site/search.json` | JSON search index compatible with Fuse.js and Lunr. Each entry includes `title`, `url`, `description`, `content` (HTML stripped), and `date`. |

### Further reading

Full documentation is available in the [wiki](https://github.com/funky-monkey/static-site-miso/wiki):

- [Built-in Filters](https://github.com/funky-monkey/static-site-miso/wiki/Built-in-Filters) — all 20 Twig filters with signatures and examples
- [Writing a Plugin](https://github.com/funky-monkey/static-site-miso/wiki/Writing-a-Plugin) — add custom filters, functions, and tests via `_plugins/`
- [Schema Structured Data](https://github.com/funky-monkey/static-site-miso/wiki/Schema-Structured-Data) — JSON-LD injection via `_config/*-schema.yml`
- [Migrating to Miso](https://github.com/funky-monkey/static-site-miso/wiki/Migrating-to-Miso) — from Jekyll or Hugo, plus Liquid → Twig syntax reference
- [Claude Skills](https://github.com/funky-monkey/static-site-miso/wiki/Claude-Skills) — automate workflows with Claude Code (`miso-convert`, `miso-schema`)
