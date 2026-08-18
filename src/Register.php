<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice;

/**
 * Entry point for consuming plugins.
 */
final class Register
{
    /**
     * Build the notice and hook it into wp-admin.
     *
     * When a container has been supplied through {@see Config::setContainer()} the notice is
     * registered as a singleton there and resolved from it, so the plugin gets the same instance
     * its own code resolves. Without a container the notice is simply instantiated.
     *
     * @param array<string, string> $strings Optional translated copy, see CoreUpdateNotice.
     */
    public static function notice(array $strings = []): CoreUpdateNotice
    {
        $notice = self::resolve($strings);

        $notice->register();

        return $notice;
    }

    /**
     * @param array<string, string> $strings
     */
    private static function resolve(array $strings): CoreUpdateNotice
    {
        if (!Config::hasContainer()) {
            return new CoreUpdateNotice($strings);
        }

        $container = Config::getContainer();

        if (!$container->has(CoreUpdateNotice::class)) {
            $container->singleton(CoreUpdateNotice::class, new CoreUpdateNotice($strings));
        }

        $resolved = $container->get(CoreUpdateNotice::class);

        // A container may hand back anything; only trust it when it is what we asked for.
        return $resolved instanceof CoreUpdateNotice ? $resolved : new CoreUpdateNotice($strings);
    }
}
