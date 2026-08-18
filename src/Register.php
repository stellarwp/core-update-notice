<?php declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice;

/**
 * Entry point for consuming plugins.
 */
final class Register {

	/**
	 * Hook a notice instance into wp-admin and limit its output to the consuming plugin's pages.
	 *
	 * @param callable(): bool $isPluginPage Whether the current screen belongs to the consumer.
	 */
	public static function notice( CoreUpdateNotice $notice, callable $isPluginPage ): void {
		if ( did_action( 'admin_init' ) ) {
			_doing_it_wrong(
				__METHOD__,
				'Core update notices must be registered before admin_init.',
				CoreUpdateNotice::NOTICE_VERSION
			);

			return;
		}

		$isEligible = null;

		add_filter( CoreUpdateNotice::HANDLER_WINNER_FILTER, [ $notice, 'selectWinner' ] );
		add_filter(
			CoreUpdateNotice::DISPLAY_WINNER_FILTER,
			static function ( $winner ) use ( $notice, $isPluginPage, &$isEligible ) {
				$isEligible ??= $isPluginPage();

				return $isEligible ? $notice->selectWinner( $winner ) : $winner;
			}
		);
		add_action( 'admin_init', [ $notice, 'handleDismissal' ] );
		add_action( 'admin_notices', [ $notice, 'render' ] );
	}

}
