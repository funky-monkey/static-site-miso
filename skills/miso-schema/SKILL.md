---
name: miso-schema
description: >
  Generates pre-filled Schema.org JSON-LD configuration files for a Miso static site project.
  Use this skill whenever the user wants to add structured data, set up schema.org markup,
  generate *-schema.yml files, improve SEO with JSON-LD, or says things like "add structured data",
  "set up schema files", "fill in the schema templates", or "generate schema.org for my site".
  Also trigger when the user points at a Miso project and asks about rich results, Knowledge Panel,
  Google search appearance, or structured data — even if they don't say "schema" explicitly.
---

# Miso Schema

You are generating pre-filled `*-schema.yml` files for a Miso project. These files live in `_config/`
and are automatically picked up by the build — no code changes needed, just drop the files in.

## How Miso schema files work

- Each `*-schema.yml` in `_config/` is loaded at build time
- String values in the `schema:` block support Twig expressions: `{{ site.* }}`, `{{ page.* }}`, `{{ vars.* }}`
- `vars:` holds static values you set once (company name, logo URL, social links)
- `collections:` (optional) limits injection to specific collections; omit to apply to all pages
- Miso injects a `<script type="application/ld+json">` block into every matching page's `<head>`

## Available schema types

| File | Schema type | Best for |
|------|------------|---------|
| `website-schema.yml` | WebSite | Every site — establishes identity, enables Sitelinks Searchbox |
| `organization-schema.yml` | Organization | Company/product sites — Knowledge Panel, brand signals |
| `webpage-schema.yml` | WebPage | Per-page title/description/URL — always useful |
| `breadcrumblist-schema.yml` | BreadcrumbList | Sites with a primary nav in `_config/menu.yaml` |
| `software-application-schema.yml` | SoftwareApplication | SaaS, plugins, apps — shows pricing and ratings in SERPs |
| `article-schema.yml` | Article | Sites with a blog or news collection |

---

## Process

Work through these steps in order.

### Step 1 — Read the project

Read the following files to gather real values:

1. `_config/site.yaml` — extract: `site.title`, `site.base_url`, `site.description`, `site.seo.author`, `site.seo.twitter.site`, `site.seo.social_image`, `site.seo.open_graph.*`
2. `_config/menu.yaml` — check if a primary nav exists (needed for BreadcrumbList)
3. `collections:` block in `site.yaml` — identify collection names (e.g. `blog`, `docs`, `changelog`)
4. Sample 1–2 content files from each collection — check frontmatter fields like `author`, `category`, `rating`, `price`
5. Check `_config/` for any existing `*-schema.yml` files — don't overwrite them unless the user asks

### Step 2 — Determine which schemas to generate

Apply this decision logic:

| Condition | Generate |
|-----------|----------|
| Always | `website-schema.yml`, `webpage-schema.yml` |
| `_config/menu.yaml` has a `primary` menu | `breadcrumblist-schema.yml` |
| Site looks like a company/product/SaaS (has logo, org name, or social links) | `organization-schema.yml` |
| Site has a `blog`, `news`, `posts`, or `articles` collection | `article-schema.yml` |
| Site has a `software`, `plugin`, `app`, or `product` collection, OR `site.yaml` contains pricing/ratings | `software-application-schema.yml` |

Tell the user which schemas you're going to generate and why, then confirm before writing files.

### Step 3 — Fill in the values

For each schema file, replace every placeholder with the real value from the project:

- Use `site.base_url` for all URL fields
- Use `site.title` for name/publisher fields
- Use `site.seo.author` for author fields
- Use `site.seo.social_image` or a sensible path for logo fields
- Use `site.seo.twitter.site` for Twitter/X handles
- Extract social profile URLs from any `social_*` fields in `site.yaml`
- For fields you genuinely cannot derive (e.g. rating count, price), use a clearly marked placeholder like `"FILL_IN"` and note it in your summary
- Keep Twig expressions (`{{ page.title }}`, `{{ site.base_url }}`, etc.) for values that vary per page

### Step 4 — Write the files

Write each file to `_config/<name>-schema.yml`. Use the exact YAML structure from the examples below.

After writing, tell the user:
- Which files were created
- Which fields you filled from real data vs. left as `FILL_IN` placeholders
- That they need to add `{% for schema in schemas %}<script type="application/ld+json">{{ schema|raw }}</script>{% endfor %}` to their `base.twig.html` `<head>` if not already present (check first)

---

## Canonical file templates

Use these as the base for each file. Replace placeholder values with real project data.

### website-schema.yml

