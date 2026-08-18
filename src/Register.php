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
     * When a container has been supplied through {@see Config::setContainer()} the notice is bound
     * there as a singleton, so the plugin can resolve the same instance elsewhere.
     *
     * @param array<string, string> $strings Optional translated copy, see CoreUpdateNotice.
     */
    public static function notice(array $strings = []): CoreUpdateNotice
    {
        $notice = new CoreUpdateNotice($strings);

        /*
         * Bound unconditionally rather than behind a has() check. has() is not a reliable "already
         * bound" test: an auto-wiring container such as di52 answers true for any instantiable
         * class, which would leave the notice unbound and let get() build a copy that has none of
         * the caller's strings.
         */
        if (Config::hasContainer()) {
            Config::getContainer()->singleton(CoreUpdateNotice::class, $notice);
        }

        $notice->register();

        return $notice;
    }
}
