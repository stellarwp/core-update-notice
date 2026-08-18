# StellarWP Core Update Notice

[![CI](https://github.com/stellarwp/core-update-notice/workflows/CI/badge.svg)](https://github.com/stellarwp/core-update-notice/actions?query=branch%3Amain)

A WordPress admin notice prompting site administrators to update WordPress, dismissed once across
every plugin that displays it.

## Table of contents

* [Installation](#installation)
* [Notes on examples](#notes-on-examples)
* [Displaying the notice](#displaying-the-notice)
* [Translations](#translations)
* [Configuration](#configuration)
  * [Service containers](#service-containers)
* [Dismissal](#dismissal)
* [Choosing which plugin displays the notice](#choosing-which-plugin-displays-the-notice)
* [Shared state](#shared-state)
* [Development](#development)

## Installation

The repository is private, so add it as a VCS repository and require it:

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

One call, on `init` or later:

```php
use StraussGeneratedNamespace\StellarWP\CoreUpdateNotice\Register;

add_action( 'init', static fn() => Register::notice() );
```

That hooks `admin_init` for dismissal, and both `admin_notices` and `network_admin_notices` for
output. The notice is shown only to
users with the `update_core` capability, and only while WordPress reports an available core upgrade.

## Translations

The default copy is English and untranslated. Pass your own strings so they are extracted into your
plugin's text domain:

```php
Register::notice( [
	'heading' => __( 'Keep your site protected. Update to the latest version of WordPress.', 'my-plugin' ),
	'body'    => __( 'Your site is running on an outdated version of WordPress, …', 'my-plugin' ),
	'dismiss' => __( 'Dismiss this notice.', 'my-plugin' ),
] );
```

Any key you leave out falls back to the English default. Call this at `init` or later, or the
translations will not be loaded yet.

## Configuration

No configuration is required.

### Service containers

It is not required to use a service container with this library, however if you are using one and
want it to fit within your system, you can connect your container, which **must** implement the
`StellarWP\ContainerContract\ContainerInterface` interface.

```php
use StraussGeneratedNamespace\StellarWP\CoreUpdateNotice\Config;
use StraussGeneratedNamespace\StellarWP\CoreUpdateNotice\Register;

Config::setContainer( $container );

Register::notice();
```

`Register::notice()` then binds the notice on your container as a singleton, so the rest of your
plugin can resolve the same instance:

```php
$container->get( StraussGeneratedNamespace\StellarWP\CoreUpdateNotice\CoreUpdateNotice::class );
```

The binding is unconditional and overwrites any earlier one. It is deliberately not guarded behind
`has()`: an auto-wiring container such as di52 answers `has()` true for any instantiable class, so a
guarded binding would never run and the container would hand back an auto-wired copy carrying none
of your strings.

## Dismissal

Dismissal records the specific offer it applied to, as a set keyed `"{version}|{locale}"` — the
same shape WordPress uses for its own `dismissed_update_core`. Dismiss 6.9 and 6.9 stays quiet,
while a later 6.8.1 security release still gets through. A single stored version could not express
that, and would have silenced exactly the releases this notice exists to surface.

The offer key travels in the dismiss link, so the dismissal records what the user was actually
shown rather than whatever the update transient holds by the time the click lands.

Storage uses the site option API, so one dismissal covers a whole multisite network instead of
being repeated on every subsite. On single site that falls through to `update_option()` with
autoload disabled.

Nothing is written while merely deciding whether to display; only the dismiss handler writes.

## Choosing which plugin displays the notice

Every copy registers `CoreUpdateNotice::NOTICE_VERSION` when `register()` runs, and only the
highest version registered on the request renders. Plugin load order does not decide it.

So if Kadence Blocks and GiveWP both bundle the package and only GiveWP is updated to a release
with a newer notice, GiveWP's copy takes over site-wide.

This works between copies at this release or later. A copy predating it does not read the version
global at all and renders on the guard alone, so against those the winner is still whichever
`admin_notices` callback runs first.

Equal versions fall back to whichever renders first, which the render guard settles.

Call `Register::notice()` on `init` or `admin_init`, never from inside an `admin_notices` callback.
A copy registering once that hook is already running claims the contest without its own callback
being reached, which suppresses the notice for that request.

Bump `NOTICE_VERSION` whenever the notice's copy or behaviour changes. It is deliberately separate
from the package version, so a release that only touches tooling does not reshuffle which plugin
owns the notice.

## Shared state

Each plugin prefixes its own copy, which rewrites namespaces and class names but not string
literals. Everything shared between plugins is therefore a string key:

| Key | Purpose |
| --- | --- |
| `nx_wp_core_update_notice_dismissed` | Network-wide site option holding the set of dismissed offers, keyed `"{version}\|{locale}"`. Non-autoloaded. |
| `nx-dismiss-wp-core-update-notice` | Dismiss query argument and nonce action. Whichever plugin's `admin_init` runs first stores the flag. |
| `nx_wp_core_update_notice_rendered` | Global marking that a copy already rendered this request, so two plugins do not print the notice twice. |
| `nx_wp_core_update_notice_version` | Global holding the highest `NOTICE_VERSION` registered this request. Copies below it do not render. |

A static property cannot serve as the render guard: each prefixed copy is a distinct class with its
own statics.

Dismissal is a nonce-protected link rather than the core dismiss button, which only removes the node
client side. The notice carries `is-dismissible` because that rule supplies the `position: relative`
and `padding-right: 48px` the absolutely positioned control needs, and core's
`makeNoticesDismissible()` skips notices that already contain a `.notice-dismiss`, so no second,
non-persisting button is appended. No script ships.

On multisite, `update_core` is a super-admin capability and the update transient is network wide,
so the dismissal is stored network wide to match. The notice is hooked into the network admin as
well, which is where `update-core.php` lives for a super admin.

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
