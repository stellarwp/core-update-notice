<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests\Doubles;

use StellarWP\CoreUpdateNotice\CoreUpdateNotice;

/**
 * Stands in for a plugin that has not been updated in a while.
 */
final class OlderNotice extends CoreUpdateNotice
{
    public const NOTICE_VERSION = '0.0.1';

    public bool $terminated = false;

    protected function terminate(): void
    {
        $this->terminated = true;
    }
}
