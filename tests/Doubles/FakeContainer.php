<?php

declare(strict_types=1);

namespace StellarWP\CoreUpdateNotice\Tests\Doubles;

use StellarWP\ContainerContract\ContainerInterface;

/**
 * Minimal container-contract implementation, standing in for di52 or whatever the consuming plugin
 * uses.
 */
final class FakeContainer implements ContainerInterface
{
    /**
     * @var array<string, mixed>
     */
    private $bindings = [];

    /**
     * @var int
     */
    public $getCalls = 0;

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
        $this->getCalls++;

        return $this->bindings[$id] ?? null;
    }

    /**
     * @param string|class-string $id
     *
     * @return bool
     */
    public function has(string $id)
    {
        return array_key_exists($id, $this->bindings);
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
