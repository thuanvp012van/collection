<?php

namespace Penguin\Component\Collection\Traits;

use Penguin\Component\Collection\LazyCollection;
use Penguin\Component\Collection\Collection;
use Penguin\Component\Collection\Enumerable;
use Penguin\Component\Collection\Arr;
use JsonSerializable;
use LogicException;
use Traversable;
use UnitEnum;
use Closure;
use Penguin\Component\Collection\ItemNotFoundException;
use Penguin\Component\Collection\MultipleItemsFoundException;

trait EnumeratesValues
{
    protected array $sorts = [];

    /**
     * Collect the values into a collection.
     * 
     * @return \Penguin\Component\Collection\Collection
     */
    public function collect(): Collection
    {
        return new Collection($this->all());
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
     * Counts all elements in a collection.
     * 
     * @param string|int|callable $key
     * @return int
     */
    public function count(string|int|callable $key = null): int
    {
        if ($key === null) {
            return $this instanceof LazyCollection ? iterator_count($this->getIterator()) : count($this->items);
        }

        $count = 0;
        if (is_callable($key)) {
            foreach ($this as $item) {
                if ($key($item)) {
                    $count++;
                }
            }
            return $count;
        }

        $segments = $this->explodeKey($key);
        foreach ($this as $item) {
            if ($item !== extract_item($item, $segments)) {
                $count++;
            }
        }
        return $count;
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
            return array_sum($this->all());
        }

        $sum = 0;
        $term = 0;
        if (is_callable($key)) {
            foreach ($this as $item) {
                if (is_numeric($term = $key($item))) {
                    $sum += $term;
                }
            }
            return $sum;
        }

        $segments = $this->explodeKey($key);
        foreach ($this as $item) {
            if (is_array($item) && is_numeric($term = extract_item($item, $segments))) {
                $sum += $term;
            }
        }

        return $sum;
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
        return $this->contains($key, $value, true);
    }

    /**
     * Get the first item in the collection, but only if exactly one item exists. Otherwise, throw an exception.
     * 
     * @param string|callable|null $key
     * @param mixed $value
     * @param string $operator
     * @return mixed
     */
    public function sole(string|callable $key = null, mixed $value = null, string $operator = '=='): mixed
    {
        if ($key === null) {
            return $this->first();
        }

        if (is_string($key)) {
            $segments = $this->explodeKey($key);
            $key = function ($item) use ($segments, $value, $operator) {
                $childItem = extract_item($item, $segments);
                return $childItem !== $item && $this->compare($childItem, $operator, $value);
            };
        }
        $items = $this->filter($key);
        $count = $items->count();

        if ($count === 0) {
            throw new ItemNotFoundException;
        }
        
        if ($count > 1) {
            throw new MultipleItemsFoundException($count);
        }

        return $items->first();
    }

    /**
     * Split a collection into a certain number of groups, and fill the first groups completely.
     *
     * @param int $numberOfGroups
     * @return static
     */
    public function splitIn(int $numberOfGroups): static
    {
        return $this->chunk(ceil($this->count() / $numberOfGroups));
    }

    /**
     * Reduce the collection to a single value.
     * 
     * @param callable $callback
     * @param mixed $initial
     * @return mixed
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $result = $initial;

        foreach ($this as $key => $value) {
            $result = $callback($result, $value, $key);
        }

        return $result;
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     * 
     * @param callable $callback
     * @return static
     */
    public function reject(callable $callback): static
    {
        return $this->filter(function ($item, $key) use ($callback) {
            return !$callback($item, $key);
        });
    }

