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

        foreach (self::extractKey($key) as $segment) {
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
                $segments = self::extractKey($key);
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
     * @param array $items
     * @return array<int, string|int>
     */
    public static function keys(iterable $items): array
    {
        $items = self::getArrayableItems($items);
        return array_keys($items);
    }

    /**
     * Return all the values of an array.
     * 
     * @param array $items
     * @return array<int, mixed>
     */
    public static function values(iterable $items): array
    {
        $items = self::getArrayableItems($items);
        return array_values($items);
    }

    public static function exists(ArrayAccess|array $array, string|int $key): bool
    {
    }

    public static function remove(array &$array, string|int $key): void
    {
        unset($array[$key]);
    }

    public static function count(Countable|iterable $items): int
    {
        if (!$items instanceof Countable) {
            $items = self::getArrayableItems($items);
        }
        return count($items);
    }

    public static function countBy(callable $callback = null, array $array): array
    {
        if ($callback !== null) {
            $array = Arr::map($callback, $array);
        }
        return array_count_values($array);
    }

    public static function diff(iterable ...$arrays): array
    {
        foreach ($arrays as &$array) {
            $array = self::getArrayableItems($array);
        }
        return array_diff(...$array);
    }

    public static function diffAssoc(iterable ...$arrays): array
    {
        foreach ($arrays as &$array) {
            $array = self::getArrayableItems($array);
        }
        return array_diff_assoc(...$array);
    }

    public static function diffKeys(iterable ...$arrays): array
    {
        foreach ($arrays as &$array) {
            $array = self::getArrayableItems($array);
        }
        return array_diff_key(...$array);
    }

    public static function empty(array $array): bool
    {
        return empty($array);
    }

    public static function pad(array $array, int $size, mixed $value): array
    {
        return array_pad($array, $size, $value);
    }

    public static function randomKey(array $array, int $num = 1): array|string|int
    {
        return array_rand($array, $num);
    }

    public static function random(array $array, int $num = 1, bool $preserveKeys = false): mixed
    {
        $rand = array_rand($array, $num);
        if (is_array($rand)) {
            $array = array_filter($array, function ($_, $key) use ($rand) {
                return in_array($key, $rand);
            }, ARRAY_FILTER_USE_BOTH);
            return $preserveKeys ? array_values($array) : $array;
        }
        return $array[$rand];
    }

    public static function replace(array ...$arrays): array
    {
        return array_replace(...$arrays);
    }

    public static function replaceRecursive(array ...$arrays): array
    {
        return array_replace_recursive(...$arrays);
    }

    public static function only(iterable $array, string|int ...$keys): array
    {
        $array = self::getArrayableItems($array);
        return array_intersect_key($array, array_flip($keys));
    }

    public static function max(mixed ...$items): mixed
    {
        return max(...$items);
    }

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

    public static function unique(iterable $items, int $flags = SORT_STRING): array
    {
        $items = self::getArrayableItems($items);
        return array_unique($items, $flags);
    }

    public static function first(iterable $array, callable $callback = null, mixed $default = null): mixed
    {
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

    public static function last(array $array, callable $callback = null, mixed $default = null): mixed
    {
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
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public static function sort(array &$array, bool $descending = false, int $options = SORT_REGULAR): bool
    {
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

    public static function flatten(iterable $array, int $depth = 1000): array
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
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof Collection) {
            return $items->all();
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        }
        return (array) $items;
    }

    private static function extractKey(string|int $key): array
    {
        return explode('.', $key);
    }
}
