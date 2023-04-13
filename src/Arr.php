<?php

namespace Penguin\Component\Collection;

use ArgumentCountError;
use Penguin\Component\Plug\Plug;
use ArrayAccess;
use Closure;
use Countable;
use Traversable;

class Arr
{
    use Plug;

    /**
     * Get an item from an array using 'dot' notation.
     * 
     * @param ArrayAccess|array $array
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(ArrayAccess|array $array, string|int $key, mixed $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return self::getValue($default);
        }

        foreach (self::explodeKey($key) as $segment) {
            if (isset($array[$segment])) {
                $array = $array[$segment];
            } else {
                return self::getValue($default);
            }
        }
        return $array;
    }

    /**
     * Push an item into the end of array.
     * 
     * @param ArrayAccess|array &$array
     * @param mixed $value
     * @return void
     */
    public static function push(ArrayAccess|array &$array, mixed $value): void
    {
        $array[] = $value;
    }

    /**
     * Push an item onto the beginning of an array.
     * 
     * @param array &$array
     * @param mixed $value
     * @param string|int $key
     * @return void
     */
    public static function prepend(array &$array, mixed $value, string|int $key = null): void
    {
        if ($key === null) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }
    }

    /**
     * Put an item in array by key.
     * 
     * @param ArrayAccess|array &$array
     * @param string|int $key
     * @param mixed $value  
     * @return void  
     */
    public static function put(ArrayAccess|array &$array, string|int $key, mixed $value): void
    {
        $array[$key] = $value;
    }

    /**
     * Remove one or many array items from a given array using "dot" notation.
     * 
     * @param ArrayAccess|array &$array
     * @param string|int ...$keys
     * @return void
     */
    public static function forget(ArrayAccess|array &$array, string|int ...$keys): void
    {
        foreach ($keys as $key) {
            if (isset($array[$key])) {
                unset($array[$key]);
            }

            if (str_contains($key, '.')) {
                $value = null;
                $segments = self::explodeKey($key);
                if (!isset($array[$segments[0]])) {
                    return;
                }
                $value = &$array[$segments[0]];
                unset($segments[0]);
                $lastkey = end($segments);
                foreach ($segments as $segment) {
                    if (!isset($value[$segment])) {
                        break;
                    }
                    if ($segment !== $lastkey) {
                        $value = &$value[$segment];
                    }
                }
                unset($value[$lastkey]);
            }
        }
    }

    /**
     * Get all of the given array except for a specified array of keys.
     * 
     * @param ArrayAccess|array &$array
     * @param string|int ...$keys
     * @return void
     */
    public static function except(ArrayAccess|array $array, string|int ...$keys): array
    {
        static::forget($array, ...$keys);
        return $array;
    }

    /**
     * Get and remove item from the array.
     * 
     * @param ArrayAccess|array &$array
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    public static function pull(ArrayAccess|array &$array, string|int $key, mixed $default = null): mixed
    {
        $value = static::get($array, $key, $default);
        static::forget($array, $key);
        return $value;
    }

    /**
     * Return all the keys or a subset of the keys of an array.
     * 
     * @param array $array
     * @return array<int, string|int>
     */
    public static function keys(iterable $array): array
    {
        $array = self::getArrayableItems($array);
        return array_keys($array);
    }

    /**
     * Return all the values of an array.
     * 
     * @param array $array
     * @return array<int, mixed>
     */
    public static function values(iterable $array): array
    {
        $array = self::getArrayableItems($array);
        return array_values($array);
    }

    public static function exists(ArrayAccess|array $array, string|int $key): bool
    {
        return isset($array[$key]);
    }

    public static function count(Countable|iterable $items): int
    {
        if (!$items instanceof Countable) {
            $items = self::getArrayableItems($items);
        }
        return count($items);
    }

    /**
     * Count the number of items in the collection by a field or using a callback.
     * 
     * @param callable $callback
     * @param iterable $array
     * @return static
     */
    public static function countBy(callable $callback = null, iterable $array): array
    {
        $array = self::getArrayableItems($array);
        if ($callback !== null) {
            $array = Arr::map($callback, $array);
        }
        return array_count_values($array);
    }

    /**
     * Computes the difference of arrays.
     * 
     * @param iterable ...$arrays
     * @return array
     */
    public static function diff(iterable ...$arrays): array
    {
        foreach ($arrays as &$array) {
            $array = self::getArrayableItems($array);
        }
        return array_diff(...$array);
    }

    /**
     * Computes the difference of arrays with additional index check.
     * 
     * @param iterable ...$arrays
     * @return array
     */
    public static function diffAssoc(iterable ...$arrays): array
    {
        $arrays = Arr::map(fn (iterable $array) => self::getArrayableItems($array), $arrays);
        return array_diff_assoc(...$arrays);
    }

    /**
     * Computes the difference of arrays using keys for comparison.
     * 
     * @param iterable ...$arrays
     * @return array
     */
    public static function diffKeys(iterable ...$arrays): array
    {
        $arrays = Arr::map(fn (iterable $array) => self::getArrayableItems($array), $arrays);
        return array_diff_key(...$arrays);
    }

    /**
     * Check array is empty.
     * 
     * @param iterable $array
     * @return bool
     */
    public static function empty(iterable $array): bool
    {
        $array = self::getArrayableItems($array);
        return empty($array);
    }

    /**
     * Pad array to the specified length with a value.
     * 
     * @param iterable $array
     * @param int $size
     * @param mixed $value
     * @return array
     */
    public static function pad(iterable $array, int $size, mixed $value): array
    {
        return array_pad(self::getArrayableItems($array), $size, $value);
    }

    /**
     * Pick one or more random keys out of an array.
     * 
     * @param iterable $array
     * @param int $num
     * @return array|string|int
     */
    public static function randomKey(iterable $array, int $num = 1): array|string|int
    {
        return array_rand(self::getArrayableItems($array), $num);
    }

    /**
     * Pick one or more random values out of an array.
     * 
     * @return iterable $array
     * @return int $num
     * @return bool $preserveKeys
     * @return mixed
     */
    public static function random(iterable $array, int $num = 1, bool $preserveKeys = false): mixed
    {
        $array = self::getArrayableItems($array);
        $rand = array_rand($array, $num);
        if (is_array($rand)) {
            $array = array_filter($array, function ($value, $key) use ($rand) {
                return in_array($key, $rand);
            }, ARRAY_FILTER_USE_BOTH);
            return $preserveKeys ? array_values($array) : $array;
        }
        return $array[$rand];
    }

    /**
     * Replace the array items with the given items.
     * 
     * @param iterable $arrays
     * @return array
     */
    public static function replace(iterable ...$arrays): array
    {
        $arrays = Arr::map(fn (iterable $array) => self::getArrayableItems($array), $arrays);
        return array_replace(...$arrays);
    }

    /**
     * Recursively replace the array items with the given items.
     * 
     * @param iterable $arrays
     * @param array
     */
    public static function replaceRecursive(iterable ...$arrays): array
    {
        $arrays = Arr::map(fn (iterable $array) => self::getArrayableItems($array), $arrays);
        return array_replace_recursive(...$arrays);
    }

    /**
     * Get the items with the specified keys.
     * 
     * @param iterable $array
     * @param string|int ...$keys
     * @return static
     */
    public static function only(iterable $array, string|int ...$keys): array
    {
        $array = self::getArrayableItems($array);
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Find highest value.
     * 
     * @param mixed ...$items
     * @return mixed
     */
    public static function max(mixed ...$items): mixed
    {
        return max(...$items);
    }

    /**
     * Find lowest value.
     * 
     * @param mixed ...$items
     * @return mixed
     */
    public static function min(mixed ...$items): mixed
    {
        return min(...$items);
    }

    /**
     * Applies the callback to the elements of the given arrays.
     * 
     * @param callable $callback
     * @param array ...$arrays
     * @param arrray
     */
    public static function map(callable $callback, array $array): array
    {
        $keys = array_keys($array);
        try {
            $items = array_map($callback, $array, $keys);
        } catch (ArgumentCountError) {
            $items = array_map($callback, $array);
        }
        return array_combine($keys, $items);
    }

    /**
     * Merge one or more items.
     * 
     * @param iterable ...$items
     * @return array
     */
    public static function merge(iterable ...$items): array
    {
        foreach ($items as &$item) {
            $item = self::getArrayableItems($item);
        }
        unset($item);
        return (array) array_merge(...$items);
    }

    /**
     * Merge one or more items recursively.
     * 
     * @param iterable ...$items
     * @return array
     */
    public static function mergeRecursive(iterable ...$items): array
    {
        foreach ($items as &$item) {
            $item = self::getArrayableItems($item);
        }
        unset($item);
        return (array) array_merge_recursive(...$items);
    }

    /**
     * Pluck an array of values from an array.
     * 
     * @param iterable $array
     * @param string|int $value
     * @param string|int $key
     * @return array
     */
    public static function pluck(iterable $array, string|int $value, string|int $key = null): array
    {
        list($value, $key) = self::explodeKeys($value, $key);
        $results = [];
        foreach ($array as $item) {
            $itemValue = extract_item($item, $value);
            if ($key === null) {
                if ($itemValue !== $item) {
                    $results[] = $itemValue;
                }
            } else {
                $itemKey = extract_item($item, $key);
                if ($itemValue !== $item && $itemKey !== $item) {
                    if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                        $itemKey = (string) $itemKey;
                    }
                    $results[$itemKey] = $itemValue;
                }
            }
        }
        return $results;
    }

    /**
     * Removes duplicate values from an array.
     * 
     * @param iterable $array
     * @param int $flags
     * @return array
     */
    public static function unique(iterable $array, int $flags = SORT_STRING): array
    {
        $array = self::getArrayableItems($array);
        return array_unique($array, $flags);
    }

    /**
     * Get first value by callback in an array.
     * 
     * @param iterable $array
     * @param callable $callback
     * @param mixed $default
     * @return mixed
     */
    public static function first(iterable $array, callable $callback = null, mixed $default = null): mixed
    {
        $array = self::getArrayableItems($array);
        if ($callback === null) {
            if (empty($array)) {
                return $default;
            }

            foreach ($array as $value) {
                return $value;
            }
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return self::getValue($default);
    }

    /**
     * Get last value by callback in an array.
     * 
     * @param iterable $array
     * @param callable $callback
     * @param mixed $default
     * @return mixed
     */
    public static function last(iterable $array, callable $callback = null, mixed $default = null): mixed
    {
        $array = self::getArrayableItems($array);
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }

        foreach (array_reverse($array) as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return self::getValue($default);
    }

    /**
     * Sort the array.
     * 
     * @param iterable $array
     * @param bool $descending
     * @param int $options
     * @return static
     */
    public static function sort(iterable &$array, bool $descending = false, int $options = SORT_REGULAR): bool
    {
        $array = self::getArrayableItems($array);
        return $descending ? rsort($array, $options) : sort($array, $options);
    }

    /**
     * Sort the array keys.
     * 
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public static function sortKeys(array &$array, bool $descending = false, int $options = SORT_REGULAR): bool
    {
        return $descending ? krsort($array, $options) : ksort($array, $options);
    }

    /**
     * Searches the array for a given value and returns the first corresponding key if successful.
     * 
     * @param int|string|callable $callback
     * @param bool $strict
     * @return int|string|false
     */
    public static function search(callable $callback, iterable $item, bool $strict = false): int|string|false
    {
        $item = self::getArrayableItems($item);
        return array_search($callback, $item, $strict);
    }

    /**
     * Exchanges all keys with their associated values in an array.
     * 
     * @param iterable $item
     * @return array
     */
    public static function flip(iterable $item): array
    {
        return array_flip(self::getArrayableItems($item));
    }

    /**
     * Generate URL-encoded query string
     * 
     * @param iterable $item
     * @return string
     */
    public static function query(iterable $item): string
    {
        return http_build_query(self::getArrayableItems($item), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Concatenate values of a given key as a string.
     * 
     * @param string $glue
     * @param iterable $item
     * @return string
     */
    public static function implode(string $glue, iterable $item): string
    {
        $item = self::getArrayableItems($item);
        return implode($glue, $item);
    }

    /**
     * Computes the intersection of arrays.
     * 
     * @param iterable ...$arrays
     * @return array
     */
    public static function intersect(iterable ...$arrays): array
    {
        $arrays = Arr::map(fn (iterable $array) => self::getArrayableItems($array), $arrays);
        return array_intersect(...$arrays);
    }

    public static function where(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public static function whereNotNull(array $array): array
    {
        return self::where($array, function ($value) {
            return $value !== null;
        });
    }

    public static function whereNotEmpty(array $array): array
    {
        return static::where($array, function ($value) {
            return !empty($value);
        });
    }

    public static function whereNotBlank(array $array): array
    {
        return static::where($array, function ($value) {
            return !empty(trim($value));
        });
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param iterable $array
     * @param int $depth
     * @return array
     */
    public static function flatten(iterable $array, int $depth = PHP_INT_MAX): array
    {
        if ($depth === 0) {
            return array_values((array)$array);
        }
        $result = [];
        foreach ($array as $value) {
            if (!is_array($value)) {
                $result[] = $value;
            } else {
                $result = array_merge($result, self::flatten($value, $depth - 1));
            }
        }
        return $result;
    }

    /**
     * Collapse an array of arrays into a single array.
     *
     * @param iterable $array
     * @return array
     */
    public static function collapse(iterable $array): array
    {
        $results = [];

        foreach ($array as $values) {
            $values = self::getArrayableItems($values);
            if (!is_array($values)) {
                continue;
            }
            $results[] = $values;
        }

        return array_merge([], ...$results);
    }

    /**
     * Creates an array by using one array for keys and another for its values.
     * 
     * @param iterable $keys
     * @param iterable $values
     * @return array|false
     */
    public static function combine(iterable $keys, iterable $values): array|false
    {
        $keys = self::getArrayableItems($keys);
        $values = self::getArrayableItems($values);
        if (!is_array($keys) || !is_array($values)) {
            return false;
        }
        return array_combine($keys, $values);
    }

    /**
     * Shuffle an array.
     * 
     * @param array $array
     * @param int $seed
     * @return array
     */
    public static function shuffle(array $array, int $seed = null): array
    {
        if ($seed === null) {
            shuffle($array);
        } else {
            mt_srand($seed);
            shuffle($array);
            mt_srand();
        }

        return $array;
    }

    /**
     * Extract a slice of the array
     * 
     * @param array $array
     * @param int $start
     * @param int $length
     * @param bool $preserveKeys
     * @return array
     */
    public static function slice(array $array, int $start, int $length = null, bool $preserveKeys = false): array
    {
        return array_slice($array, $start, $length, $preserveKeys);
    }

    /**
     * If the given value is not an array and not null, wrap it in one.
     * 
     * @param mixed $value
     * @return array
     */
    public static function wrap(mixed $value): array
    {
        if (is_null($value)) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }

    protected static function getValue(mixed $value): mixed
    {
        return $value instanceof Closure ? $value() : $value;
    }

    protected static function getArrayableItems(Collection|iterable $items): array
    {
        if ($items instanceof Collection) {
            return $items->all();
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        }
        return (array) $items;
    }

    protected static function explodeKeys(string|int|null ...$keys): array
    {
        return Arr::map(function ($key) {
            if ($key !== null) {
                $key = self::explodeKey($key);
            }
            return $key;
        }, $keys);
    }

    private static function explodeKey(string|int $key): array
    {
        return explode('.', $key);
    }
}
