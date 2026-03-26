# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

W3 Total Cache (W3TC) is a WordPress performance plugin providing page caching, object caching, database caching, minification, CDN integration, and related optimizations.

- **PHP compatibility**: 7.2.5–8.3 (enforced via `composer.json` platform config)
- **WordPress compatibility**: 5.3+
- **Current version**: 2.9.1 (do not bump manually — done in the build process)

## Commands

### Install dependencies
```bash
yarn run install:deps
# Runs: composer install --no-interaction --prefer-dist -o && yarn install --frozen-lockfile
```

### PHP linting
```bash
# Syntax check all PHP files (excludes vendor/node_modules)
yarn run php-lint

# WordPress coding standards check
./vendor/bin/phpcs
```

### JS linting
```bash
yarn run js-lint
yarn run js-lint-fix
```

### Running tests

Tests require a WordPress test environment. Set it up first:
```bash
bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
```

Run the full PHPUnit test suite:
```bash
./vendor/bin/phpunit
```

Run a single test file:
```bash
./vendor/bin/phpunit tests/test-generic-plugin.php
```

Run the standalone mfunc security regression test (no WordPress bootstrap needed):
```bash
php tests/test-mfunc-security.php
```

### Upgrade dependencies
```bash
yarn run upgrade:deps
# Runs: composer update --with-all-dependencies && yarn upgrade
```

## Architecture

### Naming Convention (Module_Component pattern)
All classes follow a `Module_Component` naming pattern without namespaced subdirectories. Files live flat in the plugin root. The module prefix maps to a feature area:

| Prefix | Feature |
|---|---|
| `PgCache_` | Page caching |
| `ObjectCache_` | WordPress object cache |
| `DbCache_` | Database query caching |
| `Minify_` | CSS/JS minification |
| `Cdn_` / `CdnEngine_` | CDN push/pull |
| `Cdnfsd_` | CDN full-site delivery |
| `BrowserCache_` | Browser caching / HTTP headers |
| `Extension_*` | Optional extensions (CloudFlare, NewRelic, AlwaysCached, etc.) |
| `UserExperience_` | Lazy load, defer scripts, preload, etc. |
| `Generic_` | Core plugin infrastructure |
| `Util_` | Utility helpers |

Component suffixes indicate role:
- `_Plugin` — registers WordPress hooks (the "controller")
- `_Plugin_Admin` — admin-only hooks
- `_Core` — runtime/frontend logic
- `_Environment` — writes server config (`.htaccess`, `nginx.conf`)
- `_Page` / `_Page_View` — admin settings pages and their view templates
- `_ConfigLabels` — human-readable config key labels

### Bootstrap Flow
1. `w3-total-cache.php` — plugin entry point; loads `w3-total-cache-api.php` (constants) and `Root_Loader.php`
2. `Root_Loader` — reads `Config`, conditionally instantiates each `*_Plugin` class based on enabled features
3. Each `*_Plugin` calls `run()` to register hooks; admin plugins are registered only in the admin context

### Dispatcher (Service Locator)
`Dispatcher` (`Dispatcher.php`) is a singleton-style service locator. Use `Dispatcher::component('ClassName')` to get a shared instance, and `Dispatcher::config()` for the active configuration object.

### Configuration
`Config` (`Config.php`) reads/writes plugin settings stored in `wp-content/w3tc-config/master.php` (serialized PHP array). `ConfigKeys.php` lists all known keys. Use `$config->get_boolean()`, `$config->get_string()`, etc. to read values.

### Dynamic Content (mfunc/mclude)
`PgCache_ContentGrabber` handles serving pages from the page cache. It supports `<!-- mfunc {token} -->` and `<!-- mclude {token} -->` HTML comment tags for injecting dynamic content into cached pages. The security token is stored as `W3TC_DYNAMIC_SECURITY`. `Generic_Plugin` sanitizes user-submitted content to strip any mfunc/mclude tags before they reach the cache.

### Coding Standards
- Follow WordPress Coding Standards (`phpcs.xml` configures `WordPress`, `WordPress-Core`, `WordPress-Docs`, `WordPress-Extra`)
- Indentation: 4-space tabs (not spaces)
- Strings: single quotes unless variable interpolation is needed
- Prefix all global namespace function calls with a backslash (e.g., `\strlen()`)
- Opening parenthesis of multi-line function calls must be the last content on the line
- Do not make unrelated coding-standards fixes in changed files
- Add `@since X.X.X` to all new doc blocks (version is updated in the build process)

### Contribution Notes
- Do not update POT files, `readme.txt`, or the plugin version — all handled in the build process
- All changes require a pull request referencing a GitHub or JIRA issue
- `phpcs.xml` excludes `vendor/`, `lib/`, `node_modules/`, `qa/`, and `tests/`
- PHPUnit test bootstrap expects `WP_TESTS_DIR` env var pointing to WordPress test library (default: `/tmp/wordpress-tests-lib`)

### Security Reporting
Report vulnerabilities via [Patchstack VDP](https://patchstack.com/database/vdp/d5047161-3e39-4462-9250-1b04385021dd).
