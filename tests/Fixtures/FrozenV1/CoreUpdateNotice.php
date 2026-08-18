<?php declare(strict_types=1);

namespace FrozenV1Plugin\StellarWP\CoreUpdateNotice;

/**
 * Frozen snapshot of the v1 winner protocol as it appears in a separately prefixed plugin.
 *
 * Do not update this fixture when production code changes: it exists to prove that newer copies
 * remain compatible with plugins that still bundle v1.
 */
final class CoreUpdateNotice {

	public const NOTICE_VERSION = '1.0.0';

	/**
	 * @param mixed $winner
	 *
	 * @return array{version: string, notice: object, ...}
	 */
	public function selectWinner( $winner ): array {
		if (
			is_array( $winner )
			&& isset( $winner['version'], $winner['notice'] )
			&& is_string( $winner['version'] )
			&& $winner['version'] !== ''
			&& is_object( $winner['notice'] )
			&& version_compare( self::NOTICE_VERSION, $winner['version'], '<=' )
		) {
			return $winner;
		}

		return [
			'version' => self::NOTICE_VERSION,
			'notice'  => $this,
		];
	}

}
