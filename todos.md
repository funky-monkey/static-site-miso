# Todos

## Plugin system & core filters

- [x] Design plugin architecture (Twig AbstractExtension + _plugins/ auto-loader)
- [x] Implement `src/Twig/CoreFiltersExtension.php` — 20 built-in filters
- [x] Implement `src/Twig/PluginLoader.php` — auto-loads `_plugins/*.php`
- [x] Register CoreFiltersExtension + PluginLoader in `bin/miso` `initializeGenerator()`
- [x] Add `_plugins/` to watch dirs in `miso run --watch`
- [x] Add example plugin to `skeleton/_plugins/example.php`
- [x] Document plugin system in README

## Open issues (see GitHub)

- [ ] #2 — Custom Twig filters (covered by plugin system above)
- [x] #3 — RSS feed generation (`--rss` flag)
- [x] #4 — Search index generation (`--search` flag)
- [ ] #5 — Build hooks / lifecycle callbacks
