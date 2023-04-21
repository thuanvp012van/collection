<?php

namespace Penguin\Component\Collection;

use Penguin\Component\Collection\Traits\EnumeratesValues;
use Penguin\Component\Plug\Plug;
use BadMethodCallException;
use ReflectionProperty;
use ArrayIterator;
use ArrayAccess;
use Traversable;

class Collection implements ArrayAccess, Enumerable
{
    use Plug;
    use EnumeratesValues;

    protected array $items;

    public function __construct(mixed $items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * Create a new collection instance.
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
        return new static(range($start, $end, $step));
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
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }
        return $this->getValue($default);
    }

    /**
     * Determines if a given key exists in the collection.
     * 
     * @param string|int ...$keys
     * @return bool
     */
    public function has(string|int ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->items)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determines whether any of the given keys exist in the collection.
     * 
     * @param string|int ...$keys
     * @return bool
     */
    public function hasAny(string|int ...$keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->items)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Push one or more items onto the end of the collection.
     *
     * @param mixed ...$values
     * @return $this
     */
    public function push(mixed ...$values): static
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }
        return $this;
    }

    /**
     * Push an item onto the beginning of the collection.
     * 
     * @param mixed $value
     * @param string|int|null $key
     * @return $this
     */
    public function prepend(mixed $value, string|int $key = null): static
    {
        Arr::prepend($this->items, $value, $key);
        return $this;
    }

    /**
     * Put an item in the collection by key.
     * 
     * @param string|int $key
     * @param mixed $value
     * @return $this
     */
    public function put(string|int $key, mixed $value): static
    {
        Arr::put($this->items, $key, $value);
        return $this;
    }

    /**
     * Get and remove the last N items from the collection.
     * 
     * @param int $count
     * @return mixed
     */
    public function pop(int $count = 1): mixed
    {
        if ($count === 1) {
            return array_pop($this->items);
        }

        if ($this->isEmpty()) {
            return new static;
        }

        $results = [];
        foreach (range(1, Arr::min($count, $this->count())) as $items) {
            $results[] = array_pop($this->items);
        }

        return new static($results);
    }

    /**
     * Remove an item from the collection by key.
     * 
     * @param string|int ...$keys
     * @return $this
     */
    public function forget(string|int ...$keys): static
    {
        Arr::forget($this->items, ...$keys);
        return $this;
    }

    /**
     * Get all item of the collection except for a specified array of keys.
     * 
     * @param string|int ...$keys
     * @return static
     */
    public function except(string|int ...$keys): static
    {
        return new static(Arr::except($this->items, ...$keys));
    }

    /**
     * Get and remove an item from the collection.
     * 
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string|int $key, mixed $default = null): mixed
    {
        return Arr::pull($this->items, $key, $default);
    }

    /**
     * Get all of the items in the collection.
     * 
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get all keys in a collection.
     * 
     * @return static
     */
    public function keys(): static
    {
        return new static(Arr::keys($this->items));
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
            $keyBy = fn($item) => extract_item($item, $segments);
        }
        $results = [];
        foreach ($this->items as $key => $item) {
            $results[$keyBy($item, $key)] = $item;
        }
        return new static($results);
    }

    /**
     * Get all values in a collection.
     * 
     * @return static
     */
    public function values(): static
    {
        return new static(Arr::values($this->items));
    }

    /**
     * Count the number of items in the collection by a field or using a callback.
     * 
     * @param callable|null $callback
     * @return static
     */
    public function countBy(callable $callback = null): static
    {
        return new static(Arr::countBy($callback, $this->items));
    }

    /**
     * Get the average value of a given key.
     * 
     * @param string|int|null $key
     * @return int|float|null
     */
    public function avg(string|int $key = null): int|float|null
    {
        $count = $this->count($key);
        return $count > 0 ? $this->sum($key) / $this->count($key) : null;
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
            return Arr::max($this->items);
        }

        if (is_string($callback)) {
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
            return Arr::min($this->items);
        }

        if (is_string($callback)) {
            $segments = $this->explodeKey($callback);
            $callback = function ($item) use ($segments) {
                $childItem = extract_item($item, $segments);
                return $item !== $childItem ? $childItem : null;
            };
        }

        $min = null;
        $this->each(function ($item, $key) use (&$min, &$callback) {
            if ($min === null) {
                $min = $callback($item, $key);
            } else {
                $item = $callback($item, $key);
                $min = $item > $min ? $min : $item;
            }
        });
        return $min;
    }

    /**
     * Find median value in the collection.
     * 
     * @param string|callback|null $callback
     * @return float|int|null
     */
    public function median(string|callable $callback = null): float|int|null
    {
        if ($callback === null) {
            $values = $this->sort();
        } else {
            $values = $this->map($callback)->sort();
        }

        $count = $this->count();
        $middle = (int)($count / 2);

        if ($count % 2) {
            return $values->get($middle);
        }

        return ($values->get($middle - 1) + $values->get($middle)) / 2;
    }

    /**
     * Get the mode of a given key.
     * 
     * @param string|int|null $key
     * @param mixed
     */
    public function mode(string|int $key = null): mixed
    {
        if ($key !== null) {
            $segments = $this->explodeKey($key);
            $values = [];
            foreach ($this->items as $item) {
                $childItem = extract_item($item, $segments);
                if ($childItem !== $item) {
                    $values[] = $childItem;
                }
            }
        } else {
            $values = &$this->items;
        }
        $countValues = array_count_values($values);
        return array_keys($countValues, max($countValues));
    }

    /**
     * Merge the collection with the given items.
     * 
     * @param mixed $item
     * @return static
     */
    public function merge(mixed $item): static
    {
        return new static(Arr::merge($this->items, $this->getArrayableItems($item)));
    }

    /**
     * Recursively merge the collection with the given items.
     * 
     * @param mixed $item
     * @return static
     */
    public function mergeRecursive(mixed $item): static
    {
        return new static(Arr::mergeRecursive($this->items, $this->getArrayableItems($item)));
    }

    /**
     * Run a map over each of the items.
     * 
     * @param callable $callback
     * @return static
     */
    public function map(callable $callback): static
    {
        return new static(Arr::map($callback, $this->items));
    }

    /**
     * Exchanges all keys with their associated values in a collection.
     * 
     * @return static
     */
    public function flip(): static
    {
        return new static(Arr::flip($this->items));
    }

    /**
     * Transform each item in the collection using a callback.
     * 
     * @param callable $callback
     * @return $this
     */
    public function transform(callable $callback): static
    {
        $this->items = Arr::map($callback, $this->items);
        return $this;
    }

    /**
     * Take the first or last {$limit} items.
     *
     * @param int $limit
     * @return static
     */
    public function take(int $limit): static
    {
        return $limit < 0 ? $this->slice($limit, abs($limit)) : $this->slice(0, $limit);
    }

    /**
     * Union the collection with the given items.
     * 
     * @param mixed $item
     * @return static
     */
    public function union(mixed $item): static
    {
        return new static($this->items + $this->getArrayableItems($item));
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
        return new static(Arr::pad($this->items, $size, $value));
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
        return new static(Arr::pluck($this->items, $value, $key));
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
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * Get first value by callback in a collection but throw an exception if no matching items exist.
     * 
     * @param callable|null $callback
     * @return mixed
     */
    public function firstOrFail(callable $callback = null): mixed
    {
        $first = Arr::first($this->items, $callback, INF);
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
        return Arr::last($this->items, $callback, $default);
    }

    /**
     * Get a lazy collection for the items in this collection.
     *
     * @return \Penguin\Component\Collection\LazyCollection
     */
    public function lazy(): LazyCollection
    {
        return new LazyCollection($this->items);
    }

    /**
     * Return an collection with elements in reverse order.
     * 
     * @param bool $preserveKeys
     * @return static
     */
    public function reverse(bool $preserveKeys = false): static
    {
        return new static(array_reverse($this->items, $preserveKeys));
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
        return new static(Arr::diff($this->items, ...$items));
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items.
     * 
     * @param iterable ...$items
     * @return static
     */
    public function diffAssoc(iterable ...$items): static
    {
        return new static(Arr::diffAssoc($this->items, ...$items));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     * 
     * @param iterable ...$items
     * @return static
     */
    public function diffKeys(iterable ...$items): static
    {
        return new static(Arr::diffKeys($this->items, ...$items));
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
        if (is_string($callback)) {
            $segments = $this->explodeKey($callback);
            $callback = function ($item) use ($segments) {
                return extract_item($item, $segments);
            };
        }
        $items = $callback === null ? $this : $this->map($callback);
        $uniqueItems = $items->unique(null, $strict);
        $operator = $strict ? '===' : '==';
        $results = [];
        foreach ($items as $key => $item) {
            if ($uniqueItems->isNotEmpty() && $this->compare($item, $operator, $uniqueItems->first())) {
                $uniqueItems->shift();
            } else {
                $results[$key] = $item;
            }
        }
        return new static($results);
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     * 
     * @param int|string|callable $callback
     * @param bool $strict
     * @return int|string|false
     */
    public function search(mixed $callback, bool $strict = false): int|string|false
    {
        if (is_callable($callback)) {
            foreach ($this->items as $key => $item) {
                if ($callback($item, $key)) {
                    return $key;
                }
            }
            return false;
        }
        return Arr::search($callback, $this->items, $strict);
    }

    /**
     * Get and remove the first N items from the collection.
     * 
     * @param int $count
     * @return mixed
     */
    public function shift(int $count = 1): mixed
    {
        if ($count === 1) {
            return array_shift($this->items);
        }

        if ($this->isEmpty()) {
            return new static;
        }

        $results = [];
        $totalItems = $this->count();
        for ($i = 0; $i < min($count, $totalItems); $i++) {
            array_push($results, array_shift($this->items));
        }
        return new static($results);
    }

    /**
     * Shuffle the items in the collection.
     * 
     * @param int|null $seed
     * @return static
     */
    public function shuffle(int $seed = null): static
    {
        return new static(Arr::shuffle($this->items, $seed));
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
        $chunks = floor(($this->count() - $size) / $step) + 1;
        return static::times($chunks, fn ($number) => $this->slice(($number - 1) * $step, $size));
    }

    /**
     * Skip the first items by count.
     * 
     * @param int $count
     * @return static
     */
    public function skip(int $count): static
    {
        return $this->slice($count);
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
        return new static(Arr::slice($this->items, $start, $length, $preserveKeys));
    }

    /**
     * Split a collection into a certain number of groups.
     * 
     * @param int $numberOfGroups
     * @return static
     */
    public function split(int $numberOfGroups): static
    {
        if ($this->isEmpty()) {
            return new static;
        }

        $groups = new static;

        $groupSize = floor($this->count() / $numberOfGroups);

        $remain = $this->count() % $numberOfGroups;

        $start = 0;

        for ($i = 0; $i < $numberOfGroups; $i++) {
            $size = $groupSize;

            if ($i < $remain) {
                $size++;
            }

            if ($size) {
                $groups->push(new static(Arr::slice($this->items, $start, $size)));
                $start += $size;
            }
        }
        return $groups;
    }

    /**
     * Check collection is empty.
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
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
        if ($key === null) {
            return implode($glue, $this->items);
        }

        if (is_string($key)) {
            $segments = $this->explodeKey($key);
            $key = function ($item) use (&$segments) {
                return extract_item($item, $segments);
            };
        }
        return implode($glue, $this->map($key)->all());
    }

    /**
     * Intersect the collection with the given items.
     * 
     * @param mixed $item
     * @return static
     */
    public function intersect(mixed $item): static
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($item)));
    }

    /**
     * Intersect the collection with the given items by key.
     * 
     * @param mixed $item
     * @return static
     */
    public function intersectByKeys(mixed $item): static
    {
        return new static(array_intersect_key($this->items, $this->getArrayableItems($item)));
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param int $depth
     * @return static
     */
    public function flatten(int $depth = PHP_INT_MAX): static
    {
        return new static(Arr::flatten($this->items, $depth));
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
            return new static;
        }

        $chunks = array_chunk($this->items, $size, true);
        return new static(array_map(function ($chunk) {
            return new static ($chunk);
        }, $chunks));
    }

    /**
     * Chunk the collection into chunks with a callback.
     * 
     * @param callable $callback
     * @return static
     */
    public function chunkWhile(callable $callback): static
    {
        $chunks = [];
        foreach ($this->items as $key => $item) {
            if (empty($chunks)) {
                $chunks[] = new static([$key => $item]);
                continue;
            }
            if ($callback($item, $key, end($chunks))) {
                end($chunks)->put($key, $item);
            } else {
                $chunks[] = new static([$key => $item]);
            }
        }
        return new static($chunks);
    }

    /**
     * Collapse the collection of items into a single array.
     * 
     * @return static
     */
    public function collapse(): static
    {
        return new static(Arr::collapse($this->items));
    }

    /**
     * Creates a collection by using this collection for keys and another for its values.
     * 
     * @param iterable $values
     * @return static
     */
    public function combine(iterable $values): static
    {
        return new static(Arr::combine($this->items, $values));
    }

    /**
     * Push all of the given items onto the collection.
     * 
     * @param iterable $items
     * @return static
     */
    public function concat(iterable $items): static
    {
        $result = new static($this->items);
        foreach ($items as $item) {
            $result->push($item);
        }
        return $result;
    }

    /**
     * Determine if an item exists in the collection.
     * 
     * @param mixed $key
     * @param mixed $value
     * @param bool $strict
     * @return bool
     */
    public function contains(mixed $key, mixed $value = null, bool $strict = false): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                $callback = $key;
                foreach ($this->items as $key => $item) {
                    if ($callback($item, $key)) {
                        return true;
                    }
                }
                return false;
            }
            return in_array($key, $this->items, $strict);
        } else {
            $operator = $strict === true ? '===' : '==';
            $segments = $this->explodeKey($key);
            foreach ($this->items as $item) {
                $childItem = extract_item($item, $segments);
                if ($childItem !== $item && $this->compare($childItem, $operator, $value)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Replace the collection items with the given items.
     * 
     * @param iterable $items
     * @return static
     */
    public function replace(iterable $items): static
    {
        return new static(Arr::replace($this->items, $items));
    }

    /**
     * Recursively replace the collection items with the given items.
     * 
     * @param iterable $items
     * @param static
     */
    public function replaceRecursive(iterable $items): static
    {
        return new static(Arr::replaceRecursive($this->items, $items));
    }

    /**
     * Run a filter over each of the items.
     * 
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback): static
    {
        return new static(Arr::where($this->items, $callback));
    }

    /**
     * Sort the collection.
     * 
     * @param bool $descending
     * @param int $options
     * @return static
     */
    public function sort(bool $descending = false, int $options = SORT_REGULAR): static
    {
        $sorted = $this->items;
        Arr::sort($sorted, $descending, $options);
        return new static($sorted);
    }

    /**
     * Sort the collection in descending order.
     * 
     * @param int $options
     * @return static
     */
    public function sortDesc(int $options = SORT_REGULAR): static
    {
        return new static(Arr::sort($this->items, true, $options));
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
        if (is_string($callback)) {
            $segments = $this->explodeKey($callback);
            $callback = function ($item) use ($segments) {
                $childItem = extract_item($item, $segments);
                return $childItem === $item ? null : $childItem;
            };
        }

        $results = [];
        foreach ($this->items as $key => $item) {
            $results[$key] = $callback($item, $key);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        $results = new static($results);
        $this->setSort($results, $callback);
        return $results;
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
        return $this->sortBy($callback, $options);
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
        if (empty($this->sorts)) {
            throw new BadMethodCallException('Can\'t use thenBy method without using sortBy or sortByDesc');
        }

        $sorts = [];
        $index = 0;
        $keys = [];

        if (is_string($callback)) {
            $segments = $this->explodeKey($callback);
            $callback = function ($item) use ($segments) {
                $childItem = extract_item($item, $segments);
                return $childItem === $item ? null : $childItem;
            };
        }

        foreach ($this->items as $key => $item) {
            if ($index === 0) {
                $sorts[] = [$key => $callback($item, $key)];
                $index++;
            } else {
                foreach ($this->sorts as $sort) {
                    if (!in_array($sort($item, $key), $keys, true)) {
                        $keys = [];
                        $sorts[] = [$key => $callback($item, $key)];
                        break;
                    }
                }
            }

            if (empty($keys)) {
                foreach ($this->sorts as $sort) {
                    $keys[] = $sort($item, $key);
                }
            } else {
                $sorts[array_key_last($sorts)][$key] = $callback($item, $key);
            }
        }

        $results = [];
        foreach ($sorts as $key => $items) {
            $descending ? arsort($items, $options) : asort($items, $options);
            foreach (array_keys($items) as $key) {
                $results[$key] = $this->items[$key];
            }
        }

        $results = new static($results);
        $this->setSort($results, $callback, true);
        return $results;
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
        if (empty($this->sorts)) {
            throw new BadMethodCallException('Can\'t use thenByDesc method without using sortBy or sortByDesc');
        }
        return $this->thenBy($key, true, $options);
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
        $sorted = $this->items;
        Arr::sortKeys($sorted, $descending, $options);
        return new static($sorted);
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
        $keys = Arr::wrap($keys);
        $callback = array_shift($keys);
        if (is_string($callback)) {
            $segments = $this->explodeKey($callback);
            $callback = function (array $item) use ($segments) {
                return extract_item($item, $segments);
            };
        }

        $results = [];
        foreach ($this->items as $index => $item) {
            $groupKeys = Arr::wrap($callback($item, $index));
            foreach ($groupKeys as $groupKey) {
                $groupKey = match (true) {
                    is_bool($groupKey) => (int) $groupKey,
                    is_object($groupKey) && method_exists($groupKey, '__toString') => (string) $groupKey,
                    default => $groupKey
                };

                if (!isset($results[$groupKey])) {
                    $results[$groupKey] = new static;
                }

                $results[$groupKey]->offsetSet($preserveKeys ? $index : null, $item);
            }
        }

        $results = new static($results);
        if (!empty($keys)) {
            $results = $results->map(function (Collection $result) use ($keys, $preserveKeys) {
                return $result->groupBy($keys, $preserveKeys);
            });
        }
        return $results;
    }

    /**
     * Get the items with the specified keys.
     * 
     * @param string|int ...$keys
     * @return static
     */
    public function only(string|int ...$keys): static
    {
        return new static(Arr::only($this->items, ...$keys));
    }

    /**
     * Set the item in the collection.
     * 
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Determine if an item exists in the collection.
     * 
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Unset the item in the collection.
     * 
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Get an item in the collection.
     * 
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    /**
     * Get an iterator for the items.
     * 
     * @return ArrayIterator
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
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
        if (empty($finalGlue)) {
            return $this->implode($glue);
        }

        if ($this->count() === 0) {
            return '';
        }

        if ($this->count() === 1) {
            return $this->first();
        }

        $collection = new static($this->items);
        $collection->pop();
        return $collection->implode($glue) . $finalGlue . $this->last();
    }

    /**
     * Add sort function.
     * 
     * @param Collection $collection
     * @param callable $callback
     * @param bool $append
     * @return void
     */
    protected function setSort(Collection $collection, callable $callback, bool $append = false): void
    {
        $reflectionProperty = new ReflectionProperty($collection, 'sorts');
        $sorts = $append ? $this->sorts : [];
        $sorts[] = $callback;
        $reflectionProperty->setValue($collection, $sorts);
    }
}
