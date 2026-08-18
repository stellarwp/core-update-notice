<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Tests\Doubles\NewerNotice;
use StellarWP\CoreUpdateNotice\Tests\Doubles\OlderNotice;

/**
 * When several plugins bundle the package, the highest notice version registered on the request
 * renders. Updating one plugin therefore puts it in charge without touching the others.
 */
final class NoticeVersionContestTest extends TestCase
{
    public function testASingleCopyRenders(): void
    {
        $this->stubDismissed('');
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $notice = new CoreUpdateNotice();
        $notice->register();

        $this->assertTrue($notice->isNewestRegistered());
        $this->assertNotSame('', $this->render($notice));
    }

    public function testTheNewerCopyWinsRegardlessOfRegistrationOrder(): void
    {
        $this->stubDismissed('');
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $older = new OlderNotice(['heading' => 'FROM THE OLD PLUGIN']);
        $newer = new NewerNotice(['heading' => 'FROM THE UPDATED PLUGIN']);

        // The stale plugin loads first, as it would if it sorted earlier.
        $older->register();
        $newer->register();

        $this->assertFalse($older->isNewestRegistered());
        $this->assertTrue($newer->isNewestRegistered());

        $this->assertSame('', $this->render($older));

        $output = $this->render($newer);
        $this->assertStringContainsString('FROM THE UPDATED PLUGIN', $output);
    }

    public function testTheNewerCopyWinsWhenItRegistersFirst(): void
    {
        $this->stubDismissed('');
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $newer = new NewerNotice(['heading' => 'FROM THE UPDATED PLUGIN']);
        $older = new OlderNotice(['heading' => 'FROM THE OLD PLUGIN']);

        $newer->register();
        $older->register();

        $this->assertSame('', $this->render($older));
        $this->assertStringContainsString('FROM THE UPDATED PLUGIN', $this->render($newer));
    }

    /**
     * The older copy must stand down even if its admin_notices callback fires first, which is the
     * case the render guard alone could not handle.
     */
    public function testTheOlderCopyStandsDownEvenWhenItRendersFirst(): void
    {
        $this->stubDismissed('');
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $older = new OlderNotice(['heading' => 'FROM THE OLD PLUGIN']);
        $newer = new NewerNotice(['heading' => 'FROM THE UPDATED PLUGIN']);

        $older->register();
        $newer->register();

        $first = $this->render($older);
        $second = $this->render($newer);

        $this->assertSame('', $first);
        $this->assertStringContainsString('FROM THE UPDATED PLUGIN', $second);
    }

    public function testEqualVersionsStillRenderOnlyOnce(): void
    {
        $this->stubDismissed('');
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $a = new CoreUpdateNotice();
        $b = new CoreUpdateNotice();

        $a->register();
        $b->register();

        $this->assertNotSame('', $this->render($a));
        $this->assertSame('', $this->render($b));
    }

    public function testAnUnregisteredCopyDoesNotBlockItself(): void
    {
        $this->stubDismissed('');
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        // render() without register() happens if a consumer hooks the method directly.
        $this->assertNotSame('', $this->render(new CoreUpdateNotice()));
    }
}
