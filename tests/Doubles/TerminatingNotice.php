<?php declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests\Doubles;

use StellarWP\CoreUpdateNotice\CoreUpdateNotice;

/**
 * exit cannot be intercepted, so the dismissal path records termination instead of performing it.
 */
final class TerminatingNotice extends CoreUpdateNotice {

	public bool $terminated = false;

	protected function terminate(): void {
		$this->terminated = true;
	}

}
