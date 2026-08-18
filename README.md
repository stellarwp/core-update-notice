# Core Update Notice

A WordPress admin notice prompting site administrators to update WordPress while a core update is
available. The dismissal flag is shared across every plugin that displays it, so a site running
several of them only has to dismiss it once.

## Install

The repository is private, so add it as a VCS repository:

```json
{
	"repositories": [
		{
			"type": "vcs",
			"url": "git@github.com:stellarwp/core-update-notice.git"
		}
	],
	"require": {
		"stellarwp/core-update-notice": "dev-main"
	}
}
```

## Usage

```php
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;

( new CoreUpdateNotice() )->register();
```

`register()` hooks `admin_init` for dismissal and `admin_notices` for output. The notice renders
only for users with the `update_core` capability, and only while WordPress reports an available
core upgrade.

### Translations

The default copy is English and untranslated. Pass your own strings so they land in the consuming
plugin's text domain, where its POT file will pick them up:

```php
new CoreUpdateNotice( [
	'heading' => __( 'Keep your site protected. Update to the latest version of WordPress.', 'your-plugin' ),
	'body'    => __( 'Your site is running on an outdated version of WordPress, …', 'your-plugin' ),
	'dismiss' => __( 'Dismiss this notice.', 'your-plugin' ),
] );
```

Any key you leave out falls back to the English default.

## Cross-plugin state

Consumers are expected to Strauss-prefix their copy, which rewrites namespaces and class names but
not string literals. Everything shared between plugins is therefore a string key:

| Key | Purpose |
| --- | --- |
| `nx_wp_core_update_notice_dismissed` | Site option holding the dismissal flag. Non-autoloaded. |
| `nx-dismiss-wp-core-update-notice` | Dismiss query argument and nonce action. Whichever plugin's `admin_init` runs first stores the flag. |
| `nx_wp_core_update_notice_rendered` | Global marking that a copy already rendered this request, so two plugins do not print the notice twice. |

A static property cannot serve as the render guard: each prefixed copy is a distinct class with its
own statics.

## Implementation notes

Dismissal is a nonce-protected link rather than the core dismiss button, which only removes the node
client side. The notice carries `is-dismissible` because that rule supplies the `position: relative`
and `padding-right: 48px` the absolutely positioned control needs, and core's
`makeNoticesDismissible()` skips notices that already contain a `.notice-dismiss`, so no second,
non-persisting button is appended. No script ships.

On multisite, `update_option` writes per site while `update_core` is a network capability and the
update transient is network wide, so the flag is per site.

WordPress core's own `update_nag` carries the same information on the same screens.

## Requirements

PHP 7.4+. WordPress 6.6+ for the `makeNoticesDismissible()` behavior the dismiss control relies on.
