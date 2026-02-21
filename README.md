# MarkBase

Filesystem-backed personal wiki written in PHP, originally built with gpt-5.3-codex.

## Purpose

MarkBase is a lightweight personal wiki for managing Markdown notes without a database. Content is stored directly on disk, with search indexing and cached rendering for fast browsing. MarkBase is intended for local use or deployment on secure private servers. There is no built-in security or user profile system, so you operate it at your own risk.

## Core Functionality

- Markdown page viewing/editing/saving
- Folder-aware routing with automatic folder `README.md` handling (case-insensitive lookup)
- Sidebar tree navigation with filter
- Full-text search with snippets and backlinks
- Wiki link support (`[[Page]]`, `[[Path/To/Page]]`)
- Create / Move-Rename / Delete actions from breadcrumb modals
- Recursive folder move/delete
- Automatic search index regeneration on save/create/move/delete

## Dependencies

### Required runtime

- PHP `>= 8.1`
- Composer
- One server mode:
  - Nginx + PHP-FPM
  - Apache + PHP (mod_php or PHP-FPM)
  - PHP built-in server (local/dev)

### Required PHP extension support

- `mbstring` (required by `league/commonmark`)
- `fileinfo` (used by asset MIME detection)
- `json` (used for cache/search index payloads)

### Composer dependencies

- `league/commonmark` `^2.5`

Install PHP/composer dependencies:

```bash
cd /var/www/html/wiki
/usr/bin/composer install
composer check-platform-reqs
```

## Project Paths

- App root: `/var/www/html/wiki`
- Entrypoint: `index.php`
- Base path: `/wiki`
- Pages: `doc/`
- Markdown-referenced images: `img/`
- Internal UI assets (icons/logo): `img-internal/`
- Cache: `cache/`

## Initial Setup (all server types)

```bash
cd /var/www/html/wiki
/usr/bin/composer install
mkdir -p cache/html
```

Ensure the web user can write to:

- `doc/` (create/edit/move/delete pages)
- `cache/` (search index + tree/html cache)

## Nginx Installation

Example subpath deployment (`/wiki`):

```nginx
location /wiki/ {
    alias /var/www/html/wiki/;
    try_files $uri $uri/ /wiki/index.php?$query_string;
}

location = /wiki/index.php {
    include snippets/fastcgi-php.conf;
    fastcgi_param SCRIPT_FILENAME /var/www/html/wiki/index.php;
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
}

location /wiki/img/ {
    alias /var/www/html/wiki/img/;
}

location /wiki/img-internal/ {
    alias /var/www/html/wiki/img-internal/;
}
```

Then reload Nginx:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

## Apache Installation

Example subpath deployment (`/wiki`) using `Alias` + `FallbackResource`:

```apache
Alias /wiki /var/www/html/wiki

<Directory /var/www/html/wiki>
    Options FollowSymLinks
    AllowOverride None
    Require all granted
    DirectoryIndex index.php
    FallbackResource /wiki/index.php
</Directory>
```

Enable required modules and reload:

```bash
sudo a2enmod alias rewrite
sudo systemctl reload apache2
```

## Local Run with PHP Built-in Server

Because the app base path is `/wiki`, serve from the parent directory:

```bash
cd /var/www/html
php -S 127.0.0.1:8080
```

Open:

- `http://127.0.0.1:8080/wiki/`

## Base Path Changes

If you deploy at a different path, update in `index.php`:

- `$basePath`
- `new Router($basePath)`
- `new LinkResolver($repo, $basePath)`

## Maintenance

Lint:

```bash
find src templates bin -name "*.php" -print0 | xargs -0 -n 1 php -l
php -l index.php
```

Manual search reindex:

```bash
php bin/reindex.php
```

## Running Tests

Run the full end-to-end curl test suite:

```bash
cd /var/www/html/wiki
tests/full-run.sh
```

Run against a specific deployment URL:

```bash
MARKBASE_BASE_URL="http://your-server/wiki" tests/full-run.sh
```

Configuration defaults are in:

- `tests/config.sh`

## License

MIT (see `LICENSE.md`)
