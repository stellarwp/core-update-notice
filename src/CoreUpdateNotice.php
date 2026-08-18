<?php declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice;

/**
 * Prompts site administrators to update WordPress while the install is behind the latest release.
 *
 * Consumers Strauss-prefix their own copy of this class, so nothing here may rely on class names,
 * namespaces or constants being shared between plugins. The cross-plugin state is carried entirely
 * by string keys, which prefixing does not rewrite.
 */
class CoreUpdateNotice {

	/**
	 * Dismissal flag shared with the other plugins that display this notice, so a site running
	 * more than one of them only has to dismiss it once. Do not prefix it per plugin.
	 *
	 * Holds the WordPress version the notice was dismissed against, not a boolean: the notice has
	 * to come back when a later release leaves the site outdated again.
	 */
	public const DISMISSED_OPTION = 'nx_wp_core_update_notice_dismissed';

	/**
	 * The query argument and nonce action carried by the dismiss link. Shared so the elected copy
	 * can handle a link rendered by any plugin carrying the package.
	 */
	public const DISMISS_ACTION = 'nx-dismiss-wp-core-update-notice';

	/**
	 * The version of the notice itself, independent of the package version. Bump it whenever the
	 * notice's copy or behaviour changes.
	 *
	 * When several plugins bundle this package, the highest version registered on the request is
	 * the one that renders. A plugin that has been updated therefore controls the notice for the
	 * whole site, without waiting for the others to catch up.
	 */
	public const NOTICE_VERSION = '1.0.0';

	/**
	 * Shared filter that elects one notice instance across every prefixed copy of the package.
	 */
	public const WINNER_FILTER = 'nx_wp_core_update_notice_winner';

	/**
	 * The capability a user needs to see the notice and to dismiss it.
	 */
	private const CAPABILITY = 'update_core';

	/**
	 * @var array{heading: string, body: string, dismiss: string}
	 */
	private array $copy;

	/**
	 * @param array{
	 *     heading?: string,
	 *     body?: string,
	 *     dismiss?: string
	 * } $copy Optional translated copy. Supply this from the consuming plugin so translations use
	 *         its text domain. Missing keys use the English defaults.
	 */
	public function __construct(array $copy = []) {
		$defaults = [
			'heading' => 'Keep your site protected. Update to the latest version of WordPress.',
			'body'    => 'Your site is running on an outdated version of WordPress, which can leave it'
				. ' vulnerable to security issues. To decrease your risk of exposure, please update'
				. ' your WordPress install to the latest version.',
			'dismiss' => 'Dismiss this notice.',
		];

		$this->copy = array_merge( $defaults, array_filter( $copy, 'is_string' ) );
	}

	/**
	 * Store the shared dismissal flag when the notice's dismiss control is used.
	 *
	 * @hook admin_init
	 */
	public function handleDismissal(): void {
		$target = $_GET[ self::DISMISS_ACTION ] ?? null;

		if ( ! is_string( $target ) || $target === '' ) {
			return;
		}

		if ( ! $this->isWinner() ) {
			return;
		}

		check_admin_referer( self::DISMISS_ACTION . ':' . $target );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		update_option( self::DISMISSED_OPTION, $target, false );

		wp_safe_redirect( remove_query_arg( [ self::DISMISS_ACTION, '_wpnonce' ] ) );

		$this->terminate();
	}

	/**
	 * Render the notice, at most once per request across every plugin that registers it.
	 *
	 * @hook admin_notices
	 */
	public function render(): void {
		if ( ! $this->isWinner() ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$offered = $this->displayTarget();

		if ( $offered === null ) {
			return;
		}

		/*
		 * The dismiss control is a link so the shared flag can be stored server side, without a
		 * script. "is-dismissible" supplies the positioning context the control needs, and core's
		 * makeNoticesDismissible() skips notices that already carry a .notice-dismiss, so it does
		 * not append a second, non-persisting button.
		 */
		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%1$s</strong></p><p>%2$s</p>'
			. '<a href="%3$s" class="notice-dismiss" style="text-decoration:none;">'
			. '<span class="screen-reader-text">%4$s</span></a></div>',
			esc_html( $this->copy['heading'] ),
			esc_html( $this->copy['body'] ),
			esc_url( $this->getDismissUrl( $offered ) ),
			esc_html( $this->copy['dismiss'] )
		);
	}

	/**
	 * @return bool True while a core update is available that has not already been dismissed.
	 */
	public function shouldDisplay(): bool {
		return $this->displayTarget() !== null;
	}

	/**
	 * Keep the current winner unless this notice has a higher version.
	 *
	 * @param mixed $winner
	 *
	 * @return array{version: string, notice: object, ...}
	 */
	public function selectWinner($winner): array {
		if (
			is_array( $winner )
			&& isset( $winner['version'], $winner['notice'] )
			&& is_string( $winner['version'] )
			&& $winner['version'] !== ''
			&& is_object( $winner['notice'] )
			&& version_compare( static::NOTICE_VERSION, $winner['version'], '<=' )
		) {
			return $winner;
		}

		return [
			'version' => static::NOTICE_VERSION,
			'notice'  => $this,
		];
	}

	/**
	 * Whether the shared winner filter selected this notice instance.
	 */
	public function isWinner(): bool {
		$winner = apply_filters( self::WINNER_FILTER, null );

		return is_array( $winner )
			&& ($winner['version'] ?? null) === static::NOTICE_VERSION
			&& ($winner['notice'] ?? null) === $this;
	}

	/**
	 * The offered version to display, or null when there is no undismissed update.
	 */
	private function displayTarget(): ?string {
		$offered = $this->offeredVersion();

		if ( $offered === null ) {
			return null;
		}

		return $this->isDismissedFor( $offered ) ? null : $offered;
	}

	/**
	 * End the request after redirecting. Overridable so the dismissal path can be tested.
	 */
	protected function terminate(): void {
		exit;
	}

	/**
	 * Whether the offered version has already been dismissed.
	 *
	 * The stored value is the WordPress version the notice was last dismissed against, so a later
	 * release brings it back rather than silencing it forever.
	 */
	private function isDismissedFor(string $offered): bool {
		$stored = get_option( self::DISMISSED_OPTION, '' );

		if ( is_string( $stored ) && $stored !== '' && $stored !== '1' ) {
			return version_compare( $stored, $offered, '>=' );
		}

		if ( ! empty( $stored ) ) {
			/*
			 * A boolean flag written before dismissal was versioned. Adopt the current offer so the
			 * dismissal is honoured now and re-arms on the next release, rather than either
			 * reappearing immediately or never showing again.
			 */
			update_option( self::DISMISSED_OPTION, $offered, false );

			return true;
		}

		return false;
	}

	/**
	 * The WordPress version currently being offered, or null when the install is up to date.
	 */
	private function offeredVersion(): ?string {
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$updates = get_core_updates( [ 'dismissed' => false ] );

		if ( ! is_array( $updates ) || $updates === [] ) {
			return null;
		}

		$update = $updates[0];

		if ( ! is_object( $update ) || ! isset( $update->response ) || $update->response !== 'upgrade' ) {
			return null;
		}

		// The offered release, not the installed one: get_core_updates() names it "current".
		$version = isset( $update->current ) ? (string) $update->current : '';

		return $version !== '' ? $version : null;
	}

	/**
	 * The nonce-protected link that stores the shared dismissal flag.
	 */
	private function getDismissUrl(string $offered): string {
		return (string) wp_nonce_url(
			add_query_arg( self::DISMISS_ACTION, $offered ),
			self::DISMISS_ACTION . ':' . $offered
		);
	}

}
