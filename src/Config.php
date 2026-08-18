<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice;

use RuntimeException;
use StellarWP\ContainerContract\ContainerInterface;

/**
 * Holds the container the consuming plugin wants this package to resolve through.
 *
 * Optional: without a container the package instantiates its own objects. Supplying one lets the
 * plugin own the lifecycle, so the notice is the same instance the rest of its code resolves.
 */
final class Config
{
    /**
     * @var ContainerInterface|null
     */
    private static ?ContainerInterface $container = null;

    /**
     * Set the container the package resolves through.
     */
    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * @throws RuntimeException When no container has been set.
     */
    public static function getContainer(): ContainerInterface
    {
        if (self::$container === null) {
            throw new RuntimeException(
                'No container set. Call ' . self::class . '::setContainer() before getContainer().'
            );
        }

        return self::$container;
    }

    public static function hasContainer(): bool
    {
        return self::$container !== null;
    }

    /**
     * Drop the container. Primarily for tests.
     */
    public static function reset(): void
    {
        self::$container = null;
    }
}
