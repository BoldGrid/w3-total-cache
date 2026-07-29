# Granting cache purge access to Editors (W3 Total Cache)

By default, only Administrators (`manage_options`) can purge caches from the admin bar, post row actions, and related controls. That matches W3 Total Cache 2.10+ hardening: settings, configuration, and extensions stay admin-only.

Agencies that keep clients as **Editors** can grant purge access with WordPress filters in a small mu-plugin or theme `functions.php`. This does **not** open Performance settings, config save, or extension management.

## Recommended: separate purge-all and purge-post

```php
<?php
/**
 * Plugin Name: W3TC Editor Cache Purge
 */

// Allow Editors to empty all caches.
add_filter( 'w3tc_capability_flush_all', function () {
	return 'edit_posts';
} );

// Allow Editors to purge a specific post/page (also requires edit access to that post).
add_filter( 'w3tc_capability_flush_post', function () {
	return 'edit_posts';
} );
```

Grant only what you need. Prefer **purge-post** alone when clients should clear the page they edited, not the whole site.

## Legacy filters (still honored)

These older filters still work and feed the same gates:

- `w3tc_capability_admin_bar` — base for purge UI (historical agency snippets)
- `w3tc_capability_row_action_w3tc_flush_post` — post/page row “Purge from cache”
- `w3tc_capability_favorite_action_flush_all` — Favorites “Empty Caches”

Example equivalent to granting both purges via the old admin-bar base:

```php
add_filter( 'w3tc_capability_admin_bar', function () {
	return 'edit_posts';
} );
```

## What Editors see

- Admin bar **Performance** menu with only granted purge items (no General Settings / Extensions)
- “Purge from cache” on posts/pages they can edit (when purge-post is granted)
- Favorites “Empty Caches” when purge-all is granted

## Security notes

- Default remains `manage_options` if you add no filters.
- Filters may return any capability string (even `read`). Lowering below Editor-level caps is possible but **not recommended**.
- Nonces remain required; a nonce alone never authorizes purge.
- Purge-post also requires `edit_post` for that post ID.
- AJAX and settings endpoints stay admin-only.

## Filters reference

| Filter | Purpose | Default |
| --- | --- | --- |
| `w3tc_capability_flush_all` | Purge all caches | `manage_options` |
| `w3tc_capability_flush_post` | Purge post / current page | `manage_options` |
| `w3tc_capability_admin_bar` | Legacy base for purge caps | `manage_options` |
| `w3tc_capability_row_action_w3tc_flush_post` | Legacy row-action override | (inherits flush_post) |
| `w3tc_capability_favorite_action_flush_all` | Legacy favorites override | (inherits flush_all) |
