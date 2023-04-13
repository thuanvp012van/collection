<?php

namespace Penguin\Component\Collection;

use Countable;
use IteratorAggregate;
use JsonSerializable;

interface Enumerable extends IteratorAggregate, JsonSerializable, Countable
{
    /**
     * Create a new collection instance.
     * 
     * @param mixed $items
     * @return static
     */
    public static function make(mixed $items = []): static;

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
     * Convert all items to array.
     * 
     * @return array
     */
    public function toArray(): array;
}