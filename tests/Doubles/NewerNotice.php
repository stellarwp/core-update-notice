<?php declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests\Doubles;

use StellarWP\CoreUpdateNotice\CoreUpdateNotice;

/**
 * Stands in for a plugin bundling a newer release of the package than its neighbours.
 */
final class NewerNotice extends CoreUpdateNotice {

	public const NOTICE_VERSION = '99.0.0';

	public bool $terminated = false;

	protected function terminate(): void {
		$this->terminated = true;
	}

}
