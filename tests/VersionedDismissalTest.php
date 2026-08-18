<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Functions;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Tests\Doubles\TerminatingNotice;

/**
 * Dismissal is recorded against the WordPress version it was dismissed for, so a later release
 * brings the notice back instead of silencing it permanently.
 */
final class VersionedDismissalTest extends TestCase
{
    public function testDismissalStoresTheOfferedVersionNotABoolean(): void
    {
        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '1';

        $this->stubCoreUpdate('upgrade', '6.8');
        Functions\expect('check_admin_referer')->once();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('remove_query_arg')->justReturn('/wp-admin/');
        Functions\expect('wp_safe_redirect')->once();
        Functions\expect('update_option')
            ->once()
            ->with(CoreUpdateNotice::DISMISSED_OPTION, '6.8', false);

        $notice = new TerminatingNotice();
        $notice->handleDismissal();

        $this->assertTrue($notice->terminated);
    }

    public function testStaysHiddenForTheVersionItWasDismissedFor(): void
    {
        $this->stubDismissed('6.8');
        $this->stubCoreUpdate('upgrade', '6.8');

        $this->assertFalse((new CoreUpdateNotice())->shouldDisplay());
    }

    /**
     * The bug this addresses: a boolean flag silenced the notice for every future release.
     */
    public function testReturnsWhenALaterVersionIsOffered(): void
    {
        $this->stubDismissed('6.8');
        $this->stubCoreUpdate('upgrade', '6.9');

        $this->assertTrue((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testStaysHiddenWhenTheOfferIsOlderThanTheDismissal(): void
    {
        $this->stubDismissed('6.9');
        $this->stubCoreUpdate('upgrade', '6.8');

        $this->assertFalse((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testHandlesPointReleases(): void
    {
        $this->stubDismissed('6.8.1');
        $this->stubCoreUpdate('upgrade', '6.8.2');

        $this->assertTrue((new CoreUpdateNotice())->shouldDisplay());
    }

    /**
     * A flag written before dismissal was versioned is adopted for the current offer: honoured now,
     * re-armed on the next release.
     */
    public function testALegacyBooleanFlagIsMigratedToTheCurrentOffer(): void
    {
        $this->stubDismissed(true);
        $this->stubCoreUpdate('upgrade', '6.8');

        Functions\expect('update_option')
            ->once()
            ->with(CoreUpdateNotice::DISMISSED_OPTION, '6.8', false);

        $this->assertFalse((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testDismissalFallsBackToTheInstalledVersionWithoutAnOffer(): void
    {
        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '1';

        $this->stubCoreUpdate(null);
        Functions\expect('check_admin_referer')->once();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_bloginfo')->justReturn('6.7');
        Functions\when('remove_query_arg')->justReturn('/wp-admin/');
        Functions\expect('wp_safe_redirect')->once();
        Functions\expect('update_option')
            ->once()
            ->with(CoreUpdateNotice::DISMISSED_OPTION, '6.7', false);

        (new TerminatingNotice())->handleDismissal();
    }
}