    /**
     * Run a map over each nested chunk of items.
     *
     * @param callable $callback
     * @return static
     */
    public function mapSpread(callable $callback): static
    {
        return $this->map(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;
            return $callback(...$chunk);
        });
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @param callable $callback
     * @return static
     */
    public function flatMap(callable $callback)
    {
        return $this->map($callback)->collapse();
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
        $segments = $this->explodeKey($key);
        foreach ($this as $item) {
            $childItem = extract_item($item, $segments);
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
     * "Paginate" the collection by slicing it into a smaller collection.
     *
     * @param int $page
     * @param int $perPage
     * @return static
     */
    public function forPage(int $page, int $perPage): static
    {
        $offset = max(0, ($page - 1) * $perPage);
        return $this->slice($offset, $perPage);
    }

    /**
     * Create a new collection consisting of every n-th element.
     * 
     * @param int $step
     * @param int $offset
     * @param static
     */
    public function nth(int $step, int $offset = 0): static
    {
        $new = [];
        $position = 0;
        foreach ($this->slice($offset)->items as $item) {
            if ($position % $step === 0) {
                $new[] = $item;
            }
            $position++;
        }
        return new static($new);
    }

    /**
     * Take items in the collection until the given callback return false.
     * 
     * @param int|callable $callback
     * @return static
     */
    public function takeUntil(int|callable $callback): static
    {
        if (is_int($callback)) {
            $callback = function ($item) use ($callback) {
                return $callback <= $item;
            };
        }
        return $this->filter(function ($item, $key) use ($callback) {
            return !$callback($item, $key);
        });
    }

    /**
     * Take items in the collection until the given callback return true.
     * 
     * @param int|callable $callback
     * @return static
     */
    public function takeWhile(int|callable $callback): static
    {
        if (is_int($callback)) {
            $callback = function ($item) use ($callback) {
                return $callback <= $item;
            };
        }
        return $this->filter(function ($item, $key) use ($callback) {
            return $callback($item, $key);
        });
    }

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param callable $callback
     * @return $this
     */
    public function tap(callable $callback): static
    {
        $callback($this);
        return $this;
    }

    /**
     * Pass the collection to the given callback and return the result.
     * 
     * @param callable $callback
     * @return mixed
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
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
        foreach ($this as $key => $value) {
            if ($callback($value, $key)) {
                $passed[] = $value;
            } else {
                $failed[] = $value;
            }
        }
        return new static([new static($passed), new static($failed)]);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param string|int $key
     * @param mixed $value
     * @param string $operator
     * @return static
     */
    public function where(string|int $key, mixed $value, string $operator = '='): static
    {
        $this->checkValidOperator($operator);
        $segments = $this->explodeKey($key);
        return $this->filter(function ($item) use ($segments, $operator, $value) {
            $childItem = extract_item($item, $segments);
            return $this->compare($childItem, $operator, $value);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param string|int $key
     * @param mixed $value
     * @return static
     */
    public function whereStrict(string|int $key, mixed $value): static
    {
        return $this->where($key, $value, '===');
    }

    /**
     * Filter items such that the value of the given key is between the given values.
     * 
     * @param string|int $key
     * @param string|int $min
     * @param string|int $max
     * @param static
     */
    public function whereBetween(string|int $key, string|int $min, string|int $max): static
    {
        $segments = $this->explodeKey($key);
        return $this->filter(function ($item) use ($segments, $min, $max) {
            $childItem = extract_item($item, $segments);
            return $item !== $childItem && $childItem >= $min && $childItem <= $max;
        });
    }

    /**
     * Filter items such that the value of the given key is not between the given values.
     * 
     * @param string|int $key
     * @param string|int $min
     * @param string|int $max
     * @param static
     */
    public function whereNotBetween(string|int $key, string|int $min, string|int $max): static
    {
        $segments = $this->explodeKey($key);
        return $this->filter(function ($item) use ($segments, $min, $max) {
            $childItem = extract_item($item, $segments);
            return $item !== $childItem && $childItem < $min || $childItem > $max;
        });
    }

    /**
     * Filter items by the given key value pair.
     * 
     * @param string|int $key
     * @param array $value
     * @param bool $strict
     * @param static
     */
    public function whereIn(string|int $key, array $values, bool $strict = false): static
    {
        $segments = $this->explodeKey($key);
        return $this->filter(function ($item) use ($segments, $values, $strict) {
            $childItem = extract_item($item, $segments);
            return $item !== $childItem && in_array($childItem, $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     * 
     * @param string|int $key
     * @param array $values
     * @param static
     */
    public function whereInStrict(string|int $key, array $values): static
    {
        return $this->whereIn($key, $values, true);
    }

    /**
     * Filter items by the given key value pair.
     * 
     * @param string|int $key
     * @param array $values
     * @param bool $strict
     * @return static
     */
    public function whereNotIn(string|int $key, mixed $values, bool $strict = false): static
    {
        $segments = $this->explodeKey($key);
        $values = $this->getArrayableItems($values);
        return $this->filter(function ($item) use ($segments, $values, $strict) {
            $childItem = extract_item($item, $segments);
            return $item !== $childItem && !in_array($childItem, $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     * 
     * @param string|int $key
     * @param array $values
     * @param static
     */
    public function whereNotInStrict(string|int $key, array $values): static
    {
        return $this->whereNotIn($key, $values, true);
    }

    /**
     * Filter items by the given types.
     * 
     * @param string ...$types
     * @return static
     */
    public function whereInstanceOf(string ...$types): static
    {
        return $this->filter(function ($item) use ($types) {
            foreach ($types as $type) {
                if ($item instanceof $type) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Filter items by the given key value pair like where like sql.
     * 
     * e.g. collection(
     *    ['product' => 'Desk', 'price' => 200],
     *    ['product' => 'Chair', 'price' => 100],
     *    ['product' => 'Bookcase', 'price' => 150],
     *    ['product' => 'Door', 'price' => 100]
     * )->whereLike('product', 'D%')
     * => [
     *      ['product' => 'Desk', 'price' => 200],
     *      ['product' => 'Door', 'price' => 100]
     * ]
     * 
     * @param string|int $key
     * @param string $value
     * @param bool $strict
     * @return static
     */
    public function whereLike(string|int $key, string $value, bool $strict = false): static
    {
        $encoding = mb_detect_encoding($value);
        if (!$strict) $value = iconv($encoding, 'ASCII//TRANSLIT//IGNORE', $value);

        if (preg_match("/^%(.*)%$/", $value, $matches)) {
            $value = $matches[1];
            return $this->handleWhereLike($key, $value, '!==', 'false', 0, $encoding, $strict);
        }

        if (preg_match("/^(.*)%$/", $value, $matches)) {
            $value = $matches[1];
            return $this->handleWhereLike($key, $value, '===', 0, 0, $encoding, $strict);
        }

        if (preg_match("/^%(.*)$/", $value, $matches)) {
            $value = $matches[1];
            $length = strlen($value);
            return $this->handleWhereLike(
                $key,
                $value,
                '!==',
                'false',
                'strlen($childItem) - ' . $length,
                $encoding,
                $strict
            );
        }

        $segments = $this->explodeKey($key);
        return $this->filter(function ($item) use ($segments, $value, $strict, $encoding) {
            $childItem = extract_item($item, $segments);

            $type = gettype($childItem);
            if ($type !== 'integer' && $type !== 'double' && $type !== 'string') return false;

            if (!$strict) $childItem = iconv($encoding, 'ASCII//TRANSLIT//IGNORE', (string)$childItem);

            return $item !== $childItem && $childItem === $value;
        });
    }

    /**
     * Filter items where the value is null.
     *
     * @return string|int $key
     * @return static
     */
    public function whereNull(string|int $key): static
    {
        return $this->whereStrict($key, null);
    }

    /**
     * Filter items where the value is not null.
     *
     * @return string|int $key
     * @return static
     */
    public function whereNotNull(string|int $key): static
    {
        return $this->where($key, null, '!==');
    }

    /**
     * Filter items where the value is not empty.
     * 
     * @return string|int $key
     * @return static
     */
    public function whereNotEmpty(string|int $key): static
    {
        $segments = $this->explodeKey($key);
        return $this->filter(function ($item) use ($segments) {
            $childItem = extract_item($item, $segments);
            return $item !== $childItem && !empty($childItem);
        });
    }

    /**
     * Filter items where the value is not blank.
     *
     * @return static
     */
    public function whereNotBlank(): static
    {
        return $this->filter(function (string|int $item) {
            return !empty(trim($item));
        });
    }

    /**
     * Apply the callback if the given "value" is true.
     * 
     * @param bool $value
     * @param callable $callback
     * @param callable $default
     * @return $this
     */
    public function when(bool $value, callable $callback, callable $default = null): static
    {
        $value ? $callback($this) : ($default === null ? null : $default($this));
        return $this;
    }

    /**
     * Apply the callback if the collection is empty.
     * 
     * @param callable $callback
     * @param callable $default
     * @return $this
     */
    public function whenEmpty(callable $callback, callable $default = null): static
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    /**
     * Apply the callback if the collection is not empty.
     * 
     * @param callable $callback
     * @param callable $default
     * @return $this
     */
    public function whenNotEmpty(callable $callback, callable $default = null): static
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    /**
     * Wrap the given value in a collection if applicable.
     * 
     * @param mixed $value
     * @return array
     */
    public static function wrap(mixed $value): static
    {
        return $value instanceof Enumerable
            ? new static($value)
            : new static(Arr::wrap($value));
    }

    /**
     * Return unique items from the collection.
     * 
     * @param callable|string|null $key
     * @param bool $strict
     * @return static
     */
    public function unique(callable|string $key = null, bool $strict = false): static
    {
        if ($this instanceof Collection && $key === null && $strict === false) {
            return new static(Arr::unique($this->items, SORT_REGULAR));
        }

        $exists = [];
        if ($key === null) {
            $callback = function ($item) use (&$exists, $strict) {
                if (in_array($item, $exists, $strict)) {
                    return false;
                }
                $exists[] = $item;
                return true;
            };
        } else if (is_string($key)) {
            $segments = $this->explodeKey($key);
            $callback = function ($item) use (&$segments, &$exists, $strict) {
                $childItem = extract_item($item, $segments);
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
     * Get the underlying items from the given collection if applicable.
     * 
     * @param mixed $value
     * @return mixed
     */
    public static function unwrap(mixed $value): mixed
    {
        return $value instanceof Enumerable ? $value->all() : $value;
    }

    /**
     * Apply the callback if the given "value" is false.
     * 
     * @param bool $value
     * @param callable $callback
     * @param callable $default
     * @param $this
     */
    public function unless(bool $value, callable $callback, callable $default = null): static
    {
        return $this->when(!$value, $callback, $default);
    }

    /**
     * Apply the callback if the collection is not empty.
     * 
     * @param callable $callback
     * @param callable $default
     * @return $this
     */
    public function unlessEmpty(callable $callback, callable $default = null): static
    {
        return $this->whenNotEmpty($callback, $default);
    }

    /**
     * Apply the callback if the collection is empty.
     * 
     * @param callable $callback
     * @param callable $default
     * @return $this
     */
    public function unlessNotEmpty(callable $callback, callable $default = null): static
    {
        return $this->whenEmpty($callback, $default);
    }

    /**
     * Create a new collection by invoking the callback a given amount of times.
     *
     * @param int $number
     * @param callable $callback
     * @return static
     */
    public static function times($number, callable $callback = null): static
    {
        if ($number < 1) {
            return new static;
        }
        return static::range(1, $number)->map($callback);
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
     * Convert all items to array.
     * 
     * @return array
     */
    public function toArray(): array
    {
        return $this->map(fn ($value) => $value instanceof Enumerable ? $value->toArray() : $value)->all();
    }

    /**
     * Retrieve duplicate items from the collection using strict comparison.
     * 
     * @param string|callable|null $callback
     * @return static
     */
    public function duplicatesStrict(string|callable $callback = null): static
    {
        return $this->duplicates($callback, true);
    }

    /**
     * Dump the collection and end the script.
     * 
     * @param mixed ...$args
     * @return void
     */
    public function dd(mixed ...$args): void
    {
        dd($args + $this->all());
    }

    /**
     * Execute a callback over each item.
     * 
     * @param callable $callback
     * @return $this
     */
    public function each(callable $callback): static
    {
        foreach ($this as $key => $value) {
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
            foreach ($this as $key => $item) {
                if (!$callback($item, $key)) {
                    return false;
                }
            }
        } else {
            $this->checkValidOperator($operator);
            if (str_contains($key, '.')) {
                $segments = $this->explodeKey($key);
                foreach ($this as $item) {
                    $childItem = extract_item($item, $segments);
                    if ($childItem !== $item && !$this->compare($childItem, $operator, $value)) {
                        return false;
                    }
                }
            } else {
                foreach ($this as $item) {
                    if (array_key_exists($key, $item) && !$this->compare($item[$key], $operator, $value)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif ($value instanceof Enumerable) {
                return $value->toArray();
            }

            return $value;
        }, $this->all());
    }

    protected function handleWhereLike(
        string|int $key,
        string $value,
        string $operator,
        string $desiredPosition,
        int|string $offset = 0,
        string $encoding = 'UTF-8',
        bool $strict = false
    ): static {
        $segments = $this->explodeKey($key);
        return $this->filter(function ($item) use ($segments, $value, $operator, $desiredPosition, $offset, $encoding, $strict) {
            $childItem = extract_item($item, $segments);
            $type = gettype($childItem);
            if ($type !== 'integer' && $type !== 'double' && $type !== 'string') return false;

            if ($strict) {
                $position = strpos((string)$childItem, $value, eval("return $offset;"));
            } else {
                $childItem = iconv($encoding, 'ASCII//TRANSLIT//IGNORE', (string)$childItem);
                $position = stripos((string)$childItem, $value, eval("return $offset;"));
            }
            if ($position === false) return false;
            return eval("return $position $operator $desiredPosition;");
        });
    }

    /**
     * Check valid operator.
     * 
     * @param string $operator
     * @throws LogicException If operator is not valid
     */
    protected function checkValidOperator(string $operator): void
    {
        $allowOperator = ['=', '==', '===', '!=', '!==', '>', '>=', '<', '<=', '<>', '<=>'];
        if (!in_array($operator, $allowOperator)) {
            throw new LogicException("Invalid $operator operator");
        }
    }

    /**
     * Compare item and value using operator.
     * 
     * @param mixed $item
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function compare(mixed $item, string $operator, mixed $value): bool
    {
        switch ($operator) {
            default:
            case '=':
            case '==':
                return $item == $value;
            case '!=':
            case '<>':
                return $item != $value;
            case '<':
                return $item < $value;
            case '>':
                return $item > $value;
            case '<=':
                return $item <= $value;
            case '>=':
                return $item >= $value;
            case '===':
                return $item === $value;
            case '!==':
                return $item !== $value;
            case '<=>':
                return $item <=> $value;
        }
    }

    /**
     * Extract key.
     * 
     * @param string|int $key
     * @return array
     */
    protected function explodeKey(string|int $key): array
    {
        return explode('.', $key);
    }

    /**
     * Get arrayable items.
     * 
     * @param mixed $items
     * @return array
     */
    protected function getArrayableItems(mixed $items): array
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof Enumerable) {
            return $items->all();
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        } elseif ($items instanceof JsonSerializable) {
            return (array) $items->jsonSerialize();
        } elseif ($items instanceof UnitEnum) {
            return [$items];
        }
        return (array) $items;
    }

    protected function getValue(mixed $value): mixed
    {
        return $value instanceof Closure ? $value() : $value;
    }
}
