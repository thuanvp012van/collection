<?php

namespace Penguin\Component\Collection;

use Penguin\Component\Collection\Traits\EnumeratesValues;
use Penguin\Component\Plug\Plug;

class LazyCollection implements Enumerable
{
    use Plug;
    use EnumeratesValues;

    protected iterable $source;

    /**
     * Get all of the items in the collection.
     * 
     * @return array
     */
    public function all(): array
    {
        if (is_array($this->source)) {
            return $this->source;
        }

        return iterator_to_array($this->getIterator());
    }

    /**
     * Collect the values into a collection.
     * 
     * @return static
     */
    public function collect(): static
    {
        return new static($this->getArrayableItems($this->source));
    }

    /**
     * Get the keys of the collection items.
     *
     * @return static
     */
    public function keys(): static
    {
        return new static(function () {
            foreach ($this as $key => $value) {
                yield $key;
            }
        });
    }

    /**
     * Get all values in a collection.
     * 
     * @return static
     */
    public function values(): static
    {
        return new static(function () {
            foreach ($this as $value) {
                yield $value;
            }
        });
    }

    /**
     * Calculate the sum of values in a collection.
     * 
     * @param string|int|callable $key
     * @return int|float
     */
    public function sum(string|int|callable $key = null): int|float
    {

    }

    public function filter()
    {

    }
}