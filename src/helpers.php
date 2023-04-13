<?php

use Penguin\Component\Collection\Collection;

if (!function_exists('collect')) {
    function collect(mixed $items = []): Collection {
        return new Collection($items);
    }
}

if (!function_exists('lazycollect')) {
    function lazycollect(mixed $items = []) {
        return new Collection($items);
    }
}

if (!function_exists('extract_item')) {
    function extract_item(array $array, array $segments): mixed {
        $result = $array;
        $tmp = null;
        foreach ($array as $key => $item) {
            if ($key == $segments[0]) {
                if (is_array($item) && count($segments) > 1) {
                    $tmp = extract_item($item, array_slice($segments, 1));
                    $result = $item === $tmp ? $result : $tmp;
                } else {
                    $result = $item;
                }
                break;
            }
        }
        return $result;
    }
}
