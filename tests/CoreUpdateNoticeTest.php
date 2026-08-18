<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Tests\Doubles\TerminatingNotice;

final class CoreUpdateNoticeTest extends TestCase
{
    public function testDisplaysWhenACoreUpdateIsAvailable(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate('upgrade');

        $this->assertTrue((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testDoesNotDisplayWhenCoreIsUpToDate(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate('latest');

        $this->assertFalse((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testDoesNotDisplayWhenNoUpdateDataIsAvailable(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate(null);

        $this->assertFalse((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testDoesNotDisplayWhenTheOfferedVersionIsMissing(): void
    {
        $this->stubDismissed([]);
        Functions\when('get_core_updates')->justReturn([(object) ['response' => 'upgrade']]);

        $this->assertFalse((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testRendersTheCopyAndADismissLink(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $output = $this->render(new CoreUpdateNotice());

        $this->assertStringContainsString(
            'Keep your site protected. Update to the latest version of WordPress.',
            $output
        );
        $this->assertStringContainsString('notice-warning', $output);
        $this->assertStringContainsString('is-dismissible', $output);
        $this->assertStringContainsString(CoreUpdateNotice::DISMISS_ACTION, $output);
    }

    public function testRendersNothingWithoutTheUpdateCoreCapability(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate('upgrade');
        Functions\when('current_user_can')->justReturn(false);

        $this->assertSame('', $this->render(new CoreUpdateNotice()));
    }

    /**
     * Two plugins bundling this package must not print the notice twice.
     */
    public function testRendersOnlyOncePerRequest(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $this->assertNotSame('', $this->render(new CoreUpdateNotice()));
        $this->assertSame('', $this->render(new CoreUpdateNotice()));
    }

    public function testConsumerSuppliedCopyOverridesTheDefaults(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $output = $this->render(new CoreUpdateNotice(['heading' => 'Traduzido']));

        $this->assertStringContainsString('Traduzido', $output);
        $this->assertStringNotContainsString('Keep your site protected', $output);
    }

    public function testPartialCopyFallsBackToDefaultsForMissingKeys(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $output = $this->render(new CoreUpdateNotice(['heading' => 'Traduzido']));

        $this->assertStringContainsString('outdated version of WordPress', $output);
    }

    public function testRegisterHooksDismissalAndBothNoticeHooks(): void
    {
        Actions\expectAdded('admin_init')->once();
        Actions\expectAdded('admin_notices')->once();
        // Multisite keeps update-core.php in the network admin, which admin_notices never reaches.
        Actions\expectAdded('network_admin_notices')->once();

        (new CoreUpdateNotice())->register();
    }

    public function testDismissalIsIgnoredWithoutTheQueryArgument(): void
    {
        Functions\expect('check_admin_referer')->never();
        Functions\expect('update_option')->never();

        (new CoreUpdateNotice())->handleDismissal();
    }

    public function testDismissalIsRefusedWithoutTheUpdateCoreCapability(): void
    {
        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '1';

        Functions\expect('check_admin_referer')->once();
        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('update_option')->never();
        Functions\expect('wp_safe_redirect')->never();

        $notice = new TerminatingNotice();
        $notice->handleDismissal();

        $this->assertFalse($notice->terminated);
    }
}
