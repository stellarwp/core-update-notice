<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey;
use Mockery;
use Brain\Monkey\Functions;
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
        unset($GLOBALS['nx_wp_core_update_notice_rendered']);

        // Escaping and translation helpers behave as identity functions under test.
        Functions\stubs([
            'esc_html' => null,
            'esc_url' => null,
            'esc_attr' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Config::reset();
        unset($GLOBALS['nx_wp_core_update_notice_rendered']);

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
     */
    protected function stubCoreUpdate(?string $response): void
    {
        Functions\when('get_core_updates')->justReturn(
            $response === null ? [] : [(object) ['response' => $response]]
        );
    }

    protected function stubDismissed(bool $dismissed): void
    {
        Functions\when('get_option')->justReturn($dismissed);
    }

    protected function render(CoreUpdateNotice $notice): string
    {
        ob_start();
        $notice->render();

        return (string) ob_get_clean();
    }
}
