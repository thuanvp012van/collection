<?php

namespace Penguin\Component\Collection;

use RuntimeException;
use Throwable;

class MultipleItemsFoundException extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param int $count
     * @param int $code
     * @param Throwable|null $previous
     * @return void
     */
    public function __construct(public int $count, int $code = 0, Throwable $previous = null)
    {
        parent::__construct("{$this->count} items were found.", $code, $previous);
    }

    /**
     * Get the number of items found.
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }
}