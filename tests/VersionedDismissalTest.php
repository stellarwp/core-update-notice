<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Functions;
use RuntimeException;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Tests\Doubles\TerminatingNotice;

/**
 * Dismissal is recorded against the WordPress version it was dismissed for, so a later release
 * brings the notice back instead of silencing it permanently.
 */
final class VersionedDismissalTest extends TestCase
{
    public function testDismissLinkAndNonceTargetTheOfferedVersion(): void
    {
        $this->stubDismissed('');
        $this->stubCoreUpdate('upgrade', '6.8');
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('add_query_arg')
            ->once()
            ->with(CoreUpdateNotice::DISMISS_ACTION, '6.8')
            ->andReturn('/wp-admin/?' . CoreUpdateNotice::DISMISS_ACTION . '=6.8');
        Functions\expect('wp_nonce_url')
            ->once()
            ->with(
                '/wp-admin/?' . CoreUpdateNotice::DISMISS_ACTION . '=6.8',
                CoreUpdateNotice::DISMISS_ACTION . ':6.8'
            )
            ->andReturn('/wp-admin/?' . CoreUpdateNotice::DISMISS_ACTION . '=6.8&_wpnonce=signed');

        $notice = new CoreUpdateNotice();
        $this->stubWinner($notice);

        $this->assertStringContainsString(
            CoreUpdateNotice::DISMISS_ACTION . '=6.8&_wpnonce=signed',
            $this->render($notice)
        );
    }

    public function testDismissalStoresTheSignedRenderedVersionWithoutRequerying(): void
    {
        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '6.8';

        Functions\expect('get_core_updates')->never();
        Functions\expect('get_bloginfo')->never();
        Functions\expect('check_admin_referer')
            ->once()
            ->with(CoreUpdateNotice::DISMISS_ACTION . ':6.8');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('remove_query_arg')->justReturn('/wp-admin/');
        Functions\expect('wp_safe_redirect')->once();
        Functions\expect('update_option')
            ->once()
            ->with(CoreUpdateNotice::DISMISSED_OPTION, '6.8', false);

        $notice = new TerminatingNotice();
        $this->stubWinner($notice);
        $notice->handleDismissal();

        $this->assertTrue($notice->terminated);
    }

    public function testInvalidNonceCannotDismissTheTarget(): void
    {
        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '6.8';

        Functions\expect('check_admin_referer')->once()->andThrow(new RuntimeException('Invalid nonce'));
        Functions\expect('update_option')->never();
        Functions\expect('wp_safe_redirect')->never();

        $notice = new TerminatingNotice();
        $this->stubWinner($notice);

        try {
            $notice->handleDismissal();
            $this->fail('Nonce validation did not stop dismissal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Invalid nonce', $exception->getMessage());
        }

        $this->assertFalse($notice->terminated);
    }

    public function testDismissalIgnoresAnEmptyTarget(): void
    {
        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '';

        Functions\expect('check_admin_referer')->never();
        Functions\expect('update_option')->never();

        (new CoreUpdateNotice())->handleDismissal();
    }

    public function testDismissalIgnoresANonStringTarget(): void
    {
        $_GET[CoreUpdateNotice::DISMISS_ACTION] = ['6.8'];

        Functions\expect('check_admin_referer')->never();
        Functions\expect('update_option')->never();

        (new CoreUpdateNotice())->handleDismissal();
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
}
