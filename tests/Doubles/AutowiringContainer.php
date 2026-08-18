<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests\Doubles;

use StellarWP\ContainerContract\ContainerInterface;

/**
 * Emulates di52: has() answers true for any instantiable class whether or not it was bound, and an
 * unbound class is auto-wired fresh on every get(). Guards the package against reintroducing a
 * has()-based "already bound?" check, which that behaviour silently defeats.
 */
final class AutowiringContainer implements ContainerInterface
{
    /**
     * @var array<string, mixed>
     */
    private $bindings = [];

    /**
     * @param string|class-string $id
     * @param mixed               $implementation
     *
     * @return void
     */
    public function bind(string $id, $implementation = null)
    {
        $this->bindings[$id] = $implementation;
    }

    /**
     * @param string|class-string $id
     *
     * @return mixed
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->bindings)) {
            return $this->bindings[$id];
        }

        return class_exists($id) ? new $id() : null;
    }

    /**
     * @param string|class-string $id
     *
     * @return bool
     */
    public function has(string $id)
    {
        return array_key_exists($id, $this->bindings) || class_exists($id);
    }

    /**
     * @param string|class-string $id
     * @param mixed               $implementation
     *
     * @return void
     */
    public function singleton(string $id, $implementation = null)
    {
        $this->bindings[$id] = $implementation;
    }
}
