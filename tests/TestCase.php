<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use StellarWP\CoreUpdateNotice\Config;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;

abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Monkey\setUp();

        Config::reset();

        // Escaping helpers behave as identity functions under test.
        Functions\stubs([
            'esc_html' => null,
            'esc_url' => null,
            'esc_attr' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Config::reset();
        unset($_GET[CoreUpdateNotice::DISMISS_ACTION]);

        // Brain\Monkey expectations are Mockery assertions; count them so tests that only assert
        // through expectAdded()/expect() are not reported as risky.
        if ($container = Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }

        Monkey\tearDown();

        parent::tearDown();
    }

    /**
     * Stub the update_core read that CoreUpdateNotice performs.
     *
     * @param string|null $response The response WordPress reports, or null for no offer at all.
     * @param string      $offered  The version being offered, WordPress calls it "current".
     */
    protected function stubCoreUpdate(?string $response, string $offered = '9.9'): void
    {
        Functions\when('get_core_updates')->justReturn(
            $response === null ? [] : [(object) ['response' => $response, 'current' => $offered]]
        );
    }

    /**
     * Stub the stored dismissal option. Pass '' for never dismissed.
     *
     * @param mixed $value
     */
    protected function stubDismissed($value): void
    {
        Functions\when('get_option')->alias(
            static function (string $name, $default = false) use ($value) {
                return $name === CoreUpdateNotice::DISMISSED_OPTION ? $value : $default;
            }
        );
    }

    /**
     * Stub everything the render path needs beyond the update and dismissal state.
     */
    protected function stubRenderable(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('add_query_arg')->justReturn('/wp-admin/?' . CoreUpdateNotice::DISMISS_ACTION . '=1');
        Functions\when('wp_nonce_url')->returnArg();
    }

    protected function stubWinner(CoreUpdateNotice $notice, int $times = 1): void
    {
        Filters\expectApplied(CoreUpdateNotice::WINNER_FILTER)
            ->times($times)
            ->with(null)
            ->andReturn($notice->selectWinner(null));
    }

    protected function render(CoreUpdateNotice $notice): string
    {
        ob_start();
        $notice->render();

        return (string) ob_get_clean();
    }
}
