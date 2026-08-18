<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests;

use Brain\Monkey\Actions;
use ReflectionProperty;
use RuntimeException;
use StellarWP\CoreUpdateNotice\Config;
use StellarWP\CoreUpdateNotice\CoreUpdateNotice;
use StellarWP\CoreUpdateNotice\Register;
use StellarWP\CoreUpdateNotice\Tests\Doubles\AutowiringContainer;
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
     * di52 answers has() true for any instantiable class, so a has()-guarded binding would never
     * run and the container would auto-wire a copy carrying none of the caller's strings.
     */
    public function testItBindsOnAnAutowiringContainerAndKeepsTheCallerStrings(): void
    {
        Actions\expectAdded('admin_init')->once();
        Actions\expectAdded('admin_notices')->once();

        $container = new AutowiringContainer();
        Config::setContainer($container);

        $notice = Register::notice(['heading' => 'Traduzido']);

        $this->assertSame($notice, $container->get(CoreUpdateNotice::class));
        $this->assertSame('Traduzido', $this->headingOf($container->get(CoreUpdateNotice::class)));
    }

    public function testTheBoundInstanceIsStableAcrossResolutions(): void
    {
        Actions\expectAdded('admin_init')->once();
        Actions\expectAdded('admin_notices')->once();

        $container = new AutowiringContainer();
        Config::setContainer($container);

        Register::notice();

        $this->assertSame(
            $container->get(CoreUpdateNotice::class),
            $container->get(CoreUpdateNotice::class)
        );
    }

    /**
     * @param mixed $notice
     */
    private function headingOf($notice): string
    {
        $this->assertInstanceOf(CoreUpdateNotice::class, $notice);

        $property = new ReflectionProperty(CoreUpdateNotice::class, 'strings');
        $property->setAccessible(true);

        /** @var array<string, string> $strings */
        $strings = $property->getValue($notice);

        return $strings['heading'];
    }
}
