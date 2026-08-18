<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Actions;
use RuntimeException;
use StellarWP\CoreUpdateNotice\Config;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Register;
use StellarWP\CoreUpdateNotice\Tests\Doubles\FakeContainer;

final class RegisterTest extends TestCase
{
    public function testConfigReportsWhetherAContainerIsSet(): void
    {
        $this->assertFalse(Config::hasContainer());

        Config::setContainer(new FakeContainer());

        $this->assertTrue(Config::hasContainer());
    }

    public function testGetContainerThrowsWhenNoneIsSet(): void
    {
        $this->expectException(RuntimeException::class);

        Config::getContainer();
    }

    public function testItRegistersWithoutAContainer(): void
    {
        Actions\expectAdded('admin_init')->once();
        Actions\expectAdded('admin_notices')->once();

        $this->assertInstanceOf(CoreUpdateNotice::class, Register::notice());
    }

    public function testItBindsTheNoticeAsASingletonOnTheContainer(): void
    {
        Actions\expectAdded('admin_init')->once();
        Actions\expectAdded('admin_notices')->once();

        $container = new FakeContainer();
        Config::setContainer($container);

        $notice = Register::notice();

        $this->assertTrue($container->has(CoreUpdateNotice::class));
        $this->assertSame($notice, $container->get(CoreUpdateNotice::class));
    }

    /**
     * A plugin that has already bound its own instance gets that one back, not a second one.
     */
    public function testItResolvesAnAlreadyBoundInstance(): void
    {
        Actions\expectAdded('admin_init')->once();
        Actions\expectAdded('admin_notices')->once();

        $existing = new CoreUpdateNotice(['heading' => 'Bound by the plugin']);

        $container = new FakeContainer();
        $container->singleton(CoreUpdateNotice::class, $existing);
        Config::setContainer($container);

        $this->assertSame($existing, Register::notice());
    }

    /**
     * A container that returns something unexpected must not produce a TypeError.
     */
    public function testItFallsBackWhenTheContainerReturnsTheWrongType(): void
    {
        Actions\expectAdded('admin_init')->once();
        Actions\expectAdded('admin_notices')->once();

        $container = new FakeContainer();
        $container->singleton(CoreUpdateNotice::class, 'not a notice');
        Config::setContainer($container);

        $this->assertInstanceOf(CoreUpdateNotice::class, Register::notice());
    }
}
