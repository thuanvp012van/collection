<?php

namespace Penguin\Component\Collection;

use Penguin\Component\Collection\Traits\EnumeratesValues;
use Penguin\Component\Plug\Plug;
use ArrayIterator;
use ReflectionProperty;
use Traversable;

class Collection implements Enumerable
{
    use Plug;
    use EnumeratesValues;

    protected array $items;

    protected array $sorts = [];

    public function __construct(mixed $items = [])
    {
        $this->items = $this->getArrayableItems($items);
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
        if (isset($this->items[$key])) {
            return $this->items[$key];
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
        foreach ($keys as $key) {
            if (!isset($this->items[$key])) {
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
            if (isset($this->items[$key])) {
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
     * @param string|int $key
     * @return static
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
     * Get all values in a collection.
     * 
     * @return static
     */
    public function values(): static
    {
        return new static(Arr::values($this->items));
    }

    /**
     * Calculate the sum of values in a collection.
     * 
     * @param string|int|callable $key
     * @return int|float
     */
    public function sum(string|int|callable $key = null): int|float
    {
        if ($key === null) {
            return array_sum($this->items);
        }

        $sum = 0;
        $term = 0;
        if (is_callable($key)) {
            foreach ($this->items as $item) {
                if (is_numeric($term = $key($item))) {
                    $sum += $term;
                }
            }
            return $sum;
        }

        $segments = $this->extractKey($key);
        foreach ($this->items as $item) {
            if (is_array($item) && is_numeric($term = $this->getItemRecursive($item, $segments))) {
                $sum += $term;
            }
        }

        return $sum;
    }

    /**
     * Counts all elements in a collection.
     * 
     * @param string|int|callable $key
     * @return int
     */
    public function count(string|int|callable $key = null): int
    {
        if ($key === null) {
            return count($this->items);
        }

        $count = 0;
        if (is_callable($key)) {
            foreach ($this->items as $item) {
                if ($key($item)) {
                    $count++;
                }
            }
            return $count;
        }

        $segments = $this->extractKey($key);
        foreach ($this->items as $item) {
            if ($item !== $this->getItemRecursive($item, $segments)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Count the number of items in the collection by a field or using a callback.
     * 
     * @param callable $callback
     * @return static
     */
    public function countBy(callable $callback = null): static
    {
        return new static(Arr::countBy($callback, $this->items));
    }

    /**
     * Get the average value of a given key.
     * 
     * @param string|int $key
     * @return int|float|null
     */
    public function avg(string|int $key = null): int|float|null
    {
        $count = $this->count($key);
        return $count > 0 ? $this->sum($key) / $this->count($key) : null;
    }

    public function max(string|callable $callback = null): mixed
    {
        if ($callback === null) {
            return Arr::max($this->items);
        }

        if (is_string($callback)) {
            $segments = $this->extractKey($callback);
            $callback = function ($item) use ($segments) {
                $childItem = $this->getItemRecursive($item, $segments);
                return $item !== $childItem ? $childItem : null;
            };
        }

        $max = null;
        $this->each(function ($item, $key) use (&$max, &$callback) {
            if ($max === null) {
                $max = $callback($item, $key);
            } else {
                $item = $callback($item, $key);
                $max = $item > $max ? $item : $max;
            }
        });
        return $max;
    }

    public function min(string|callable $callback = null): mixed
    {
        if ($callback === null) {
            return Arr::min($this->items);
        }

        if (is_string($callback)) {
            $segments = $this->extractKey($callback);
            $callback = function ($item) use ($segments) {
                $childItem = $this->getItemRecursive($item, $segments);
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
     * @param string|int $key
     * @param mixed
     */
    public function mode(string|int $key = null): mixed
    {
        if ($key !== null) {
            $segments = $this->extractKey($key);
            $values = [];
            foreach ($this->items as $item) {
                $childItem = $this->getItemRecursive($item, $segments);
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
     * Convert all items to array.
     * 
     * @return array
     */
    public function toArray(): array
    {
        return $this->toArrayRecursive($this->items);
    }

    /**
     * Return the JSON representation of a collection.
     * 
     * @return int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Transform each item in the collection using a callback.
     * 
     * @param callable $callback
     * @return static
     */
    public function transform(callable $callback): static
    {
        $this->items = Arr::map($callback, $this->items);
        return $this;
    }

    /**
     * Return unique items from the collection.
     * 
     * @param callable|string $key
     * @param bool $strict
     * @return static
     */
    public function unique(callable|string $key = null, bool $strict = false): static
    {
        if ($key === null) {
            return new static(Arr::unique($this->items, SORT_NUMERIC));
        }

        $exists = [];
        if (is_string($key)) {
            $segments = $this->extractKey($key);
            $callback = function ($item) use (&$segments, &$exists, $strict) {
                $childItem = $this->getItemRecursive($item, $segments);
                if (in_array($childItem, $exists, $strict)) {
                    return false;
                }
                $exists[] = $childItem;
                return true;
            };
        } else {
            $callback = function ($item, $index) use ($key, &$exists, $strict) {
                $value = $key($item, $index);
                if (in_array($value, $exists, $strict)) {
                    return false;
                }
                $exists[] = $value;
                return true;
            };
        }
        return $this->filter(function ($item, $index) use ($callback) {
            return $callback($item, $index);
        });
    }

    /**
     * Return unique items from the collection using strict comparison.
     * 
     * @param callable|string $key
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
     * Partition the collection into two arrays using the given callback or key.
     * 
     * @param callable $callback
     * @return static
     */
    public function partition(callable $callback): static
    {
        $passed = [];
        $failed = [];
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                $passed[] = $value;
            } else {
                $failed[] = $value;
            }
        }
        return new static([new static($passed), new static($failed)]);
    }

    /**
     * Get first value by callback in a collection.
     * 
     * @param callable $callback
     * @param mixed $default
     * @return mixed
     */
    public function first(callable $callback = null, mixed $default = null): mixed
    {
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * Get the first item by the given key value pair.
     * 
     * @param string|int $key
     * @param mixed $value
     * @param string $operator
     * @return mixed
     */
    public function firstWhere(string|int $key, mixed $value, string $operator = '='): mixed
    {
        $this->checkValidOperator($operator);
        $segments = $this->extractKey($key);
        foreach ($this->items as $item) {
            $childItem = $this->getItemRecursive($item, $segments);
            if ($childItem === $item) {
                continue;
            }
            if ($this->compare($childItem, $operator, $value)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Get first value by callback in a collection but throw an exception if no matching items exist.
     * 
     * @param callable $callback
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
     * @param callable $callback
     * @param mixed $default
     * @return mixed
     */
    public function last(callable $callback = null, mixed $default = null): mixed
    {
        return Arr::last($this->items, $callback, $default);
    }

    /**
     * Execute a callback over each item.
     * 
     * @param callable $callback
     * @return $this
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }
        return $this;
    }

    /**
     * Execute a callback over each nested chunk of items.
     * 
     * @param callable $callback
     * @return $this
     */
    public function eachSpread(callable $callback): static
    {
        return $this->each(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;
            return $callback(...$chunk);
        });
    }

    /**
     * Determine if all items pass the given truth test.
     * 
     * @param string|int|callable $key
     * @param mixed $value
     * @param string $operator
     * @param bool
     */
    public function every(string|int|callable $key, mixed $value = null, string $operator = '='): bool
    {
        if (is_callable($key)) {
            $callback = $key;
            foreach ($this->items as $key => $item) {
                if (!$callback($item, $key)) {
                    return false;
                }
            }
        } else {
            $this->checkValidOperator($operator);
            if (str_contains($key, '.')) {
                $segments = $this->extractKey($key);
                foreach ($this->items as $item) {
                    $childItem = $this->getItemRecursive($item, $segments);
                    if ($childItem !== $item && !$this->compare($childItem, $operator, $value)) {
                        return false;
                    }
                }
            } else {
                foreach ($this->items as $item) {
                    if (isset($item[$key]) && !$this->compare($item[$key], $operator, $value)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Return an collection with elements in reverse order.
     * 
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

    public function diffAssoc(iterable ...$items): static
    {
        return new static(Arr::diffAssoc($this->items, ...$items));
    }

    public function diffKeys(iterable ...$items): static
    {
        return new static(Arr::diffKeys($this->items, ...$items));
    }

    // public function duplicates(callable|string $key = null, bool $strict = false): static
    // {
        
    // }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     * 
     * @param int|string|callable $callback
     * @param bool $strict
     * @return int|string|false
     */
    public function search(int|string|callable $callback, bool $strict = false): int|string|false
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
     * @param int $seed
     * @return static
     */
    public function shuffle(int $seed = null): static
    {
        return new static(Arr::shuffle($this->items, $seed));
    }

    /**
     * Extract a slice of the collection.
     * 
     * @param int $start
     * @param int $length
     * @param bool $preserveKeys
     * @return static
     */
    public function slice(int $start, int $length = null, bool $preserveKeys = false): static
    {
        return new static(Arr::slice($this->items, $start, $length, $preserveKeys));
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
     * Check collection is empty.
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check collection is not empty.
     * 
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param int $depth
     * @return static
     */
    public function flatten(int $depth = 1000): static
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

        $chunks = [];
        foreach (array_chunk($this->items, $size, true) as &$chunk) {
            $chunks[] = new static($chunk);
        }
        return new static($chunks);
    }

    public function chunkWhile(callable $callback)
    {

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
     * @return static|false
     */
    public function combine(iterable $values): static|false
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
            $segments = $this->extractKey($key);
            foreach ($this->items as $item) {
                $childItem = $this->getItemRecursive($item, $segments);
                if ($childItem !== $item && $this->compare($childItem, $operator, $value)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Determine if an item exists using strict comparison.
     * 
     * @param mixed $key
     * @param mixed $value
     * @return bool
     */
    public function containsStrict(mixed $key, mixed $value = null): bool
    {
        return $this->contains($value, $key, true);
    }

    /**
     * Replace the collection items with the given items.
     * 
     * @param mixed $items
     * @return static
     */
    public function replace(mixed $items): static
    {
        return new static(Arr::replace($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Recursively replace the collection items with the given items.
     * 
     * @param mixed $items
     * @param static
     */
    public function replaceRecursive(mixed $items): static
    {
        return new static(Arr::replaceRecursive($this->items, $this->getArrayableItems($items)));
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
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public function sort(int $options = SORT_REGULAR, bool $descending = false): static
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
            $segments = $this->extractKey($callback);
            $callback = function ($item) use ($segments) {
                $childItem = $this->getItemRecursive($item, $segments);
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

    public function groupBy(string|callable ...$keys): static
    {
    }

    public function only(string|int ...$keys): static
    {
        return new static(Arr::only($this->items, ...$keys));
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->items[$offset];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __serialize(): array
    {
        return $this->items;
    }

    public function __unserialize(array $data): void
    {
        $this->items = $data;
    }

    /**
     * Get recursive item in array.
     * 
     * @return array $array
     * @return array $segments
     * @return mixed
     */
    protected function getItemRecursive(array &$array, array $segments): mixed
    {
        $result = null;
        $tmp = null;
        foreach ($array as $key => $item) {
            if ($key === $segments[0]) {
                if (is_array($item) && count($segments) > 1) {
                    $tmp = $this->getItemRecursive($item, array_slice($segments, 1));
                    $result = $item === $tmp ? null : $tmp;
                } else {
                    $result = $item;
                }
                break;
            }
            $result = null;
        }
        return $result === null ? $array : $result;
    }

    /**
     * Convert all items to array.
     * 
     * @param array $items
     * @return array
     */
    protected function toArrayRecursive(array $items): array
    {
        $result = [];
        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $result[$key] = $this->toArrayRecursive($item);
            } else {
                $result[$key] = $item instanceof Collection ? $item->toArray() : $item;
            }
        }
        return $result;
    }

    protected function setSort(Collection $collection, callable $callback, bool $append = false): void
    {
        $reflectionProperty = new ReflectionProperty($collection, 'sorts');
        $sorts = $append ? $this->sorts : [];
        $sorts[] = $callback;
        $reflectionProperty->setValue($collection, $sorts);
    }
}
