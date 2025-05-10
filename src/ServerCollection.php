<?php

namespace LoadBalancer;

use Countable;
use IteratorAggregate;
use ArrayIterator;

class ServerCollection implements IteratorAggregate, Countable
{
    private array $servers = [];

    public function add(Server $server): void
    {
        $this->servers[] = $server;
    }

    public function all(): array
    {
        return $this->servers;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->servers);
    }

    public function count(): int
    {
        return count($this->servers);
    }
}
