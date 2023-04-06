<?php

namespace Penguin\Component\Collection;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;

interface Enumerable extends ArrayAccess, IteratorAggregate, JsonSerializable, Countable
{
    /**
     * Get all of the items in the collection.
     * 
     * @return array
     */
    public function all(): array;

    /**
     * Get all keys in a collection.
     * 
     * @return static
     */
    public function keys(): static;

    /**
     * Get all values in a collection.
     * 
     * @return static
     */
    public function values(): static;

    /**
     * Create a collection with the given range.
     *
     * @param string|int $start
     * @param string|int $end
     * @param string|int $step
     * @return static
     */
    public static function range(string|int $start, string|int $end, string|int $step = 1): static;

    /**
     * Push one or more items onto the end of the collection.
     *
     * @param mixed ...$values
     * @return $this
     */
    public function push(mixed ...$values): static;
}