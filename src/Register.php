<?php declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice;

/**
 * Entry point for consuming plugins.
 */
final class Register {

	/**
	 * Hook a notice instance into wp-admin and enter it into the version contest.
	 */
	public static function notice(CoreUpdateNotice $notice): void {
		if ( did_action( 'admin_init' ) ) {
			_doing_it_wrong(
				__METHOD__,
				'Core update notices must be registered before admin_init.',
				CoreUpdateNotice::NOTICE_VERSION
			);

			return;
		}

		add_filter( CoreUpdateNotice::WINNER_FILTER, [ $notice, 'selectWinner' ] );
		add_action( 'admin_init', [ $notice, 'handleDismissal' ] );
		add_action( 'admin_notices', [ $notice, 'render' ] );
	}

}
