<?php

namespace Penguin\Component\Collection\Traits;

use Closure;
use JsonSerializable;
use LogicException;
use Penguin\Component\Collection\Arr;
use Penguin\Component\Collection\Collection;
use Traversable;
use UnitEnum;

trait EnumeratesValues
{
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

        foreach ($this->all() as $key => $value) {
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
        return new static(Arr::map(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        }, $this->items));
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
        $segments = $this->extractKey($key);
        return $this->filter(function ($item) use ($segments, $operator, $value) {
            $childItem = $this->getItemRecursive($item, $segments);
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
        $segments = $this->extractKey($key);
        return $this->filter(function ($item) use ($segments, $min, $max) {
            $childItem = $this->getItemRecursive($item, $segments);
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
        $segments = $this->extractKey($key);
        return $this->filter(function ($item) use ($segments, $min, $max) {
            $childItem = $this->getItemRecursive($item, $segments);
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
        $segments = $this->extractKey($key);
        return $this->filter(function ($item) use ($segments, $values, $strict) {
            $childItem = $this->getItemRecursive($item, $segments);
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
        $segments = $this->extractKey($key);
        $values = $this->getArrayableItems($values);
        return $this->filter(function ($item) use ($segments, $values, $strict) {
            $childItem = $this->getItemRecursive($item, $segments);
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

        $segments = $this->extractKey($key);
        return $this->filter(function ($item) use ($segments, $value, $strict, $encoding) {
            $childItem = $this->getItemRecursive($item, $segments);

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
        $segments = $this->extractKey($key);
        return $this->filter(function ($item) use ($segments) {
            $childItem = $this->getItemRecursive($item, $segments);
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
     * Apply the callback if the given "value" is (or resolves to) truthy.
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
     * Dump the collection and end the script.
     */
    public function dd(): void
    {
        dd($this);
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
        $segments = $this->extractKey($key);
        return $this->filter(function ($item) use ($segments, $value, $operator, $desiredPosition, $offset, $encoding, $strict) {
            $childItem = $this->getItemRecursive($item, $segments);
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
    protected function extractKey(string|int $key): array
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
        } elseif ($items instanceof Collection) {
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