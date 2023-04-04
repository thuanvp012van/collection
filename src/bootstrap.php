<?php

use Penguin\Component\Collection\Collection;

if (!function_exists('collect')) {
    function collect(mixed $items = []) {
        return new Collection($items);
    }
}

if (!function_exists('lazycollect')) {
    function lazycollect(mixed $items = []) {
        return new Collection($items);
    }
}

/**
 * Sort the collection using the given callback but the priority is reduced.
 * 
 * @param string|callable $callback
 * @param bool $descending
 * @param int $options
 * @return static
 */
Collection::plug('thenBy', function (string|callable $callback, bool $descending = false, int $options = SORT_REGULAR): static {
    if (empty($this->sorts)) {
        throw new BadMethodCallException('Can\'t use thenBy method without using sortBy or sortByDesc');
    }
    
    $sorts = [];
    $index = 0;
    $keys = [];
    
    if (is_string($callback)) {
        $segments = $this->extractKey($callback);
        $callback = function($item) use ($segments) {
            $childItem = $this->getItemRecursive($item, $segments);
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
});

/**
 * Sort the collection in descending order using the given callback but the priority is reduced.
 * 
 * @param string|callable $callback
 * @param bool $descending
 * @param int $options
 * @return static
 */
Collection::plug('thenByDesc', function (string|callable $key, int $options = SORT_REGULAR): static {
    if (empty($this->sorts)) {
        throw new BadMethodCallException('Can\'t use thenByDesc method without using sortBy or sortByDesc');
    }
    return $this->thenBy($key, true, $options);
});