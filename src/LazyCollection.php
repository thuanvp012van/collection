<?php

namespace Penguin\Component\Collection;

use ArrayIterator;
use Penguin\Component\Collection\Traits\EnumeratesValues;
use Penguin\Component\Plug\Plug;
use InvalidArgumentException;
use Generator;
use Closure;
use IteratorAggregate;
use stdClass;
use Traversable;

class LazyCollection implements Enumerable
{
    use Plug;
    use EnumeratesValues;

    protected mixed $source;

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
     * Create a new lazy lazy collection instance.
     * 
     * @param mixed $items
     * @return static
     */
    public static function make(mixed $items = []): static
    {
        return new static($items);
    }

    /**
     * Create a lazy collection with the given range.
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
     * Get an item from the lazy collection by key.
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
     * Determines if a given key exists in the lazy collection:
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
     * Determines whether any of the given keys exist in the lazy collection.
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
     * Get all item of the collection except for a specified array of keys.
     * 
     * @param string|int ...$keys
     * @return static
     */
    public function except(string|int ...$keys): static
    {
        $keys = Arr::flip($keys);
        return new static(function () use (&$keys) {
            foreach ($this as $key => $value) {
                if (!isset($keys[$key])) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Get all of the items in the lazy collection.
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
     * Get the keys of the lazy collection items.
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
     * Key an associative array by a field or using a callback.
     *
     * @param string|callable $keyBy
     * @return static
     */
    public function keyBy(string|callable $keyBy): static
    {
        if (is_string($keyBy)) {
            $segments = $this->explodeKey($keyBy);
            $keyBy = fn ($item) => extract_item($item, $segments);
        }

        return new static(function () use ($keyBy) {
            foreach ($this as $key => $item) {
                yield $keyBy($item, $key) => $item;
            }
        });
    }

    /**
     * Get all values in a lazy collection.
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
     * Count the number of items in the collection by a field or using a callback.
     * 
     * @param callable|null $callback
     * @return mixed
     */
    public function countBy(callable $callback = null): mixed
    {
        return $this->collect()->countBy($callback);
    }

    /**
     * Get the average value of a given key.
     * 
     * @param string|int|null $key
     * @return int|float|null
     */
    public function avg(string|int $key = null): int|float|null
    {
        return $this->collect()->avg($key);
    }

    /**
     * Find highest value in the collection.
     * 
     * @param string|callback|null $callback
     * @return mixed
     */
    public function max(string|callable $callback = null): mixed
    {
        if ($callback === null) {
            $callback = fn ($item) => $item;
        } else if (is_string($callback)) {
            $segments = $this->explodeKey($callback);
            $callback = function ($item) use ($segments) {
                $childItem = extract_item($item, $segments);
                return $item !== $childItem ? $childItem : null;
            };
        }

        return $this->reduce(function ($max, $value, $key) use ($callback) {
            $value = $callback($value, $key);
            return $max === null || $value > $max ? $value : $max;
        });
    }

    /**
     * Find lowest value in the collection.
     * 
     * @param string|callback|null $callback
     * @return mixed
     */
    public function min(string|callable $callback = null): mixed
    {
        if ($callback === null) {
            $callback = fn ($item) => $item;
        } else if (is_string($callback)) {
            $segments = $this->explodeKey($callback);
            $callback = function ($item) use ($segments) {
                $childItem = extract_item($item, $segments);
                return $item !== $childItem ? $childItem : null;
            };
        }

        return $this->reduce(function ($min, $value, $key) use ($callback) {
            $value = $callback($value, $key);
            return $min === null || $value < $min ? $value : $min;
        });
    }

    /**
     * Find median value in the collection.
     * 
     * @param string|callback|null $callback
     * @return float|int|null
     */
    public function median(string|callable $callback = null): float|int|null
    {
        return $this->collect()->median($callback);
    }

    /**
     * Get the mode of a given key.
     * 
     * @param string|int|null $key
     * @param mixed
     */
    public function mode(string|int $key = null): mixed
    {
        return $this->collect()->mode($key);
    }

    /**
     * Merge the collection with the given items.
     * 
     * @param mixed $item
     * @return static
     */
    public function merge(mixed $item): static
    {
        return $this->passthrough('merge', [$item]);
    }

    /**
     * Recursively merge the collection with the given items.
     * 
     * @param mixed $item
     * @return static
     */
    public function mergeRecursive(mixed $item): static
    {
        return $this->passthrough('mergeRecursive', [$item]);
    }

    /**
     * Run a map over each of the items.
     * 
     * @param callable $callback
     * @return static
     */
    public function map(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * Exchanges all keys with their associated values in a collection.
     * 
     * @return static
     */
    public function flip(): static
    {
        return new static(function () {
            foreach ($this as $key => $value) {
                yield $value => $key;
            }
        });
    }

    /**
     * Run a filter over each of the items.
     * 
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Take the first or last {$limit} items.
     *
     * @param int $limit
     * @return static
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->passthrough('take', [$limit]);
        }

        return new static(function () use ($limit) {
            foreach ($this as $key => $value) {
                if ($limit > 0) {
                    yield $key => $value;
                    $limit--;
                } else {
                    break;
                }
            }
        });
    }

    /**
     * Union the collection with the given items.
     * 
     * @param mixed $item
     * @return static
     */
    public function union(mixed $item): static
    {
        return $this->passthrough('union', [$item]);
    }

    /**
     * Return unique items from the collection using strict comparison.
     * 
     * @param callable|string|null $key
     * @return static
     */
    public function uniqueStrict(callable|string $key = null): static
    {
        return $this->unique($key, true);
    }

    /**
     * Pad collection to the specified length with a value.
     * 
     * @param int $size
     * @param mixed $value
     * @return static
     */
    public function pad(int $size, mixed $value): static
    {
        if ($size < 0) {
            return $this->passthrough('pad', [$size, $value]);
        }

        return new static(function () use ($size, $value) {
            $yielded = 0;

            foreach ($this as $index => $item) {
                yield $index => $item;

                $yielded++;
            }

            while ($yielded++ < $size) {
                yield $value;
            }
        });
    }

    /**
     * Get the values of a given key.
     * 
     * @param string|int $value
     * @param string|int|null $key
     * @return static
     */
    public function pluck(string|int $value, string|int $key = null): static
    {
        return new static(function () use ($value, $key) {
            $value =  $this->explodeKey($value);
            $key =  $key === null ? $key : $this->explodeKey($key);
            foreach ($this as $index => $item) {
                $itemValue = extract_item($item, $value);
                if ($key === null) {
                    if ($itemValue !== $item) {
                        yield $itemValue;
                    }
                } else {
                    $itemKey = extract_item($item, $key);
                    if ($itemValue !== $item && $itemKey !== $item) {
                        if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                            $itemKey = (string) $itemKey;
                        }
                        yield $itemKey => $itemValue;
                    }
                }
            }
        });
    }

    /**
     * Get first value by callback in a collection.
     * 
     * @param callable|null $callback
     * @param mixed $default
     * @return mixed
     */
    public function first(callable $callback = null, mixed $default = null): mixed
    {
        $iterator = $this->getIterator();

        if ($callback === null) {
            if (!$iterator->valid()) {
                return $this->getValue($default);
            }
            return $iterator->current();
        }

        foreach ($iterator as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return $this->getValue($default);
    }

    /**
     * Get first value by callback in a collection but throw an exception if no matching items exist.
     * 
     * @param callable|null $callback
     * @return mixed
     */
    public function firstOrFail(callable $callback = null): mixed
    {
        $first = $this->first($callback, INF);
        if ($first === INF) {
            throw new ItemNotFoundException;
        }
        return $first;
    }

    /**
     * Get last value by callback in a collection.
     * 
     * @param callable|null $callback
     * @param mixed $default
     * @return mixed
     */
    public function last(callable $callback = null, mixed $default = null): mixed
    {
        $result = $placeholder = INF;
        foreach ($this as $key => $value) {
            if (is_null($callback) || $callback($value, $key)) {
                $result = $value;
            }
        }
        return $result === $placeholder ? $this->getValue($default) : $result;
    }

    /**
     * Return an collection with elements in reverse order.
     * 
     * @param bool $preserveKeys
     * @return static
     */
    public function reverse(bool $preserveKeys = false): static
    {
        return $this->passthrough('reverse', [$preserveKeys]);
    }

    /**
     * Pick one or more random values out of a collection.
     * 
     * @return int $num
     * @return bool $preserveKeys
     * @return mixed
     */
    public function random(int $num = 1, bool $preserveKeys = false): mixed
    {
        $result = Arr::random($this->items, $num, $preserveKeys);
        if (is_array($result)) {
            return new static($result);
        }
        return $result;
    }

    /**
     * Computes the difference of collection.
     * 
     * @param iterable ...$items
     * @param static
     */
    public function diff(iterable ...$items): static
    {
        return $this->passthrough('diff', $items);
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items.
     * 
     * @param iterable ...$items
     * @return static
     */
    public function diffAssoc(iterable ...$items): static
    {
        return $this->passthrough('diffAssoc', $items);
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     * 
     * @param iterable ...$items
     * @return static
     */
    public function diffKeys(iterable ...$items): static
    {
        return $this->passthrough('diffKeys', $items);
    }

    /**
     * Retrieve duplicate items from the collection.
     * 
     * @param string|callable|null $callback
     * @param bool $strict
     * @return static
     */
    public function duplicates(string|callable $callback = null, bool $strict = false): static
    {
        return $this->passthrough('duplicates', [$callback, $strict]);
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     * 
     * @param mixed $callback
     * @param bool $strict
     * @return string|int|false
     */
    public function search(mixed $callback, bool $strict = false): string|int|false
    {
        if (!is_callable($callback)) {
            $operator = $strict === true ? '===' : '==';
            $callback = function ($item) use ($callback, $operator) {
                return $this->compare($callback, $operator, $item);
            };
        }
        foreach ($this as $key => $item) {
            if ($callback($item, $key)) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Shuffle the items in the collection.
     *
     * @param int|null $seed
     * @return static
     */
    public function shuffle($seed = null): static
    {
        return $this->passthrough('shuffle', [$seed]);
    }

    /**
     * Create chunks representing a "sliding window" view of the items in the collection.
     *
     * @param int $size
     * @param int $step
     * @return static
     */
    public function sliding(int $size = 2, int $step = 1): static
    {
        return new static(function () use ($size, $step) {
            $chunk = [];
            foreach ($this as $item) {
                $chunk[] = $item;
                if (count($chunk) === $size) {
                    yield new static($chunk);
                    $chunk = array_slice($chunk, $step);
                }
            }
        });
    }

    /**
     * Skip the first items by count.
     * 
     * @param int $count
     * @return static
     */
    public function skip(int $count): static
    {
        return new static(function () use ($count) {
            $numberOfLoops = 0;
            foreach ($this as $key => $value) {
                if ($numberOfLoops < $count) {
                    $numberOfLoops++;
                } else {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Extract a slice of the collection.
     * 
     * @param int $start
     * @param int|null $length
     * @param bool $preserveKeys
     * @return static
     */
    public function slice(int $start, int $length = null, bool $preserveKeys = false): static
    {
        if ($start < 0 && $length < 0) {
            return $this->passthrough('slice', [$start, $length, $preserveKeys]);
        }

        $result = $this->skip($start);
        return $length === null ? $result : $result->take($length);
    }

    /**
     * Split a collection into a certain number of groups.
     * 
     * @param int $numberOfGroups
     * @return static
     */
    public function split(int $numberOfGroups): static
    {
        return $this->passthrough('split', [$numberOfGroups]);
    }

    /**
     * Check collection is empty.
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !$this->getIterator()->valid();
    }

    /**
     * Concatenate values of a given key as a string.
     * 
     * @param string $glue
     * @param string|callable|null $key
     * @return string
     */
    public function implode(string $glue, string|callable $key = null): string
    {
        return $this->collect()->implode($glue, $key);
    }

    /**
     * Intersect the collection with the given items.
     * 
     * @param mixed $item
     * @return static
     */
    public function intersect(mixed $item): static
    {
        return $this->passthrough('intersect', [$item]);
    }

    /**
     * Intersect the collection with the given items by key.
     * 
     * @param mixed $item
     * @return static
     */
    public function intersectByKeys(mixed $item): static
    {
        return $this->passthrough('intersectByKeys', [$item]);
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param int $depth
     * @return static
     */
    public function flatten(int $depth = PHP_INT_MAX): static
    {
        return (new static(function () use ($depth) {
            foreach ($this as $item) {
                if (!is_array($item)) {
                    yield $item;
                } elseif ($depth === 1) {
                    yield from $item;
                } else {
                    yield from (new static($item))->flatten($depth - 1);
                }
            }
        }))->values();
    }

    /**
     * Chunk the collection into chunks of the given size.
     *
     * @param int $size
     * @return static
     */
    public function chunk(int $size): static
    {
        if ($size <= 0) {
            return new static([]);
        }

        return new static(function () use ($size) {
            $chunk = [];
            foreach ($this as $key => $item) {
                $chunk[$key] = $item;
                if (count($chunk) === $size) {
                    yield new static($chunk);
                    $chunk = [];
                }
            }
            if (!empty($chunk)) {
                yield new static($chunk);
            }
        });
    }

    /**
     * Chunk the collection into chunks with a callback.
     * 
     * @param callable $callback
     * @return static
     */
    public function chunkWhile(callable $callback): static
    {
        return new static(function () use ($callback) {
            $chunk = null;
            foreach ($this as $key => $item) {
                if ($chunk === null) {
                    $chunk = new Collection([$key => $item]);
                    continue;
                }
                if ($callback($item, $key, $chunk)) {
                    $chunk->put($key, $item);
                } else {
                    yield $chunk;
                    $chunk = new Collection([$key => $item]);
                }
            }

            if ($chunk->isNotEmpty()) {
                yield $chunk;
            }
        });
    }

    /**
     * Sort the lazy collection.
     * 
     * @param bool $descending
     * @param int $options
     * @return static
     */
    public function sort(bool $descending = false, int $options = SORT_REGULAR): static
    {
        return $this->passthrough('sort', [$options, $descending]);
    }

    /**
     * Sort the lazy collection in descending order.
     * 
     * @param int $options
     * @return static
     */
    public function sortDesc(int $options = SORT_REGULAR): static
    {
        return $this->sort(true, $options);
    }

    /**
     * Sort the collection using the given callback.
     * 
     * @param string|callable $callback
     * @param bool $descending
     * @param int $options
     * @return static
     */
    public function sortBy(string|callable $callback, bool $descending = false, int $options = SORT_REGULAR): static
    {
        return $this->passthrough('sortBy', [$callback, $descending, $options]);
    }

    /**
     * Sort the collection in descending order using the given callback.
     * 
     * @param string|callable $callback
     * @param int $options
     * @return static
     */
    public function sortByDesc(string|callable $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, true, $options);
    }

    /**
     * Sort the collection using the given callback but the priority is reduced.
     * 
     * @param string|callable $callback
     * @param bool $descending
     * @param int $options
     * @return static
     */
    public function thenBy(string|callable $callback, bool $descending = false, int $options = SORT_REGULAR): static
    {
        return $this->passthrough('thenBy', [$callback, $descending, $options]);
    }

    /**
     * Sort the collection in descending order using the given callback but the priority is reduced.
     * 
     * @param string|callable $callback
     * @param bool $descending
     * @param int $options
     * @return static
     */
    public function thenByDesc(string|callable $key, int $options = SORT_REGULAR): static
    {
        return $this->passthrough('thenBy', [$key, true, $options]);
    }

    /**
     * Sort the collection keys.
     * 
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false): static
    {
        return $this->passthrough('sortKeys', [$options, $descending]);
    }

    /**
     * Sort the collection keys in descending order.
     * 
     * @param int $options
     * @return static
     */
    public function sortKeysDesc(int $options = SORT_REGULAR): static
    {
        return $this->sortKeys($options, true);
    }

    /**
     * Group an associative array by a field or using a callback.
     * 
     * @param string|callable|array $keys
     * @param bool $preserveKeys
     * @return static
     */
    public function groupBy(string|callable|array $keys, bool $preserveKeys = false): static
    {
        return $this->passthrough('groupBy', [$keys, $preserveKeys]);
    }

    /**
     * Get the items with the specified keys.
     * 
     * @param string|int ...$keys
     * @return static
     */
    public function only(string|int ...$keys): static
    {
        return new static(function () use ($keys) {
            if (is_null($keys)) {
                yield from $this;
            } else {
                $keys = Arr::flip($keys);
                foreach ($this as $key => $value) {
                    if (isset($keys[$key])) {
                        yield $key => $value;
                        unset($keys[$key]);
                        if (empty($keys)) {
                            break;
                        }
                    }
                }
            }
        });
    }

    /**
     * Join all items from the collection using a string. The final items can use a separate glue string.
     * 
     * @param string $glue
     * @param string|null $finalGlue
     * @return string
     */
    public function join(string $glue, string $finalGlue = null): string
    {
        return $this->collect()->join($glue, $finalGlue);
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
     * Pass this lazy collection through a method on the collection class.
     *
     * @param string $method
     * @param array $params
     * @return static
     */
    protected function passthrough(string $method, array $params): static
    {
        return new static(function () use ($method, $params) {
            yield from $this->collect()->$method(...$params);
        });
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
