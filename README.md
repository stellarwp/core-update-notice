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

> We _actually_ recommend that this library gets included in your project
> using [Strauss](https://github.com/BrianHenryIE/strauss).
>
> Luckily, adding Strauss to your `composer.json` is only slightly more complicated than adding a
> typical dependency, so checkout
> our [strauss docs](https://github.com/stellarwp/global-docs/blob/main/docs/strauss-setup.md).

## Notes on examples

Since the recommendation is to use Strauss to prefix this library's namespaces, all examples will be
using the `Boomshakalaka` namespace prefix.

## Displaying the notice

One call, on `init` or later:

```php
use Boomshakalaka\StellarWP\CoreUpdateNotice\Register;

add_action( 'init', function () {
	Register::notice();
} );
```

That hooks `admin_init` for dismissal and `admin_notices` for output. The notice is shown only to
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
use Boomshakalaka\StellarWP\CoreUpdateNotice\Config;
use Boomshakalaka\StellarWP\CoreUpdateNotice\Register;

Config::setContainer( $container );

Register::notice();
```

`Register::notice()` then binds the notice on your container as a singleton, so the rest of your
plugin can resolve the same instance:

```php
$container->get( Boomshakalaka\StellarWP\CoreUpdateNotice\CoreUpdateNotice::class );
```

The binding is unconditional and overwrites any earlier one. It is deliberately not guarded behind
`has()`: an auto-wiring container such as di52 answers `has()` true for any instantiable class, so a
guarded binding would never run and the container would hand back an auto-wired copy carrying none
of your strings.

## Shared state

Consumers prefix their own copy, which rewrites namespaces and class names but not string literals.
Everything shared between plugins is therefore a string key:

| Key | Purpose |
| --- | --- |
| `nx_wp_core_update_notice_dismissed` | Site option holding the dismissal flag. Non-autoloaded. |
| `nx-dismiss-wp-core-update-notice` | Dismiss query argument and nonce action. Whichever plugin's `admin_init` runs first stores the flag. |
| `nx_wp_core_update_notice_rendered` | Global marking that a copy already rendered this request, so two plugins do not print the notice twice. |

A static property cannot serve as the render guard: each prefixed copy is a distinct class with its
own statics.

Dismissal is a nonce-protected link rather than the core dismiss button, which only removes the node
client side. The notice carries `is-dismissible` because that rule supplies the `position: relative`
and `padding-right: 48px` the absolutely positioned control needs, and core's
`makeNoticesDismissible()` skips notices that already contain a `.notice-dismiss`, so no second,
non-persisting button is appended. No script ships.

On multisite, `update_option` writes per site while `update_core` is a network capability and the
update transient is network wide, so the flag is per site.

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
