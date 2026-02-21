# AGENTS.md

Operational guide for future coding agents working on this repository.

## Project Summary

- Project: `MarkBase` (personal wiki in PHP 8.1)
- Storage model: filesystem only (no database)
- App root: `/var/www/html/wiki`
- App entrypoint: `index.php`
- Current deployment/base path: `/wiki`

Content roots:

- Pages: `doc/` (Markdown only)
- Markdown-referenced images: `img/`
- Internal UI assets (icons/logo): `img-internal/`
- Runtime cache: `cache/`

## Product Behavior To Preserve

- Sidebar shows collapsible filesystem tree from `doc/`.
- Sidebar file labels hide `.md` suffix.
- Sidebar filter (`filter.php`) returns matching page paths from search index.
- Breadcrumbs:
  - separator only between items (no trailing slash),
  - final breadcrumb is plain text (not a link).
- Folder-aware routing:
  - Visiting a folder path tries folder `README.md` first (case-insensitive).
  - If folder has no README file, show a folder listing page (subfolders + pages).
  - Clicking Edit on that listing auto-creates folder `README.md` from displayed listing content and opens editor.
- Page addressing:
  - Main view uses `view.php?path=<relative-path-without-.md>`.
  - Root page is `index.php`.
- Linking:
  - Standard Markdown links resolve relative to current page.
  - Wiki links `[[Path/To/Page]]` and filename form `[[Page]]` are supported.
  - Ambiguous filename wiki links route to disambiguation.
- Search:
  - Full-text index in `cache/search_index.json`.
  - Results page includes snippets and highlights.
  - Backlinks are computed from index.
  - Index is regenerated automatically on save, create, move, and delete mutations.
- Action icons in breadcrumb row:
  - `New` opens modal (path + name), creates blank page, then redirects to edit mode.
  - `Move/Rename` opens modal prefilled with current path/name.
  - `Delete` opens confirmation modal; deletes current page/folder and redirects to parent path.
  - On Home view, `Move` and `Delete` actions are hidden.
- Footer:
  - Shows attribution: `Icons by Icons8` (left-aligned).
- Folder move/delete behavior:
  - Moving a folder moves the whole subtree recursively.
  - Deleting a folder deletes the whole subtree recursively.
- Caching:
  - Rendered HTML cache in `cache/html/`.
  - Sidebar tree cache in `cache/tree.json`.
  - Tree cache includes load-time missing-file validation so deleted pages disappear on reload.

## Architecture Map

- Front controller: `index.php`
  - Bootstraps dependencies and services.
  - Defines URL helpers (`pageUrl`, `editUrl`, `scriptUrl`).
  - Serves internal assets from `/wiki/img-internal/...` and markdown assets from `/wiki/img/...`.
  - Handles routes for: `view`, `search`, `filter`, `edit`, `save`, `create`, `move`, `delete`, `disambiguate`.
  - Renders templates and layout.
- Core classes (`src/`):
  - `Router.php`: route/action resolution.
  - `PageRepo.php`: filesystem operations, folder/file resolution, tree/listing APIs.
  - `MarkdownRenderer.php`: Markdown conversion + HTML cache.
  - `LinkResolver.php`: markdown/wiki link rewriting and URL generation.
  - `SearchIndexer.php`: index generation/query + backlinks.
- Templates (`templates/`):
  - `layout.php`, `sidebar.php`, `view.php`, `folder.php`, `search.php`, `edit.php`, `disambiguate.php`

## Security and Path Rules (Do Not Weaken)

- Do not allow traversal outside `doc/`, `img/`, and `img-internal/`.
- Keep all path normalization through repo helpers.
- Asset serving must validate real paths are inside the correct root (`img/` for markdown assets, `img-internal/` for UI assets).
- Internal link resolution must not permit escaping content roots.
- Avoid direct filesystem access from templates.

## Base Path Rules (`/wiki`)

- Internal URLs are generated with `/wiki` prefix.
- If deployment path changes, update `index.php`:
  - `$basePath = '/wiki';`
  - `new Router($basePath)`
  - `new LinkResolver($repo, $basePath)`
- Do not hardcode `/` links in templates. Use provided URL helpers.

## Dependency Management

- Required Composer package: `league/commonmark`
- Install/update dependencies:

```bash
cd /var/www/html/wiki
/usr/bin/composer install
```

- If `vendor/` is missing/stale, rendering and indexing can fail.

## Cache and Index Operations