```yaml
vars:
  name: "SITE_TITLE"
  url: "SITE_BASE_URL"

schema:
  "@context": "https://schema.org"
  "@type": "WebSite"
  name: "{{ vars.name }}"
  url: "{{ vars.url }}"
```

If `--search` is used on this project (i.e. `search.json` exists or the user mentions client-side search), uncomment and fill the `potentialAction` block:

```yaml
  potentialAction:
    "@type": "SearchAction"
    target:
      "@type": "EntryPoint"
      urlTemplate: "{{ vars.url }}/search?q={search_term_string}"
    query-input: "required name=search_term_string"
```

### organization-schema.yml

```yaml
vars:
  name: "SITE_TITLE"
  url: "SITE_BASE_URL"
  logo: "SITE_BASE_URL/img/logo.png"
  email: "FILL_IN"
  social_twitter: "FILL_IN"
  social_linkedin: "FILL_IN"

schema:
  "@context": "https://schema.org"
  "@type": "Organization"
  name: "{{ vars.name }}"
  url: "{{ vars.url }}"
  logo:
    "@type": "ImageObject"
    url: "{{ vars.logo }}"
  contactPoint:
    "@type": "ContactPoint"
    email: "{{ vars.email }}"
    contactType: "customer support"
  sameAs:
    - "{{ vars.social_twitter }}"
    - "{{ vars.social_linkedin }}"
```

Only include `sameAs` entries for social profiles that have real URLs. Remove entries that are `FILL_IN`.

### webpage-schema.yml

```yaml
vars: {}

schema:
  "@context": "https://schema.org"
  "@type": "WebPage"
  name: "{{ page.title ?? site.title }}"
  description: "{{ page.meta_description ?? page.description ?? site.description }}"
  url: "{{ site.base_url }}{{ page.permalink }}"
  inLanguage: "en"
  isPartOf:
    "@type": "WebSite"
    url: "{{ site.base_url }}"
```

### breadcrumblist-schema.yml

```yaml
vars:
  base_url: "SITE_BASE_URL"

schema:
  "@context": "https://schema.org"
  "@type": "BreadcrumbList"
  # itemListElement is automatically built from _config/menu.yaml
```

### article-schema.yml

```yaml
collections:
  - BLOG_COLLECTION_NAME

vars:
  publisher_name: "SITE_TITLE"
  publisher_logo: "SITE_BASE_URL/img/logo.png"
  base_url: "SITE_BASE_URL"

schema:
  "@context": "https://schema.org"
  "@type": "Article"
  headline: "{{ page.title }}"
  description: "{{ page.meta_description ?? page.description ?? page.excerpt ?? '' }}"
  url: "{{ vars.base_url }}{{ page.permalink }}"
  datePublished: "{{ page.date|date('Y-m-d') }}"
  dateModified: "{{ page.date|date('Y-m-d') }}"
  author:
    "@type": "Person"
    name: "{{ page.author ?? vars.publisher_name }}"
  publisher:
    "@type": "Organization"
    name: "{{ vars.publisher_name }}"
    logo:
      "@type": "ImageObject"
      url: "{{ vars.publisher_logo }}"
  mainEntityOfPage:
    "@type": "WebPage"
    "@id": "{{ vars.base_url }}{{ page.permalink }}"
```

### software-application-schema.yml

```yaml
vars:
  name: "SITE_TITLE"
  url: "SITE_BASE_URL"
  description: "SITE_DESCRIPTION"
  operating_system: "FILL_IN"
  category: "BusinessApplication"
  price: "0"
  currency: "USD"
  rating_value: "FILL_IN"
  rating_count: "FILL_IN"

schema:
  "@context": "https://schema.org"
  "@type": "SoftwareApplication"
  name: "{{ vars.name }}"
  url: "{{ vars.url }}"
  description: "{{ vars.description }}"
  operatingSystem: "{{ vars.operating_system }}"
  applicationCategory: "{{ vars.category }}"
  offers:
    "@type": "Offer"
    price: "{{ vars.price }}"
    priceCurrency: "{{ vars.currency }}"
  aggregateRating:
    "@type": "AggregateRating"
    ratingValue: "{{ vars.rating_value }}"
    ratingCount: "{{ vars.rating_count }}"
```

---

## Tips

- Always check for existing `*-schema.yml` files before writing — ask the user before overwriting
- `webpage-schema.yml` is the most universally valuable one; always generate it
- If `site.base_url` is empty, note it prominently — all URL fields will be broken until it's set
- The `article-schema.yml` `collections:` list should match the exact collection name(s) in `site.yaml`
- Don't add `aggregateRating` to `software-application-schema.yml` if you have no real rating data — fake ratings violate Google's guidelines
