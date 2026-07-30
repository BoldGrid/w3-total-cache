# Granting cache purge access to Editors (W3 Total Cache)

By default, only Administrators (`manage_options`) can purge caches from the admin bar, post row actions, and related controls. Settings, configuration, and extensions stay admin-only.

Agencies that keep clients as **Editors** can grant purge access with WordPress filters in a small mu-plugin or theme `functions.php`. This does **not** open Performance settings, config save, or extension management.

**Existing agency filters keep working** — if you already use `w3tc_capability_admin_bar` (and/or the row/favorite filters) to grant `edit_posts`, you do not need to change your code for this restore.

## Recommended (new installs)

Grant both purge-all and purge-post so the admin-bar Performance menu and post actions stay consistent:

```php
<?php
/**
 * Plugin Name: W3TC Editor Cache Purge
 */

add_filter( 'w3tc_capability_flush_all', function () {
	return 'edit_posts';
} );

add_filter( 'w3tc_capability_flush_post', function () {
	return 'edit_posts';
} );
```

## Recommended (legacy — no code change needed)

One historical filter restores both purge-all and purge-post:

```php
add_filter( 'w3tc_capability_admin_bar', function () {
	return 'edit_posts';
} );
```

Optional companion for row actions (also still honored on its own):

```php
add_filter( 'w3tc_capability_row_action_w3tc_flush_post', function () {
	return 'edit_posts';
} );
```

## Least privilege (optional)

You may grant only one surface:

- **Purge-post only** — clients clear the page they edited, not the whole site:

```php
add_filter( 'w3tc_capability_flush_post', function () {
	return 'edit_posts';
} );
```

- **Purge-all only** — empty all caches; no per-post row action:

```php
add_filter( 'w3tc_capability_flush_all', function () {
	return 'edit_posts';
} );
```

## What Editors see

- Admin bar **Performance** menu with only granted purge items (no General Settings / Extensions)
- “Purge from cache” on posts/pages they can edit (when purge-post is granted)
- Favorites “Empty Caches” when purge-all is granted

## Notes

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
| `w3tc_capability_admin_bar` | Legacy base for both purge caps (no change needed if already in use) | `manage_options` |
| `w3tc_capability_row_action_w3tc_flush_post` | Legacy row-action override | (inherits flush_post chain) |
| `w3tc_capability_favorite_action_flush_all` | Legacy favorites override | (inherits flush_all chain) |