- HTML cache: `cache/html/`
- Tree cache: `cache/tree.json`
- Search index: `cache/search_index.json`

Rebuild search index:

```bash
php bin/reindex.php
```

Notes:
- Search index auto-rebuilds on content mutations (`save`, `create`, `move`, `delete`).
- Manual reindex is still useful for recovery or after large external filesystem edits.

## Maintenance Workflow

1. Make focused changes (avoid broad refactors unless requested).
2. Run lint after edits:

```bash
find src templates bin -name "*.php" -print0 | xargs -0 -n 1 php -l
php -l index.php
```

3. Re-test critical flows via browser or `curl`.
4. If behavior depends on index data, run `php bin/reindex.php` and re-test.
5. Run automated end-to-end checks:

```bash
tests/full-run.sh
```

6. Keep the test runner current:
  - Update `tests/full-run.sh` whenever routes, HTML markers, mutation behavior, or folder/index semantics change.
  - Update `tests/config.sh` when new environment knobs are needed for alternate hosts/base paths/TLS settings.

## Regression Test Checklist

Core flows:

- Page view renders markdown content.
- Sidebar tree loads; deleting a page removes it from sidebar after reload.
- Sidebar filter/reset works.
- Breadcrumb links work; final crumb is plain text.
- Folder breadcrumb links:
  - open folder `README.md` if present (case-insensitive),
  - otherwise show folder listing.
- Edit/save for normal page paths.
- Edit on folder listing creates `README.md` and opens editor.
- Move/rename flow.
- Move/rename from breadcrumb modal (prefilled values).
- Delete from breadcrumb modal (confirm/cancel + redirect to parent).
- Home view hides `Move` and `Delete` breadcrumb actions.
- Folder move/delete recursion.
- Wiki links, relative markdown links, image links.
- Search results + snippets.
- Backlinks section.
- CSS loads from `/wiki/style.css`.

Useful smoke commands:

```bash
curl -sS -L -D - http://odin/wiki/ | head -n 30
curl -sS "http://odin/wiki/search.php?q=test" | head -n 40
curl -sS "http://odin/wiki/filter.php?q=test"
curl -sS "http://odin/wiki/view.php?path=<path>"
curl -sS "http://odin/wiki/edit.php?path=<path>" | head -n 40
```

Automated curl regression runner:

```bash
MARKBASE_BASE_URL="http://odin/wiki" tests/full-run.sh
```

## Enhancing the Project Safely

- Keep URL behavior backward compatible unless explicitly changing routes.
- When changing navigation/linking:
  - update header, sidebar, breadcrumbs, modal forms, and any JS fetch URLs together.
- When changing `PageRepo` resolution logic:
  - verify both file paths and folder paths (with and without `README.md`).
- When changing caching:
  - verify invalidation on create, update, delete, and move.
- When changing search/index formats:
  - keep `bin/reindex.php` and query paths in sync.
- When changing mutation handlers (`save`, `create`, `move`, `delete`):
  - preserve automatic `SearchIndexer` rebuild behavior.
- When changing UI icons/assets:
  - keep template references and internal asset serving (`img-internal/`) in sync.
  - keep `img/` reserved for markdown-referenced content images.
- When changing user-visible behavior or routing:
  - update `tests/full-run.sh` in the same change so automated regression coverage matches current behavior.

## Nginx Notes (Subpath Deployment)

Working pattern:

- `location /wiki/ { alias .../; try_files $uri $uri/ /wiki/index.php?$query_string; }`
- `location = /wiki/index.php { ... fastcgi_param SCRIPT_FILENAME .../index.php; }`
- `location /wiki/img/ { alias .../img/; }`
- `location /wiki/img-internal/ { alias .../img-internal/; }`

Pitfalls:

- Do not combine `alias` and `rewrite` in the same location for this setup.
- `snippets/fastcgi-php.conf` may include `try_files $fastcgi_script_name =404;` which can break subpath aliasing.
- If PHP returns `File not found.` or `Primary script unknown`, validate `SCRIPT_FILENAME`.
- If Nginx logs `(13: Permission denied)` under `/var/www`, verify directory traverse permissions.

## Operational Troubleshooting

- Nginx logs:
  - `/var/log/nginx/error.log`
  - `/var/log/nginx/access.log`
- If a feature appears stale:
  - clear relevant cache files in `cache/` and re-test.
- If search results/backlinks are stale:
  - run `php bin/reindex.php`.
- If internal icons/logo 404:
  - verify files exist under `img-internal/` and URLs use `/wiki/img-internal/...`.
