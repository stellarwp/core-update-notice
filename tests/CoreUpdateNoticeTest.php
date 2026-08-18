<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Filters;
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

        $notice = new CoreUpdateNotice();
        $this->stubWinner($notice);

        $output = $this->render($notice);

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

        $notice = new CoreUpdateNotice();
        $this->stubWinner($notice);

        $this->assertSame('', $this->render($notice));
    }

    public function testConsumerSuppliedCopyOverridesTheDefaults(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $notice = new CoreUpdateNotice(['heading' => 'Traduzido']);
        $this->stubWinner($notice);

        $output = $this->render($notice);

        $this->assertStringContainsString('Traduzido', $output);
        $this->assertStringNotContainsString('Keep your site protected', $output);
    }

    public function testPartialCopyFallsBackToDefaultsForMissingKeys(): void
    {
        $this->stubDismissed([]);
        $this->stubCoreUpdate('upgrade');
        $this->stubRenderable();

        $notice = new CoreUpdateNotice(['heading' => 'Traduzido']);
        $this->stubWinner($notice);

        $output = $this->render($notice);

        $this->assertStringContainsString('outdated version of WordPress', $output);
    }

    public function testEscapesConsumerCopyAndTheDismissUrl(): void
    {
        $copy = [
            'heading' => '<script>heading</script>',
            'body' => '<img src=x onerror=alert(1)>',
            'dismiss' => '<svg onload=alert(1)>',
        ];
        $dismissUrl = "https://example.test/wp-admin/?redirect='unsafe'&amp;"
            . CoreUpdateNotice::DISMISS_ACTION . '=9.9';

        $this->stubDismissed([]);
        $this->stubCoreUpdate('upgrade');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('add_query_arg')->justReturn($dismissUrl);
        Functions\when('wp_nonce_url')->returnArg();

        $notice = new CoreUpdateNotice($copy);
        $this->stubWinner($notice);

        $output = $this->render($notice);

        foreach ($copy as $value) {
            $this->assertStringNotContainsString($value, $output);
            $this->assertStringContainsString(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $output);
        }
        $this->assertStringNotContainsString($dismissUrl, $output);
        $this->assertStringContainsString(
            "href=\"https://example.test/wp-admin/?redirect=&#039;unsafe&#039;&#038;"
                . CoreUpdateNotice::DISMISS_ACTION . '=9.9"',
            $output
        );
    }

    public function testDismissalIsIgnoredWithoutTheQueryArgument(): void
    {
        Filters\expectApplied(CoreUpdateNotice::WINNER_FILTER)->never();
        Functions\expect('check_admin_referer')->never();
        Functions\expect('update_option')->never();

        (new CoreUpdateNotice())->handleDismissal();
    }

    public function testDismissalIsRefusedWithoutTheUpdateCoreCapability(): void
    {
        $_GET[CoreUpdateNotice::DISMISS_ACTION] = '6.8';

        Functions\expect('check_admin_referer')
            ->once()
            ->with(CoreUpdateNotice::DISMISS_ACTION . ':6.8');
        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('update_option')->never();
        Functions\expect('wp_safe_redirect')->never();

        $notice = new TerminatingNotice();
        $this->stubWinner($notice);
        $notice->handleDismissal();

        $this->assertFalse($notice->terminated);
    }
}
