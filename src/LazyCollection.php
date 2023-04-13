<?php

namespace Penguin\Component\Collection;

use ArrayIterator;
use Penguin\Component\Collection\Traits\EnumeratesValues;
use Penguin\Component\Plug\Plug;
use InvalidArgumentException;
use Generator;
use Closure;
use IteratorAggregate;
use Traversable;

class LazyCollection implements Enumerable
{
    use Plug;
    use EnumeratesValues;

    protected iterable $source;

    /**
     * Create a new lazy collection instance.
     * 
     * @param mixed $source
     * @return void
     */
    public function __construct(mixed $source = null)
    {
        if ($source instanceof Closure || $source instanceof self) {
            $this->source = $source;
        } elseif (is_null($source)) {
            $this->source = static::empty();
        } elseif ($source instanceof Generator) {
            throw new InvalidArgumentException(
                'Generators should not be passed directly to LazyCollection. Instead, pass a generator function.'
            );
        } else {
            $this->source = $this->getArrayableItems($source);
        }
    }

    /**
     * Create a new lazy collection instance.
     * 
     * @param mixed $items
     * @return static
     */
    public static function make(mixed $items = []): static
    {
        return new static($items);
    }

    /**
     * Create a collection with the given range.
     *
     * @param string|int $start
     * @param string|int $end
     * @param string|int $step
     * @return static
     */
    public static function range(string|int $start, string|int $end, string|int $step = 1): static
    {
        return new static(function () use ($start, $end, $step) {
            if ($start <= $end) {
                for (; $start <= $end; $start = $start + $step) {
                    yield $start;
                }
            } else {
                for (; $start >= $end; $start = $start - $step) {
                    yield $start;
                }
            }
        });
    }

    /**
     * Get an item from the collection by key.
     * 
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string|int $key, mixed $default = null): mixed
    {
        foreach ($this as $outerKey => $outerValue) {
            if ($outerKey == $key) {
                return $outerValue;
            }
        }
        return $this->getValue($default);
    }

    /**
     * Determines if a given key exists in the collection:
     * 
     * @param string|int ...$keys
     * @return bool
     */
    public function has(string|int ...$keys): bool
    {
        $keys = Arr::flip($keys);
        $count = count($keys);
        foreach ($this as $key => $value) {
            if (isset($keys[$key]) && --$count === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determines whether any of the given keys exist in the collection.
     * 
     * @param string|int ...$keys
     * @return bool
     */
    public function hasAny(string|int ...$keys): bool
    {
        $keys = Arr::flip($keys);
        foreach ($this as $key => $value) {
            if (isset($keys[$key])) {
                return true;
            }
        }
        return false;
    }

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

    public function filter(callable $callback): static
    {
        return new static(function () {
            
        });
    }

    /**
     * Get the values iterator.
     * 
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return $this->makeIterator($this->source);
    }

    /**
     * Make an iterator from the given source.
     *
     * @param mixed $source
     * @return Traversable
     */
    protected function makeIterator(mixed $source): Traversable
    {
        if ($source instanceof IteratorAggregate) {
            return $source->getIterator();
        }

        if (is_array($source)) {
            return new ArrayIterator($source);
        }

        if (is_callable($source)) {
            $maybeTraversable = $source();

            return $maybeTraversable instanceof Traversable
                ? $maybeTraversable
                : new ArrayIterator(Arr::wrap($maybeTraversable));
        }

        return new ArrayIterator((array) $source);
    }
}