# StellarWP Core Update Notice

[![CI](https://github.com/stellarwp/core-update-notice/workflows/CI/badge.svg)](https://github.com/stellarwp/core-update-notice/actions?query=branch%3Amain)

A WordPress admin notice prompting site administrators to update WordPress, dismissed once across
every plugin that displays it.

## Table of contents

* [Installation](#installation)
* [Notes on examples](#notes-on-examples)
* [Displaying the notice](#displaying-the-notice)
* [Translations](#translations)
* [Service containers](#service-containers)
* [Dismissal](#dismissal)
* [Choosing which plugin displays the notice](#choosing-which-plugin-displays-the-notice)
* [Shared state](#shared-state)
* [Development](#development)

## Installation

Install the package from Packagist:

```bash
composer require stellarwp/core-update-notice
```

Then prefix it with [Strauss](https://github.com/BrianHenryIE/strauss). This is required, not a
suggestion: WordPress plugins share one PHP namespace, so two plugins shipping unprefixed copies of
this package would collide on whichever autoloader registered first, and the version that won would
be whichever plugin happened to load earliest.

See the [Strauss setup docs](https://github.com/stellarwp/global-docs/blob/main/docs/strauss-setup.md).

The package is built for this. Everything it shares between plugins is a string key, which Strauss
leaves alone while it rewrites namespaces and class names, so prefixed copies still agree on one
dismissal. See [Shared state](#shared-state).

## Notes on examples

Because the package is prefixed, all examples use `StraussGeneratedNamespace` to stand in for
whatever prefix you configure.

## Displaying the notice

One call, on `init`:

```php
use StraussGeneratedNamespace\StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StraussGeneratedNamespace\StellarWP\CoreUpdateNotice\Register;

add_action( 'init', static fn() => Register::notice( new CoreUpdateNotice() ) );
```

That hooks `admin_init` for dismissal and `admin_notices` for output. The notice is shown only to
users with the `update_core` capability, and only while WordPress reports an available core upgrade.

## Translations

The default copy is English and untranslated. Pass translated copy using your plugin's text domain:

```php
Register::notice(
	new CoreUpdateNotice( [
		'heading' => __( 'Keep your site protected. Update to the latest version of WordPress.', 'my-plugin' ),
		'body'    => __( 'Your site is running on an outdated version of WordPress, …', 'my-plugin' ),
		'dismiss' => __( 'Dismiss this notice.', 'my-plugin' ),
	] )
);
```

Any key you leave out falls back to the English default. Call this on `init`, when translations are
loaded and before `admin_init` runs. Registration at or after `admin_init` is rejected because it
would miss dismissal handling and could enter the winner contest too late.

## Service containers

The package does not depend on a container. A project that uses one can let the container construct
the notice, then pass that instance to the registration boundary:

```php
use StraussGeneratedNamespace\StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StraussGeneratedNamespace\StellarWP\CoreUpdateNotice\Register;

$container->singleton( CoreUpdateNotice::class );

add_action(
	'init',
	static fn() => Register::notice( $container->get( CoreUpdateNotice::class ) )
);
```

Alternatively, construct the notice first and bind that same instance according to your container's
API before registering it:

```php
add_action(
	'init',
	static function () use ( $container, $copy ): void {
		$notice = new CoreUpdateNotice( $copy );

		$container->singleton( CoreUpdateNotice::class, $notice );

		Register::notice( $notice );
	}
);
```

Container setup remains a responsibility of the consuming project; this package only requires a
`CoreUpdateNotice` instance.

## Dismissal

Dismissals are stored by exact WordPress version. If a site running 6.7.1 dismisses an offer to
update to 6.8.2, only the notice for 6.8.2 is hidden. A later offer for 6.8.3 or 6.9.0 appears
normally. Likewise, dismissing 6.9.0 does not hide a subsequent 6.8.3 security update because they
are separate versions.

The dismiss link carries the offered version and its nonce is bound to that exact value. The handler
validates and adds the version the user saw instead of re-querying an offer that may have changed
since the page was rendered. Stale links add their version without replacing other dismissals.

## Choosing which plugin displays the notice

Every copy enters its notice into a shared WordPress filter when `Register::notice()` runs. The
filter selects the instance with the highest `CoreUpdateNotice::NOTICE_VERSION`, and that winner
alone renders and handles dismissal. Plugin load order does not decide between different versions.

So if Kadence Blocks and GiveWP both bundle the package and only GiveWP is updated to a release
with a newer notice, GiveWP's copy takes over site-wide. The stale copies stand down without
needing to be updated themselves.

Equal versions fall back to the first instance registered.

Bump `NOTICE_VERSION` whenever the notice's copy or behaviour changes. It is deliberately separate
from the package version, so a release that only touches tooling does not reshuffle which plugin
owns the notice.

## Shared state

Each plugin prefixes its own copy, which rewrites namespaces and class names but not string
literals. Everything shared between plugins is therefore a string key:

| Key | Purpose |
| --- | --- |
| `nx_wp_core_update_notice_dismissed` | Site option containing exact dismissed WordPress versions. Non-autoloaded. |
| `nx-dismiss-wp-core-update-notice` | Dismiss query argument and nonce action, bound to the rendered WordPress version. |
| `nx_wp_core_update_notice_winner` | WordPress filter that elects one notice instance for the request. |

The shared filter requires a version string and object reference at minimum, so prefixed copies can
participate without sharing PHP classes or direct globals. Copies must preserve additional fields
unchanged for forward compatibility.

These keys and the values passed through them are a cross-version compatibility contract. Do not
rename them or require the winner object to belong to a particular PHP class: another plugin may
still be running a prefixed copy of v1. The winner filter payload has this minimum shape:

```php
[
	'version' => CoreUpdateNotice::NOTICE_VERSION,
	'notice'  => $notice,
]
```

The highest version wins, and equal versions keep the first candidate. The dismiss nonce action
is always `CoreUpdateNotice::DISMISS_ACTION . ':' . $offeredVersion`; changing that formula would
prevent a different bundled copy from handling the rendered link.

Dismissal is a nonce-protected link rather than the core dismiss button, which only removes the node
client side. The notice carries `is-dismissible` because that rule supplies the `position: relative`
and `padding-right: 48px` the absolutely positioned control needs, and core's
`makeNoticesDismissible()` skips notices that already contain a `.notice-dismiss`, so no second,
non-persisting button is appended. No script ships.

On multisite, the notice intentionally appears only on individual site admin screens, not in
Network Admin. The `update_core` capability still limits it to super administrators, while
`update_option` stores each site's dismissal separately and the update transient remains network
wide.

## Development

```bash
composer install
composer check
```

| Command | What it runs |
| --- | --- |
| `composer phpcs` | PSR-12 over `src` and `tests` |
| `composer phpstan` | Level 8, `src` only |
| `composer test` | PHPUnit |
| `composer check` | All three, in that order |

The suite uses [Brain\Monkey](https://github.com/Brain-WP/BrainMonkey) to stub the WordPress
functions the package calls, so it runs with nothing but Composer installed. `exit` cannot be
intercepted in a test, so `CoreUpdateNotice::terminate()` wraps it and the suite overrides that
method.

Requires PHP 7.4+ and WordPress 6.6+.
