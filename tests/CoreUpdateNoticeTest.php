<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Tests\Doubles\TerminatingNotice;

final class CoreUpdateNoticeTest extends TestCase
{
    public function testDisplaysWhenACoreUpdateIsAvailable(): void
    {
        $this->stubDismissed(false);
        $this->stubCoreUpdate('upgrade');

        $this->assertTrue((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testDoesNotDisplayWhenCoreIsUpToDate(): void
    {
        $this->stubDismissed(false);
        $this->stubCoreUpdate('latest');

        $this->assertFalse((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testDoesNotDisplayWhenNoUpdateDataIsAvailable(): void
    {
        $this->stubDismissed(false);
        $this->stubCoreUpdate(null);

        $this->assertFalse((new CoreUpdateNotice())->shouldDisplay());
    }

    /**
     * The flag is shared with the other plugins carrying this notice, so a value written by any of
     * them suppresses it here as well.
     */
    public function testDoesNotDisplayOnceTheSharedDismissalFlagIsSet(): void
    {
        $this->stubDismissed(true);
        $this->stubCoreUpdate('upgrade');

        $this->assertFalse((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testRendersTheCopyAndADismissLink(): void
    {
        $this->stubDismissed(false);
        $this->stubCoreUpdate('upgrade');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('add_query_arg')->justReturn('/wp-admin/?' . CoreUpdateNotice::DISMISS_ACTION . '=1');
        Functions\when('wp_nonce_url')->returnArg();

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
        $this->stubDismissed(false);
        $this->stubCoreUpdate('upgrade');
        Functions\when('current_user_can')->justReturn(false);

        $this->assertSame('', $this->render(new CoreUpdateNotice()));
    }

    /**
     * Two plugins bundling this package must not print the notice twice.
     */
    public function testRendersOnlyOncePerRequest(): void
    {
        $this->stubDismissed(false);
        $this->stubCoreUpdate('upgrade');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('add_query_arg')->justReturn('/wp-admin/?dismiss=1');
        Functions\when('wp_nonce_url')->returnArg();

        $this->assertNotSame('', $this->render(new CoreUpdateNotice()));
        $this->assertSame('', $this->render(new CoreUpdateNotice()));
    }

    public function testConsumerSuppliedCopyOverridesTheDefaults(): void
    {
        $this->stubDismissed(false);
        $this->stubCoreUpdate('upgrade');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('add_query_arg')->justReturn('/wp-admin/?dismiss=1');
        Functions\when('wp_nonce_url')->returnArg();

        $output = $this->render(new CoreUpdateNotice(['heading' => 'Traduzido']));

        $this->assertStringContainsString('Traduzido', $output);
        $this->assertStringNotContainsString('Keep your site protected', $output);
    }

    public function testPartialCopyFallsBackToDefaultsForMissingKeys(): void
    {
        $this->stubDismissed(false);
        $this->stubCoreUpdate('upgrade');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('add_query_arg')->justReturn('/wp-admin/?dismiss=1');
        Functions\when('wp_nonce_url')->returnArg();

        $output = $this->render(new CoreUpdateNotice(['heading' => 'Traduzido']));

        $this->assertStringContainsString('outdated version of WordPress', $output);
    }

    public function testRegisterHooksAdminInitAndAdminNotices(): void
    {
        Actions\expectAdded('admin_init')->once();
        Actions\expectAdded('admin_notices')->once();

        (new CoreUpdateNotice())->register();
    }

    public function testDismissalIsIgnoredWithoutTheQueryArgument(): void
    {
        unset($_GET[CoreUpdateNotice::DISMISS_ACTION]);

        Functions\expect('check_admin_referer')->never();
        Functions\expect('update_option')->never();

        (new CoreUpdateNotice())->handleDismissal();
    }

    public function testDismissalStoresTheSharedFlagNonAutoloaded(): void
    {
        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '1';

        Functions\expect('check_admin_referer')
            ->once()
            ->with(CoreUpdateNotice::DISMISS_ACTION);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('remove_query_arg')->justReturn('/wp-admin/');
        Functions\expect('update_option')
            ->once()
            ->with(CoreUpdateNotice::DISMISSED_OPTION, true, false);
        Functions\expect('wp_safe_redirect')->once();

        $notice = new TerminatingNotice();
        $notice->handleDismissal();

        $this->assertTrue($notice->terminated);

        unset($_GET[CoreUpdateNotice::DISMISS_ACTION]);
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

        unset($_GET[CoreUpdateNotice::DISMISS_ACTION]);
    }
}
