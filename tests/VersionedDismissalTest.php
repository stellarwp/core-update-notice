<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Functions;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Tests\Doubles\TerminatingNotice;

/**
 * Dismissal is a set of offers, keyed the way WordPress keys `dismissed_update_core`, so silencing
 * one release never silences a different one.
 */
final class VersionedDismissalTest extends TestCase
{
    public function testStaysHiddenForTheOfferItWasDismissedFor(): void
    {
        $this->stubDismissed(['6.8|en_US']);
        $this->stubCoreUpdate('upgrade', '6.8');

        $this->assertFalse((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testReturnsForAnOfferThatWasNeverDismissed(): void
    {
        $this->stubDismissed(['6.8|en_US']);
        $this->stubCoreUpdate('upgrade', '6.9');

        $this->assertTrue((new CoreUpdateNotice())->shouldDisplay());
    }

    /**
     * The bug this replaces: with a single stored version compared using >=, dismissing 6.9 hid a
     * later 6.8.1 security release, which is precisely what this notice exists to surface.
     */
    public function testALaterInBranchSecurityReleaseIsStillShownAfterDismissingANewerOffer(): void
    {
        $this->stubDismissed(['6.9|en_US']);
        $this->stubCoreUpdate('upgrade', '6.8.1');

        $this->assertTrue((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testDismissalsAreKeyedByLocaleAsWellAsVersion(): void
    {
        $this->stubDismissed(['6.9|en_US']);
        $this->stubCoreUpdate('upgrade', '6.9', 'pt_BR');

        $this->assertTrue((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testDismissingOneOfferLeavesEarlierDismissalsIntact(): void
    {
        $store = $this->stubDismissedStore(['6.8|en_US']);

        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '6.9|en_US';

        $this->stubCoreUpdate('upgrade', '6.9');
        Functions\expect('check_admin_referer')->once();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('remove_query_arg')->justReturn('/wp-admin/');
        Functions\expect('wp_safe_redirect')->once();

        (new TerminatingNotice())->handleDismissal();

        $this->assertSame(['6.8|en_US' => true, '6.9|en_US' => true], $store->getArrayCopy());
    }

    /**
     * The round trip the previous suite could not see: dismiss, then read back.
     */
    public function testAnOfferDismissedThisRequestIsHiddenOnTheNextRead(): void
    {
        $this->stubDismissedStore();

        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '6.9|en_US';

        $this->stubCoreUpdate('upgrade', '6.9');
        Functions\expect('check_admin_referer')->once();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('remove_query_arg')->justReturn('/wp-admin/');
        Functions\expect('wp_safe_redirect')->once();

        $notice = new TerminatingNotice();

        $this->assertTrue($notice->shouldDisplay());

        $notice->handleDismissal();

        $this->assertFalse($notice->shouldDisplay());

        // ...and a different offer is unaffected.
        $this->stubCoreUpdate('upgrade', '6.9.1');
        $this->assertTrue($notice->shouldDisplay());
    }

    /**
     * The key travels in the dismiss link, so the dismissal records what the user was shown even if
     * the update transient changes between render and click.
     */
    public function testTheDismissedKeyComesFromTheLinkNotTheCurrentTransient(): void
    {
        $store = $this->stubDismissedStore();

        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '6.9|en_US';

        // The transient has moved on since the notice was rendered.
        $this->stubCoreUpdate('upgrade', '7.0');
        Functions\expect('check_admin_referer')->once();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('remove_query_arg')->justReturn('/wp-admin/');
        Functions\expect('wp_safe_redirect')->once();

        (new TerminatingNotice())->handleDismissal();

        $this->assertSame(['6.9|en_US' => true], $store->getArrayCopy());
    }

    public function testAMalformedKeyInTheLinkFallsBackToTheCurrentOffer(): void
    {
        $store = $this->stubDismissedStore();

        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '<script>alert(1)</script>';

        $this->stubCoreUpdate('upgrade', '6.9');
        Functions\expect('check_admin_referer')->once();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('remove_query_arg')->justReturn('/wp-admin/');
        Functions\expect('wp_safe_redirect')->once();

        (new TerminatingNotice())->handleDismissal();

        $this->assertSame(['6.9|en_US' => true], $store->getArrayCopy());
    }

    public function testDismissalWritesNothingWhenThereIsNoOfferAtAll(): void
    {
        $store = $this->stubDismissedStore();

        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '1';

        $this->stubCoreUpdate(null);
        Functions\expect('check_admin_referer')->once();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('remove_query_arg')->justReturn('/wp-admin/');
        Functions\expect('wp_safe_redirect')->once();

        (new TerminatingNotice())->handleDismissal();

        $this->assertSame([], $store->getArrayCopy());
    }

    public function testACorruptOptionValueIsTreatedAsNoDismissals(): void
    {
        Functions\when('get_site_option')->justReturn('not an array');
        $this->stubCoreUpdate('upgrade', '6.9');

        $this->assertTrue((new CoreUpdateNotice())->shouldDisplay());
    }

    public function testNothingIsWrittenWhileMerelyCheckingWhetherToDisplay(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate('upgrade', '6.9');

        Functions\expect('update_site_option')->never();
        Functions\expect('update_option')->never();

        (new CoreUpdateNotice())->shouldDisplay();
    }
}
